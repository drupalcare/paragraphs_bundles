<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\ListIndex;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\Statement\FetchAs;

/**
 * Typed reads of database results, shared by the index classes.
 *
 * The database layer hands back mixed values and a nullable statement; these
 * turn them into the scalars the index code works with, in one place.
 */
trait ListIndexValuesTrait {

  /**
   * Fetches every row of a select as an associative array.
   *
   * @return list<array<string, mixed>>
   *   The rows.
   */
  private static function fetchRows(SelectInterface $query): array {
    // A SELECT always yields a statement; NULL is only for other query kinds.
    $statement = $query->execute();
    $rows = [];
    foreach ($statement?->fetchAll(FetchAs::Associative) ?? [] as $record) {
      if (is_array($record)) {
        $row = [];
        foreach ($record as $column => $value) {
          $row[(string) $column] = $value;
        }
        $rows[] = $row;
      }
    }
    return $rows;
  }

  /**
   * Fetches the first column of the first row.
   */
  private static function fetchValue(SelectInterface $query): mixed {
    return $query->execute()?->fetchField();
  }

  /**
   * Reads the first column of a select as integers.
   *
   * @return list<int>
   *   The values.
   */
  private static function intColumn(SelectInterface $query): array {
    return array_values(array_map(self::toInt(...), $query->execute()?->fetchCol() ?? []));
  }

  /**
   * Casts a database value to an integer; zero when it is not numeric.
   */
  private static function toInt(mixed $value): int {
    return is_numeric($value) ? (int) $value : 0;
  }

  /**
   * Casts a database value to a string; empty when it is not scalar.
   */
  private static function toString(mixed $value): string {
    return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
  }

}
