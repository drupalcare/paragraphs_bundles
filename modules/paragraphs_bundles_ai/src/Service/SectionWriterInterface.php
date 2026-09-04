<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\paragraphs_bundles_ai\Model\CreationResult;

/**
 * Creates paragraph sections on a new draft, or on an existing page.
 *
 * The only thing in this module that writes. Everything else reports.
 *
 * @see \Drupal\paragraphs_bundles_ai\Service\SectionWriter
 * @see docs/PB-AI-BRIEF.md §5
 */
interface SectionWriterInterface {

  /**
   * Creates sections, re-checking every precondition first.
   *
   * Does not throw for ordinary refusals — a missing title, a field that
   * rejects the bundle and an unknown node are all results, not exceptions,
   * because each is something a person can act on.
   *
   * @param string|null $contentType
   *   Node bundle. Ignored when $nodeId is given: the node is the authority on
   *   its own type, and accepting both would let them disagree.
   * @param string|null $field
   *   Paragraph field name, or NULL to let the resolver choose when the type
   *   has exactly one.
   * @param string $bundle
   *   Paragraph bundle to create.
   * @param int $count
   *   How many. Values below 1 are treated as 1.
   * @param string $title
   *   Title for a new page. Required when $nodeId is NULL.
   * @param int|null $nodeId
   *   An existing node to add to, or NULL to create a new unpublished draft.
   * @param bool $replace
   *   TRUE to replace existing sections instead of appending. Must come from
   *   an explicit instruction, never inferred.
   *
   * @return \Drupal\paragraphs_bundles_ai\Model\CreationResult
   *   What was written, or why nothing was.
   */
  public function write(
    ?string $contentType,
    ?string $field,
    string $bundle,
    int $count = 1,
    string $title = '',
    ?int $nodeId = NULL,
    bool $replace = FALSE,
  ): CreationResult;

}
