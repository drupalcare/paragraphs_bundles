<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\paragraphs_bundles_ai\Model\DraftableField;
use Drupal\paragraphs_bundles_ai\Model\FieldDraft;

/**
 * Knows what a paragraph section can hold, and fits text to it.
 *
 * Reads only. Nothing in this service saves an entity, and nothing in it
 * touches configuration — a field's limits are the site builder's decision and
 * this reports them rather than working around them.
 *
 * @see \Drupal\paragraphs_bundles_ai\Service\FieldDrafter
 * @see docs/PB-AI-BRIEF.md §5
 */
interface FieldDrafterInterface {

  /**
   * The fields on a bundle a person could write text into.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   *
   * @return array<string, \Drupal\paragraphs_bundles_ai\Model\DraftableField>|null
   *   Field name => description, or NULL when there is no such bundle. NULL
   *   and [] mean different things and the caller must say which happened: an
   *   unknown bundle is a typo or a disabled module, a bundle with no text
   *   fields is a section that simply holds something else.
   */
  public function draftableFields(string $bundle): ?array;

  /**
   * Fits a draft to a field's real constraints.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   * @param string $field
   *   Field machine name on that bundle.
   * @param string $draft
   *   The text to fit.
   *
   * @return \Drupal\paragraphs_bundles_ai\Model\FieldDraft
   *   The fitted text, or why there is none.
   */
  public function fit(string $bundle, string $field, string $draft): FieldDraft;

}
