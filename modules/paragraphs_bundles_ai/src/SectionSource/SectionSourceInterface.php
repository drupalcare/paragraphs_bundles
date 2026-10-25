<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\SectionSource;

use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs_bundles_ai\Model\CreationResult;

/**
 * Offers ready-made sections in the AI bar, beside the site's own bundles.
 *
 * Another module (for example one that ships designed section patterns)
 * implements this and tags its service `paragraphs_bundles_ai.section_source`.
 * Its items appear in the bar's "Add section" list under their own group.
 * When the person picks one and says what it should say, create() builds the
 * section, asks the model for the words through
 * SectionDrafterInterface::draftTexts(), and saves it through
 * SectionWriterInterface::attach(), which keeps this module's rules: a new
 * revision, appended, with undo.
 *
 * With no tagged service the bar is exactly as before.
 */
interface SectionSourceInterface {

  /**
   * The source's machine name, unique among sources.
   *
   * @return string
   *   Lower-case letters, digits and underscores only.
   */
  public function id(): string;

  /**
   * The group heading the items are listed under.
   *
   * @return \Stringable|string
   *   For example "Patterns".
   */
  public function label(): \Stringable|string;

  /**
   * The sentence this source adds to the bar's introduction.
   *
   * Said only when the source offers something on the page.
   *
   * @return \Stringable|string
   *   For example "Patterns are ready-made sections: their layout and
   *   settings are designed, and the AI writes the words."
   */
  public function description(): \Stringable|string;

  /**
   * What this source offers for one paragraph field of one page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The page.
   * @param string $field
   *   The paragraph field on it.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The person using the bar.
   *
   * @return array<string, array{label: \Stringable|string, hint: \Stringable|string}>
   *   Items keyed by the source's own key: the name in the list, and the hint
   *   shown in the "What should it say?" box.
   */
  public function options(NodeInterface $node, string $field, AccountInterface $account): array;

  /**
   * Builds, fills and saves one item on the page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The page.
   * @param string $field
   *   The paragraph field it goes in; options() offered the key for it.
   * @param string $key
   *   The item's key from options().
   * @param string $words
   *   What the person said it should say.
   *
   * @return \Drupal\paragraphs_bundles_ai\Model\CreationResult
   *   What was written, or why nothing was.
   */
  public function create(NodeInterface $node, string $field, string $key, string $words): CreationResult;

}
