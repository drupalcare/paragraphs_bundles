<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\ListIndex;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\Core\Theme\ThemeInitializationInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\paragraphs\ParagraphInterface;
use Psr\Log\LoggerInterface;

/**
 * Derives and writes the Paragraphs List index rows.
 */
final class ListIndexer implements ListIndexerInterface {

  use ListIndexValuesTrait;

  /**
   * Longest chain walked upwards; a guard against a parent cycle.
   */
  private const MAX_DEPTH = 64;

  /**
   * Characters of the label kept.
   *
   * The column is exactly this long so its index can serve ORDER BY; see
   * paragraphs_bundles_schema().
   */
  private const LABEL_LENGTH = 191;

  /**
   * The paragraph base table, walked by primary-key range.
   */
  private const PARAGRAPH_TABLE = 'paragraphs_item';

  /**
   * Rows written per statement.
   */
  private const WRITE_CHUNK = 200;

  /**
   * Whether the table was seen to exist (only TRUE is remembered).
   */
  private bool $available = FALSE;

  /**
   * Host check results keyed by "entity_type:bundle".
   *
   * @var array<string, bool>
   */
  private array $hostCache = [];

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly ThemeManagerInterface $themeManager,
    private readonly ThemeInitializationInterface $themeInitialization,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isAvailable(): bool {
    if ($this->available) {
      return TRUE;
    }
    $this->available = $this->database->schema()->tableExists(self::TABLE);
    return $this->available;
  }

  /**
   * {@inheritdoc}
   */
  public function hostHasParagraphs(ContentEntityInterface $entity): bool {
    $key = $entity->getEntityTypeId() . ':' . $entity->bundle();
    if (!isset($this->hostCache[$key])) {
      $found = FALSE;
      foreach ($this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle()) as $definition) {
        if ($definition->getType() === 'entity_reference_revisions' && $definition->getSetting('target_type') === 'paragraph') {
          $found = TRUE;
          break;
        }
      }
      $this->hostCache[$key] = $found;
    }
    return $this->hostCache[$key];
  }

  /**
   * {@inheritdoc}
   */
  public function indexHost(ContentEntityInterface $host): void {
    if (!$this->isAvailable() || $host->id() === NULL) {
      return;
    }
    $root_type = $host->getEntityTypeId();
    $root_id = (int) $host->id();

    $rows = $this->walkDown($root_type, [(string) $root_id], $root_type, $root_id, 1, $this->rootTitle($host));

    // Rows that used to belong to this host and no longer do: a paragraph
    // moved elsewhere. Deleted paragraphs removed their own rows already.
    $existing = self::intColumn($this->database->select(self::TABLE, 'i')
      ->fields('i', ['pid'])
      ->condition('root_type', $root_type)
      ->condition('root_id', $root_id));
    $stale = array_diff($existing, array_keys($rows));

    $this->write($rows, array_values($stale));
  }

  /**
   * {@inheritdoc}
   */
  public function indexParagraph(ParagraphInterface $paragraph): void {
    if (!$this->isAvailable() || $paragraph->id() === NULL) {
      return;
    }
    $row = $this->deriveRow($paragraph);
    $rows = [$row['pid'] => $row];
    if ($row['root_type'] !== '') {
      $rows += $this->walkDown('paragraph', [(string) $row['pid']], $row['root_type'], $row['root_id'], $row['depth'] + 1, $row['root_title']);
    }
    else {
      // An orphan chain has no root; its descendants are orphans too.
      $rows += $this->walkDown('paragraph', [(string) $row['pid']], '', 0, 0, '');
    }
    $this->write($rows, []);
  }

  /**
   * {@inheritdoc}
   */
  public function removeParagraph(int $pid): void {
    if (!$this->isAvailable()) {
      return;
    }
    $this->database->delete(self::TABLE)->condition('pid', $pid)->execute();
    $this->write($this->walkDown('paragraph', [(string) $pid], '', 0, 0, ''), []);
    Cache::invalidateTags([self::CACHE_TAG]);
  }

  /**
   * {@inheritdoc}
   */
  public function indexDeletedHost(string $entity_type_id, int $entity_id): void {
    if (!$this->isAvailable()) {
      return;
    }
    $pids = self::intColumn($this->database->select(self::TABLE, 'i')
      ->fields('i', ['pid'])
      ->condition('root_type', $entity_type_id)
      ->condition('root_id', $entity_id));
    $storage = $this->storage();
    foreach (array_chunk($pids, 200) as $chunk) {
      $rows = [];
      foreach ($storage->loadMultiple($chunk) as $paragraph) {
        if ($paragraph instanceof ParagraphInterface) {
          $row = $this->deriveRow($paragraph);
          $rows[$row['pid']] = $row;
        }
      }
      // A row whose paragraph is already gone has nothing to derive.
      $gone = array_diff($chunk, array_keys($rows));
      $this->write($rows, array_values($gone));
      $storage->resetCache($chunk);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function rebuildChunk(int $after, int $limit): ?int {
    if (!$this->isAvailable()) {
      return NULL;
    }
    $storage = $this->storage();
    $ids = $this->idsAfter($after, $limit);
    if ($ids === []) {
      return NULL;
    }
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $paragraph) {
      if ($paragraph instanceof ParagraphInterface) {
        $row = $this->deriveRow($paragraph);
        $rows[$row['pid']] = $row;
      }
    }
    $this->write($rows, []);
    $storage->resetCache($ids);
    return (int) max(array_map('intval', $ids));
  }

  /**
   * {@inheritdoc}
   */
  public function pruneMissing(): int {
    if (!$this->isAvailable()) {
      return 0;
    }
    $after = 0;
    $removed = 0;
    while (TRUE) {
      $pids = self::intColumn($this->database->select(self::TABLE, 'i')
        ->fields('i', ['pid'])
        ->condition('pid', $after, '>')
        ->orderBy('pid')
        ->range(0, 500));
      if ($pids === []) {
        break;
      }
      $after = max($pids);
      $present = $this->existingIds($pids);
      $gone = array_values(array_diff($pids, $present));
      if ($gone !== []) {
        $this->database->delete(self::TABLE)->condition('pid', $gone, 'IN')->execute();
        $removed += count($gone);
      }
    }
    if ($removed > 0) {
      Cache::invalidateTags([self::CACHE_TAG]);
    }
    return $removed;
  }

  /**
   * {@inheritdoc}
   */
  public function verify(?int $sample = 200): array {
    $mismatches = [];
    $checked = 0;

    if ($sample === NULL) {
      // Every paragraph, a keyset chunk at a time, so memory stays flat.
      $after = 0;
      while (($chunk = $this->idsAfter($after, 100)) !== []) {
        $after = max($chunk);
        $this->verifyChunk($chunk, $checked, $mismatches);
      }
    }
    else {
      foreach (array_chunk($this->sampleIds($sample), 100) as $chunk) {
        $this->verifyChunk($chunk, $checked, $mismatches);
      }
    }

    // Rows whose paragraph no longer exists.
    $after = 0;
    while (TRUE) {
      $extra_query = $this->database->select(self::TABLE, 'i')
        ->fields('i', ['pid'])
        ->condition('pid', $after, '>')
        ->orderBy('pid')
        ->range(0, $sample === NULL ? 500 : max($sample, 1));
      $indexed = self::intColumn($extra_query);
      if ($indexed === []) {
        break;
      }
      $after = max($indexed);
      $present = $this->existingIds($indexed);
      foreach (array_diff($indexed, $present) as $pid) {
        $mismatches[] = sprintf('pid %d: in the index but the paragraph does not exist', $pid);
      }
      if ($sample !== NULL) {
        break;
      }
    }

    return ['checked' => $checked, 'mismatches' => $mismatches];
  }

  /**
   * Compares one chunk of paragraphs with their stored rows.
   *
   * @param list<int> $chunk
   *   Paragraph ids.
   * @param int $checked
   *   Running count of paragraphs checked.
   * @param list<string> $mismatches
   *   Running list of mismatch lines.
   */
  private function verifyChunk(array $chunk, int &$checked, array &$mismatches): void {
    $storage = $this->storage();
    $stored = [];
    foreach (self::fetchRows($this->database->select(self::TABLE, 'i')->fields('i')->condition('pid', $chunk, 'IN')) as $record) {
      $stored[self::toInt($record['pid'] ?? 0)] = $record;
    }
    foreach ($storage->loadMultiple($chunk) as $paragraph) {
      if (!$paragraph instanceof ParagraphInterface) {
        continue;
      }
      $checked++;
      $expected = $this->deriveRow($paragraph);
      $actual = $stored[$expected['pid']] ?? NULL;
      if ($actual === NULL) {
        $mismatches[] = sprintf('pid %d: missing from the index', $expected['pid']);
        continue;
      }
      foreach ($expected as $column => $value) {
        $actual_value = self::toString($actual[$column] ?? '');
        if ($actual_value !== (string) $value) {
          $mismatches[] = sprintf('pid %d: %s is "%s", expected "%s"', $expected['pid'], $column, $actual_value, (string) $value);
        }
      }
    }
    $storage->resetCache($chunk);
  }

  /**
   * Returns the next paragraph ids above a cursor, by primary-key range.
   *
   * A plain range on the base table, NOT an entity query: the entity query
   * joins the data table and groups, which makes MySQL scan every row after
   * the cursor on every chunk (measured: 10.7 s per 200 on a million rows).
   * This is internal index maintenance, not an access-controlled listing, so
   * no access check is lost.
   *
   * @return list<int>
   *   Up to $limit ids, ascending.
   */
  private function idsAfter(int $after, int $limit): array {
    return self::intColumn($this->database->select(self::PARAGRAPH_TABLE, 'p')
      ->fields('p', ['id'])
      ->condition('p.id', $after, '>')
      ->orderBy('p.id')
      ->range(0, $limit));
  }

  /**
   * Returns which of the given ids are paragraphs, by primary-key lookup.
   *
   * Plain select on the base table for the same reason as idsAfter(); no
   * access check, this is index maintenance.
   *
   * @param list<int> $ids
   *   Candidate paragraph ids.
   *
   * @return list<int>
   *   The ids that exist.
   */
  private function existingIds(array $ids): array {
    if ($ids === []) {
      return [];
    }
    return self::intColumn($this->database->select(self::PARAGRAPH_TABLE, 'p')
      ->fields('p', ['id'])
      ->condition('p.id', $ids, 'IN'));
  }

  /**
   * Picks paragraph ids spread across the whole id range.
   *
   * @return list<int>
   *   Up to $sample ids.
   */
  private function sampleIds(int $sample): array {
    $sample = max($sample, 1);
    $bounds = $this->database->query('SELECT MIN(id) AS lo, MAX(id) AS hi FROM {paragraphs_item}')?->fetchAssoc();
    if (!is_array($bounds) || $bounds['hi'] === NULL) {
      return [];
    }
    $low = self::toInt($bounds['lo']);
    $high = self::toInt($bounds['hi']);
    $ids = [];
    $slots = min($sample, 20);
    $per_slot = max((int) ceil($sample / $slots), 1);
    for ($slot = 0; $slot < $slots; $slot++) {
      $start = $low + (int) floor(($high - $low) * $slot / $slots);
      $found = self::intColumn($this->database->select(self::PARAGRAPH_TABLE, 'p')
        ->fields('p', ['id'])
        ->condition('p.id', $start, '>=')
        ->orderBy('p.id')
        ->range(0, $per_slot));
      foreach ($found as $id) {
        $ids[$id] = $id;
      }
    }
    return array_slice(array_values($ids), 0, $sample);
  }

  /**
   * Walks down the parent columns, level by level, deriving each row.
   *
   * @param string $parent_type
   *   The entity type of the parents of the first level.
   * @param list<string> $parent_ids
   *   The parent ids of the first level.
   * @param string $root_type
   *   The root entity type recorded on every row.
   * @param int $root_id
   *   The root entity id recorded on every row.
   * @param int $depth
   *   The depth of the first level.
   * @param string $root_title
   *   The root entity's default-language label recorded on every row.
   *
   * @return array<int, array{pid: int, bundle: string, root_type: string, root_id: int, depth: int, langcode: string, label: string, root_title: string}>
   *   Rows keyed by paragraph id.
   */
  private function walkDown(string $parent_type, array $parent_ids, string $root_type, int $root_id, int $depth, string $root_title): array {
    $storage = $this->storage();
    $rows = [];
    $seen = [];
    $level = 0;
    while ($parent_ids !== [] && $level < self::MAX_DEPTH) {
      $ids = [];
      foreach (array_chunk($parent_ids, 200) as $chunk) {
        // Entity query kept on purpose: it is served by the indexed parent
        // columns and bounded by one host's tree, not an id-range walk.
        $found = $storage->getQuery()
          ->accessCheck(FALSE)
          ->condition('parent_type', $parent_type)
          ->condition('parent_id', $chunk, 'IN')
          ->execute();
        foreach ($found as $id) {
          if (!isset($seen[(int) $id])) {
            $seen[(int) $id] = TRUE;
            $ids[] = (int) $id;
          }
        }
      }
      $parent_ids = [];
      foreach (array_chunk($ids, 200) as $chunk) {
        foreach ($storage->loadMultiple($chunk) as $paragraph) {
          if (!$paragraph instanceof ParagraphInterface) {
            continue;
          }
          $rows[(int) $paragraph->id()] = $this->buildRow($paragraph, $root_type, $root_id, $depth + $level, $root_title);
          $parent_ids[] = (string) $paragraph->id();
        }
        $storage->resetCache($chunk);
      }
      // Below the first level every parent is a paragraph.
      $parent_type = 'paragraph';
      $level++;
    }
    return $rows;
  }

  /**
   * Derives one paragraph's row by walking UP to its root.
   *
   * @return array{pid: int, bundle: string, root_type: string, root_id: int, depth: int, langcode: string, label: string, root_title: string}
   *   The row.
   */
  private function deriveRow(ParagraphInterface $paragraph): array {
    $storage = $this->storage();
    $depth = 1;
    $current = $paragraph;
    $root_type = '';
    $root_id = 0;
    $root_title = '';
    for ($i = 0; $i < self::MAX_DEPTH; $i++) {
      $parent_type = $current->get('parent_type')->value;
      $parent_id = self::toString($current->get('parent_id')->value);
      if (!is_string($parent_type) || $parent_type === '' || $parent_id === '') {
        break;
      }
      if ($parent_type === 'paragraph') {
        $parent = $storage->load($parent_id);
        if (!$parent instanceof ParagraphInterface) {
          break;
        }
        $current = $parent;
        $depth++;
        continue;
      }
      $root = $this->entityTypeManager->hasDefinition($parent_type)
        ? $this->entityTypeManager->getStorage($parent_type)->load($parent_id)
        : NULL;
      if ($root !== NULL) {
        $root_type = $parent_type;
        $root_id = (int) $parent_id;
        $root_title = $this->rootTitle($root);
      }
      break;
    }
    if ($root_type === '') {
      $depth = 0;
    }
    return $this->buildRow($paragraph, $root_type, $root_id, $depth, $root_title);
  }

  /**
   * Builds one row.
   *
   * @return array{pid: int, bundle: string, root_type: string, root_id: int, depth: int, langcode: string, label: string, root_title: string}
   *   The row.
   */
  private function buildRow(ParagraphInterface $paragraph, string $root_type, int $root_id, int $depth, string $root_title): array {
    return [
      'pid' => (int) $paragraph->id(),
      'bundle' => (string) $paragraph->bundle(),
      'root_type' => $root_type,
      'root_id' => $root_id,
      'depth' => $root_type === '' ? 0 : min($depth, 255),
      'langcode' => $paragraph->getUntranslated()->language()->getId(),
      'label' => mb_substr($this->labelOf($paragraph->getUntranslated()), 0, self::LABEL_LENGTH),
      'root_title' => $root_type === '' ? '' : $root_title,
    ];
  }

  /**
   * The root entity's label in its DEFAULT translation, cut to the column size.
   *
   * Derived copy: its source is the root's own label(); it is what the list
   * sorts and searches "Content title" by, so the page needs no join to the
   * root entity. Any save of the root rebuilds its whole tree, so a rename
   * reaches every row.
   */
  private function rootTitle(EntityInterface $root): string {
    if ($root instanceof ContentEntityInterface) {
      $root = $root->getUntranslated();
    }
    return mb_substr((string) $root->label(), 0, self::LABEL_LENGTH);
  }

  /**
   * Returns Paragraph::label(), or a stand-in if computing it throws.
   *
   * A paragraph whose type was deleted, for one, makes label() fail. An index
   * write runs inside a save, so one broken paragraph must not stop an editor
   * saving a page; it is logged and shown as a stand-in the page can search.
   */
  private function labelOf(ParagraphInterface $paragraph): string {
    $previous = $this->enterRenderTheme();
    try {
      return (string) $paragraph->label();
    }
    catch (\Throwable $e) {
      $this->logger->warning('Paragraphs List index: could not compute the label of paragraph @id: @message', [
        '@id' => $paragraph->id(),
        '@message' => $e->getMessage(),
      ]);
      return '(label unavailable) paragraph ' . $paragraph->id();
    }
    finally {
      $this->leaveRenderTheme($previous);
    }
  }

  /**
   * Writes rows and removes stale ones in one transaction.
   *
   * @param array<int, array{pid: int, bundle: string, root_type: string, root_id: int, depth: int, langcode: string, label: string, root_title: string}> $rows
   *   Rows keyed by paragraph id.
   * @param list<int> $remove
   *   Paragraph ids whose rows are deleted.
   */
  private function write(array $rows, array $remove): void {
    $transaction = $this->database->startTransaction();
    try {
      foreach (array_chunk($remove, self::WRITE_CHUNK) as $chunk) {
        $this->database->delete(self::TABLE)->condition('pid', $chunk, 'IN')->execute();
      }
      foreach (array_chunk($rows, self::WRITE_CHUNK, TRUE) as $chunk) {
        $this->database->delete(self::TABLE)->condition('pid', array_keys($chunk), 'IN')->execute();
        $insert = $this->database->insert(self::TABLE)
          ->fields(['pid', 'bundle', 'root_type', 'root_id', 'depth', 'langcode', 'label', 'root_title']);
        foreach ($chunk as $row) {
          $insert->values($row);
        }
        $insert->execute();
      }
    }
    catch (\Throwable $e) {
      $transaction->rollBack();
      throw $e;
    }
    unset($transaction);
    Cache::invalidateTags([self::CACHE_TAG]);
  }

  /**
   * Activates the default theme for the duration of a label computation.
   *
   * Paragraph::label() renders the paragraph summary through a theme hook. A
   * command-line process (drush, cron) has either no active theme or, during
   * database updates, a maintenance theme whose registry lacks that hook, so
   * the summary comes out empty and the same orphan would get a different
   * label than on a web request. A web request already has a real theme and is
   * left alone: switching theme mid-request would change how the rest of that
   * page renders.
   *
   * @return \Drupal\Core\Theme\ActiveTheme|null|false
   *   The theme to put back afterwards, NULL when there was none, or FALSE when
   *   nothing was switched.
   */
  private function enterRenderTheme(): mixed {
    if (PHP_SAPI !== 'cli') {
      return FALSE;
    }
    $default = $this->configFactory->get('system.theme')->get('default');
    if (!is_string($default) || $default === '') {
      return FALSE;
    }
    $previous = $this->themeManager->hasActiveTheme() ? $this->themeManager->getActiveTheme() : NULL;
    if ($previous !== NULL && $previous->getName() === $default) {
      return FALSE;
    }
    $this->themeManager->setActiveTheme($this->themeInitialization->initTheme($default));
    return $previous;
  }

  /**
   * Puts back the theme that enterRenderTheme() replaced.
   *
   * @param mixed $previous
   *   What enterRenderTheme() returned.
   */
  private function leaveRenderTheme(mixed $previous): void {
    if ($previous instanceof ActiveTheme) {
      $this->themeManager->setActiveTheme($previous);
    }
  }

  /**
   * The paragraph storage.
   */
  private function storage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('paragraph');
  }

}
