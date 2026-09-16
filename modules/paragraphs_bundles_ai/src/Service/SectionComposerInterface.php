<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\paragraphs_bundles_ai\Model\CreationResult;

/**
 * Builds a section with its content, and its children, in one call.
 *
 * WHAT THIS ADDS TO SectionWriter. That one creates EMPTY sections: a person
 * asks for "a slideshow with three slides about baby food" and gets an empty
 * slideshow, no slides and no words. This puts the values in and nests the
 * children, which is the difference between a tool that demonstrates and a
 * tool that helps.
 *
 * IT CANNOT TOUCH ANYTHING THAT EXISTS. There is no paragraph id in this
 * interface at all: it creates new sections and fills those. Rewriting an
 * existing section is not a feature that was left out, it is one that must not
 * exist — see the add-only rule in the design.
 */
interface SectionComposerInterface {

  /**
   * Creates one section, fills it, and nests its children.
   *
   * @param string|null $contentType
   *   Node bundle. Ignored when $nodeId is given.
   * @param string|null $field
   *   Paragraph field on the node, or NULL when the type has exactly one.
   * @param string $bundle
   *   Paragraph bundle to create.
   * @param array<string, mixed> $values
   *   Field machine name => value, for the section itself. Unknown fields,
   *   styling fields and values outside a list field's keys are refused by
   *   name rather than dropped.
   * @param list<array<string, mixed>> $children
   *   Child sections, each ['bundle' => string, 'values' => array]. Empty when
   *   the section holds nothing.
   * @param string|null $childField
   *   Which field the children go in. Required only when the section has more
   *   than one — two_columns has a left and a right, and guessing is worse
   *   than asking.
   * @param string $title
   *   Title for a new page. Required when $nodeId is NULL.
   * @param int|null $nodeId
   *   Page to add to, or NULL to create a new unpublished one.
   *
   * @return \Drupal\paragraphs_bundles_ai\Model\CreationResult
   *   What was written, or why nothing was.
   */
  public function compose(
    ?string $contentType,
    ?string $field,
    string $bundle,
    array $values = [],
    array $children = [],
    ?string $childField = NULL,
    string $title = '',
    ?int $nodeId = NULL,
  ): CreationResult;

}
