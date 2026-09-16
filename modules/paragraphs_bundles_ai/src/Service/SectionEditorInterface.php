<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\node\NodeInterface;
use Drupal\paragraphs_bundles_ai\Model\EditOutcome;

/**
 * Changes the words in one section a person pointed at.
 *
 * WHAT MAKES THIS SAFE, given that editing was ruled out for weeks: the target
 * is a paragraph id the person chose from a dropdown, the section must already
 * be on the page they are looking at, only text fields are touched, and the
 * change lands as a new node revision with its number returned for undo.
 *
 * WHAT IT WILL NOT DO, ever: change which bundle a section is, add or remove
 * children, touch a styling field, or edit anything that is not on this page.
 */
interface SectionEditorInterface {

  /**
   * Writes new values into a section that is already on a page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The page the section is on.
   * @param string $field
   *   The paragraph field that holds it.
   * @param int $paragraphId
   *   The section, as chosen from the Edit dropdown.
   * @param array<string, mixed> $values
   *   Field machine name => new value.
   *
   * @return \Drupal\paragraphs_bundles_ai\Model\EditOutcome
   *   What changed and what it was, or why nothing did.
   */
  public function edit(NodeInterface $node, string $field, int $paragraphId, array $values): EditOutcome;

  /**
   * The values a section holds now, for the model to rewrite.
   *
   * @param int $paragraphId
   *   The section.
   *
   * @return array<string, string>
   *   Field machine name => current value, text fields only.
   */
  public function currentValues(int $paragraphId): array;

}
