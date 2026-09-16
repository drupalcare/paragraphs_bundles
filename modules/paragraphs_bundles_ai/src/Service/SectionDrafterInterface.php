<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\paragraphs_bundles_ai\Model\DraftedSection;

/**
 * Asks the model for one section's content, inside a schema it cannot leave.
 *
 * The model is never asked WHICH section to build or WHERE to put it — the
 * person answered both with a dropdown. It is asked only for words, and the
 * shape of its answer is defined by the bundle's own fields.
 */
interface SectionDrafterInterface {

  /**
   * Drafts the content for one section.
   *
   * @param string $bundle
   *   Paragraph bundle machine name, from the dropdown.
   * @param string $words
   *   What the person typed.
   * @param int $children
   *   How many child sections to draft, when the bundle takes them.
   *
   * @return \Drupal\paragraphs_bundles_ai\Model\DraftedSection
   *   The proposal, or why there is none.
   */
  public function draft(string $bundle, string $words, int $children = 0): DraftedSection;

}
