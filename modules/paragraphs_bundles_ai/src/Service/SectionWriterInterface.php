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

  /**
   * Runs every precondition, then attaches paragraphs somebody else built.
   *
   * WHY A CALLBACK RATHER THAN A LIST OF PARAGRAPHS. The paragraphs have to be
   * saved before they can be referenced, and a refusal after saving them would
   * leave orphans behind — saved, parentless, and invisible to the person who
   * asked. So the builder runs only once every check has passed, and a refusal
   * creates nothing at all.
   *
   * This is the one place that knows the three rules: unpublished new pages, a
   * new revision on an existing one, and appending unless told to replace.
   * Anything that composes richer sections comes through here rather than
   * repeating them.
   *
   * @param string|null $contentType
   *   Node bundle. Ignored when $nodeId is given.
   * @param string|null $field
   *   Paragraph field name, or NULL to let the resolver choose.
   * @param string $bundle
   *   Paragraph bundle being attached, for the checks and the messages.
   * @param int $count
   *   How many the builder will return. Checked against cardinality first.
   * @param callable $build
   *   Returns the saved paragraphs as a list of
   *   ['target_id' => int, 'target_revision_id' => int]. Called only after
   *   every precondition passes.
   * @param string $title
   *   Title for a new page. Required when $nodeId is NULL.
   * @param int|null $nodeId
   *   An existing node, or NULL to create a new unpublished draft.
   * @param bool $replace
   *   TRUE to replace rather than append. Never inferred.
   * @param bool $empty
   *   Whether the builder produces EMPTY sections. It decides one note, and
   *   the note is the whole point: "the new sections are empty — fill them in"
   *   is true of pb_create_sections and false of anything that composes
   *   content, and a caller told it about a section it just filled learns to
   *   distrust the notes.
   *
   * @return \Drupal\paragraphs_bundles_ai\Model\CreationResult
   *   What was written, or why nothing was.
   */
  public function attach(
    ?string $contentType,
    ?string $field,
    string $bundle,
    int $count,
    callable $build,
    string $title = '',
    ?int $nodeId = NULL,
    bool $replace = FALSE,
    bool $empty = TRUE,
  ): CreationResult;

}
