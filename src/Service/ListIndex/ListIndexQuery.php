<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\ListIndex;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\ConditionInterface;
use Drupal\Core\Database\Query\SelectInterface;

/**
 * Answers the Paragraphs List page from the index table.
 *
 * Every query is answered by an index and touches at most one page of rows,
 * with the two named exceptions: a "contains" search on the content title
 * (scans node titles) and a "contains" search on the label (scans the index's
 * short label strings). A title search touches every matching node, because
 * node titles are not copied into the index.
 *
 * Paging is by keyset, "the 50 after this key", never OFFSET, so page 10,000
 * costs the same as page 1.
 */
final class ListIndexQuery {

  use ListIndexValuesTrait;

  /**
   * Rows per page.
   */
  public const PAGE_SIZE = 50;

  /**
   * The sort keys and the index columns each one orders by, in order.
   */
  public const SORTS = [
    'pid' => ['i.pid'],
    'type' => ['i.bundle', 'i.pid'],
    'nid' => ['i.root_type', 'i.root_id', 'i.pid'],
    'label' => ['i.label', 'i.pid'],
    'node_title' => ['i.root_title', 'i.pid'],
  ];

  /**
   * Longest cursor accepted, in characters.
   *
   * Worst case is a 191-character label of 4-byte characters: 764 bytes of
   * JSON plus the pid and brackets (about 790), base64 at 4/3 (about 1054),
   * rounded up with margin.
   */
  public const CURSOR_MAX = 1200;

  /**
   * Columns compared as integers in a cursor.
   */
  private const NUMERIC = ['i.pid', 'i.root_id'];

  public function __construct(
    private readonly Connection $database,
    private readonly CacheBackendInterface $cache,
  ) {}

  /**
   * Normalises raw request values into the criteria this class understands.
   *
   * @param array<string, mixed> $raw
   *   Raw query-string values.
   *
   * @return array{type: string, title: string, title_mode: string, label: string, sort: string, dir: string, after: string, before: string}
   *   Clean criteria; unknown values fall back to the defaults.
   */
  public function normalize(array $raw): array {
    $text = static fn(mixed $value, int $max = 255): string => is_string($value) ? mb_substr(trim($value), 0, $max) : '';
    $sort = $text($raw['sort'] ?? '', 16);
    $dir = $text($raw['dir'] ?? '', 4);
    $mode = $text($raw['title_mode'] ?? '', 8);
    $title = $text($raw['title'] ?? '');
    // A "starts with" title search with no sort chosen is ordered by the same
    // index it ranges over (root_title, pid), so it stays index-served however
    // rare the prefix; any sort the person picks is honoured as chosen.
    if ($title !== '' && $mode !== 'contains' && !isset(self::SORTS[$sort])) {
      $sort = 'node_title';
    }
    return [
      'type' => $text($raw['type'] ?? '', 32),
      'title' => $title,
      'title_mode' => $mode === 'contains' ? 'contains' : 'starts',
      'label' => $text($raw['label'] ?? ''),
      'sort' => isset(self::SORTS[$sort]) ? $sort : 'pid',
      'dir' => $dir === 'desc' ? 'desc' : 'asc',
      'after' => $text($raw['after'] ?? '', self::CURSOR_MAX),
      'before' => $text($raw['before'] ?? '', self::CURSOR_MAX),
    ];
  }

  /**
   * Fetches one page.
   *
   * @param array{type: string, title: string, title_mode: string, label: string, sort: string, dir: string, after: string, before: string} $criteria
   *   Normalised criteria.
   *
   * @return array{rows: list<array{pid: int, bundle: string, root_type: string, root_id: int, depth: int, langcode: string, label: string, root_title: string}>, next: string|null, prev: string|null}
   *   The rows, and a cursor for each direction that has more rows.
   */
  public function fetchPage(array $criteria): array {
    $columns = self::SORTS[$criteria['sort']];
    $backwards = FALSE;
    $cursor = NULL;
    if ($criteria['before'] !== '') {
      $cursor = $this->decodeCursor($criteria['before'], $columns);
      $backwards = $cursor !== NULL;
    }
    if ($cursor === NULL && $criteria['after'] !== '') {
      $cursor = $this->decodeCursor($criteria['after'], $columns);
    }

    if ($criteria['sort'] === 'node_title') {
      $rows = $this->titleSortedRows($criteria, $cursor, $backwards);
    }
    else {
      $query = $this->baseQuery($criteria);
      $query->fields('i');

      // Ascending order reads "greater than the cursor"; descending, "less
      // than". Paging backwards flips both, and the rows are flipped back.
      $ascending = ($criteria['dir'] === 'asc') !== $backwards;
      if ($cursor !== NULL) {
        $query->condition($this->keysetCondition($query, $columns, $cursor, $ascending ? '>' : '<'));
      }
      foreach ($columns as $column) {
        $query->orderBy($column, $ascending ? 'ASC' : 'DESC');
      }
      $query->range(0, self::PAGE_SIZE + 1);
      $rows = self::typedRows($query);
    }
    $overflow = count($rows) > self::PAGE_SIZE;
    $rows = array_slice($rows, 0, self::PAGE_SIZE);
    if ($backwards) {
      $rows = array_reverse($rows);
    }

    if ($backwards) {
      $has_prev = $overflow;
      $has_next = TRUE;
    }
    else {
      $has_next = $overflow;
      $has_prev = $cursor !== NULL;
    }
    return [
      'rows' => $rows,
      'next' => $has_next && $rows !== [] ? $this->encodeCursor($rows[count($rows) - 1], $columns) : NULL,
      'prev' => $has_prev && $rows !== [] ? $this->encodeCursor($rows[0], $columns) : NULL,
    ];
  }

  /**
   * Reads one page-plus-one of rows sorted by Content title, orphans last.
   *
   * Orphans have an empty root_title, which would sort first ascending. They
   * must come last in BOTH directions, and a mixed-direction ORDER BY cannot
   * use one index, so the order is two index-served segments read in turn: the
   * titled rows (a range on (root_title, pid)) and then the untitled ones (the
   * same index, root_title = '', ordered by pid). A cursor says which segment
   * it is in (an empty title is the untitled one); a page that runs out of one
   * segment is filled from the next, so Next and Previous cross the boundary
   * with no duplicate and no gap.
   *
   * @param array{type: string, title: string, title_mode: string, label: string, sort: string, dir: string, after: string, before: string} $criteria
   *   Normalised criteria.
   * @param list<int|string>|null $cursor
   *   The decoded cursor, or NULL for the first page.
   * @param bool $backwards
   *   Whether to read the rows before the cursor.
   *
   * @return list<array{pid: int, bundle: string, root_type: string, root_id: int, depth: int, langcode: string, label: string, root_title: string}>
   *   Up to PAGE_SIZE + 1 rows, in display order.
   */
  private function titleSortedRows(array $criteria, ?array $cursor, bool $backwards): array {
    $ascending = ($criteria['dir'] === 'asc') !== $backwards;
    $start = ($cursor !== NULL && $cursor[0] === '') ? 1 : 0;
    // Segment 0 is the titled rows, 1 the untitled; forward reads 0 then 1.
    $order = $backwards ? range($start, 0) : range($start, 1);

    $rows = [];
    foreach ($order as $position => $segment) {
      $remaining = self::PAGE_SIZE + 1 - count($rows);
      if ($remaining <= 0) {
        break;
      }
      $query = $this->baseQuery($criteria);
      $query->fields('i');
      $query->condition('i.root_title', '', $segment === 0 ? '<>' : '=');
      $columns = $segment === 0 ? ['i.root_title', 'i.pid'] : ['i.pid'];
      if ($position === 0 && $cursor !== NULL) {
        $values = $segment === 0 ? $cursor : [$cursor[1]];
        $query->condition($this->keysetCondition($query, $columns, $values, $ascending ? '>' : '<'));
      }
      foreach ($columns as $column) {
        $query->orderBy($column, $ascending ? 'ASC' : 'DESC');
      }
      $query->range(0, $remaining);
      $rows = array_merge($rows, self::typedRows($query));
    }
    return $rows;
  }

  /**
   * Runs a query on the index and returns its rows with their types fixed.
   *
   * @return list<array{pid: int, bundle: string, root_type: string, root_id: int, depth: int, langcode: string, label: string, root_title: string}>
   *   The rows.
   */
  private static function typedRows(SelectInterface $query): array {
    $rows = [];
    foreach (self::fetchRows($query) as $record) {
      $rows[] = [
        'pid' => self::toInt($record['pid'] ?? 0),
        'bundle' => self::toString($record['bundle'] ?? ''),
        'root_type' => self::toString($record['root_type'] ?? ''),
        'root_id' => self::toInt($record['root_id'] ?? 0),
        'depth' => self::toInt($record['depth'] ?? 0),
        'langcode' => self::toString($record['langcode'] ?? ''),
        'label' => self::toString($record['label'] ?? ''),
        'root_title' => self::toString($record['root_title'] ?? ''),
      ];
    }
    return $rows;
  }

  /**
   * Counts the rows matching the filters; cached until the index changes.
   *
   * @param array{type: string, title: string, title_mode: string, label: string, sort: string, dir: string, after: string, before: string} $criteria
   *   Normalised criteria; sort and cursor are ignored.
   */
  public function count(array $criteria): int {
    $key = 'paragraphs_bundles:list_count:' . hash('sha256', serialize([
      $criteria['type'], $criteria['title'], $criteria['title_mode'], $criteria['label'],
    ]));
    $cached = $this->cache->get($key);
    if ($cached !== FALSE && is_int($cached->data)) {
      return $cached->data;
    }
    $count = self::toInt(self::fetchValue($this->baseQuery($criteria)->countQuery()));
    $this->cache->set($key, $count, CacheBackendInterface::CACHE_PERMANENT, [
      ListIndexerInterface::CACHE_TAG,
      'paragraph_list',
    ]);
    return $count;
  }

  /**
   * Looks up node titles for one page of nids, in one query.
   *
   * @param list<int> $nids
   *   Node ids.
   *
   * @return array<int, string>
   *   Default-language titles keyed by nid.
   */
  public function nodeTitles(array $nids): array {
    if ($nids === []) {
      return [];
    }
    $titles = [];
    $result = self::fetchRows($this->database->select('node_field_data', 'n')
      ->fields('n', ['nid', 'title'])
      ->condition('n.nid', array_values(array_unique($nids)), 'IN')
      ->condition('n.default_langcode', 1));
    foreach ($result as $record) {
      $titles[self::toInt($record['nid'] ?? 0)] = self::toString($record['title'] ?? '');
    }
    return $titles;
  }

  /**
   * Builds the filtered query without fields, order or limit.
   *
   * @param array{type: string, title: string, title_mode: string, label: string, sort: string, dir: string, after: string, before: string} $criteria
   *   Normalised criteria.
   */
  public function baseQuery(array $criteria): SelectInterface {
    $query = $this->database->select(ListIndexerInterface::TABLE, 'i');
    if ($criteria['type'] !== '') {
      $query->condition('i.bundle', $criteria['type']);
    }
    if ($criteria['label'] !== '') {
      $query->condition('i.label', '%' . $this->database->escapeLike($criteria['label']) . '%', 'LIKE');
    }
    if ($criteria['title'] !== '') {
      // Content title is searched on the index's own root_title copy, so there
      // is no join to the root entity. "Starts with" is a range on the
      // (root_title, pid) index; "contains" scans the index table.
      $like = $this->database->escapeLike($criteria['title']);
      $query->condition('i.root_title', $criteria['title_mode'] === 'contains' ? '%' . $like . '%' : $like . '%', 'LIKE');
    }
    return $query;
  }

  /**
   * Builds "(a > x) OR (a = x AND b > y) OR ..." for a composite key.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   The query the condition is for.
   * @param list<string> $columns
   *   The ordered key columns.
   * @param list<int|string> $values
   *   The cursor values, one per column.
   * @param string $operator
   *   Either '>' or '<'.
   */
  private function keysetCondition(SelectInterface $query, array $columns, array $values, string $operator): ConditionInterface {
    $outer = $query->orConditionGroup();
    foreach ($columns as $position => $column) {
      $group = $query->andConditionGroup();
      for ($earlier = 0; $earlier < $position; $earlier++) {
        $group->condition($columns[$earlier], $values[$earlier]);
      }
      $group->condition($column, $values[$position], $operator);
      $outer->condition($group);
    }
    return $outer;
  }

  /**
   * Encodes a row's key values into a URL-safe cursor.
   *
   * @param array{pid: int, bundle: string, root_type: string, root_id: int, depth: int, langcode: string, label: string, root_title: string} $row
   *   An index row.
   * @param list<string> $columns
   *   The ordered key columns.
   */
  private function encodeCursor(array $row, array $columns): string {
    $values = [];
    foreach ($columns as $column) {
      $name = substr($column, 2);
      $values[] = in_array($column, self::NUMERIC, TRUE) ? self::toInt($row[$name] ?? 0) : self::toString($row[$name] ?? '');
    }
    return rtrim(strtr(base64_encode(json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
  }

  /**
   * Decodes and validates a cursor; NULL when it is not one of ours.
   *
   * @param string $cursor
   *   The cursor from the address.
   * @param list<string> $columns
   *   The ordered key columns the cursor must match.
   *
   * @return list<int|string>|null
   *   The values, or NULL for anything malformed.
   */
  private function decodeCursor(string $cursor, array $columns): ?array {
    if (preg_match('/^[A-Za-z0-9_-]+$/', $cursor) !== 1) {
      return NULL;
    }
    $json = base64_decode(strtr($cursor, '-_', '+/'), TRUE);
    if ($json === FALSE) {
      return NULL;
    }
    $values = json_decode($json, TRUE);
    if (!is_array($values) || !array_is_list($values) || count($values) !== count($columns)) {
      return NULL;
    }
    $clean = [];
    foreach ($columns as $position => $column) {
      $value = $values[$position];
      if (in_array($column, self::NUMERIC, TRUE)) {
        if (!is_int($value) || $value < 0) {
          return NULL;
        }
        $clean[] = $value;
      }
      else {
        if (!is_string($value) || mb_strlen($value) > 255) {
          return NULL;
        }
        $clean[] = $value;
      }
    }
    return $clean;
  }

}
