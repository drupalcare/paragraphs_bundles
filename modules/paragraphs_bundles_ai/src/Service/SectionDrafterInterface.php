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

  /**
   * Rewrites the words in a section that already exists.
   *
   * The current values go to the model so it can change what was asked and
   * leave the rest alone. It returns only the fields it wants changed — a
   * rewrite of a title is not permission to invent an alignment.
   *
   * @param string $bundle
   *   Paragraph bundle machine name of the section being changed.
   * @param array<string, string> $current
   *   Field machine name => what the section says now.
   * @param string $words
   *   What the person asked for.
   *
   * @return \Drupal\paragraphs_bundles_ai\Model\DraftedSection
   *   The proposal, or why there is none. Children are never proposed here:
   *   changing the structure of an existing section is not this feature.
   */
  public function draftEdit(string $bundle, array $current, string $words): DraftedSection;

  /**
   * Writes the words for a list of text places another module has laid out.
   *
   * For a section source (SectionSourceInterface) that builds its own
   * structure and needs only the words: one request, one answer per place,
   * in the same order. The provider and model are the ones this module uses.
   *
   * @param string $about
   *   What is being written, for the model, for example
   *   "a 3D carousel of quote cards".
   * @param list<array{label: string, hint: string, max: int}> $places
   *   Each text place: its field label, a hint such as "Under 60 words", and
   *   its character limit (0 for none).
   * @param string $words
   *   What the person said it should say.
   *
   * @return list<string>|\Stringable
   *   One text per place, in order (an empty string where the model left one
   *   out), or a sentence saying why there are none.
   */
  public function draftTexts(string $about, array $places, string $words): array|\Stringable;

}
