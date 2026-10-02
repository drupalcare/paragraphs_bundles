<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\ListIndex;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Keeps the Paragraphs List index true.
 *
 * The index table is DERIVED from the paragraphs' own parent_type / parent_id
 * columns. Nothing edits it by hand; everything here recomputes rows from the
 * paragraphs.
 */
interface ListIndexerInterface {

  /**
   * The index table name.
   */
  public const TABLE = 'paragraphs_bundles_list_index';

  /**
   * The cache tag invalidated whenever the index changes.
   */
  public const CACHE_TAG = 'paragraphs_bundles_list_index';

  /**
   * Whether the table exists; false between a deploy and `drush updb`.
   */
  public function isAvailable(): bool;

  /**
   * Whether the entity can hold paragraphs through a paragraphs field.
   */
  public function hostHasParagraphs(ContentEntityInterface $entity): bool;

  /**
   * Rebuilds the rows of everything beneath a host entity.
   *
   * Walks DOWN the indexed parent columns, so the cost follows the size of
   * that one host's tree and never the size of the table.
   */
  public function indexHost(ContentEntityInterface $host): void;

  /**
   * Rebuilds one paragraph's row and the rows of its own descendants.
   */
  public function indexParagraph(ParagraphInterface $paragraph): void;

  /**
   * Deletes one paragraph's row and re-derives the rows of its descendants.
   *
   * The entity_reference_revisions module deletes a composite child's own
   * children later, from a queue, so until that runs they exist as orphans.
   */
  public function removeParagraph(int $pid): void;

  /**
   * Re-derives every row that was rooted at a host that has been deleted.
   *
   * The host's paragraphs outlive it until entity_reference_revisions' orphan
   * purger deletes them from its queue; until then they are orphans, exactly
   * as Paragraph::label() reports them.
   */
  public function indexDeletedHost(string $entity_type_id, int $entity_id): void;

  /**
   * Rebuilds the rows of the next chunk of paragraphs, in pid order.
   *
   * @param int $after
   *   Only paragraphs with an id above this are processed.
   * @param int $limit
   *   The chunk size.
   *
   * @return int|null
   *   The last paragraph id processed, or NULL when none were left.
   */
  public function rebuildChunk(int $after, int $limit): ?int;

  /**
   * Deletes rows whose paragraph no longer exists; returns how many.
   *
   * A maintenance walk over the whole table in chunks, for the rebuild command.
   */
  public function pruneMissing(): int;

  /**
   * Compares the table with a fresh derivation.
   *
   * @param int|null $sample
   *   How many paragraphs to check, spread across the id range; NULL checks
   *   every paragraph and every row.
   *
   * @return array{checked: int, mismatches: list<string>}
   *   The number of paragraphs checked and one line per mismatch.
   */
  public function verify(?int $sample = 200): array;

}
