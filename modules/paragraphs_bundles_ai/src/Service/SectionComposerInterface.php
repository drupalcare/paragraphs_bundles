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
   * @param list<array<string, mixed>>|array<string, list<array<string, mixed>>> $children
   *   Child sections, in either of two shapes. Empty when the section holds
   *   nothing.
   *
   *   A LIST — every child goes in one field. This is what every caller used
   *   before slots were supported, and it behaves exactly as it always did:
   *   @code
   *   [['bundle' => 'slideshow_section_bundle', 'values' => [...]], ...]
   *   @endcode
   *
   *   A MAP KEYED BY FIELD — for a section that holds sections in more than
   *   one place. Two columns has a left and a right, a flip box has a front
   *   and a back, a modal has a body and a footer, and "a Simple in each"
   *   names two different places:
   *   @code
   *   ['pb_content_left_column'  => [['bundle' => 'simple_bundle', ...]],
   *    'pb_content_right_column' => [['bundle' => 'image_bundle', ...]]]
   *   @endcode
   *
   *   EACH SLOT TAKES AS MANY AS IT IS GIVEN. A slot is an ordinary paragraph
   *   field, so a column holds however many sections the person wants — not
   *   one — subject to the field's own cardinality.
   * @param string|null $childField
   *   Which field a LIST goes in. Ignored when $children is keyed by field,
   *   because the keys have already said. Still required for a list when the
   *   section has more than one slot, since a bare list has not said where.
   * @param string $title
   *   Title for a new page. Required when $nodeId is NULL.
   * @param int|null $nodeId
   *   Page to add to, or NULL to create a new unpublished one.
   * @param array<string, mixed> $styling
   *   Display-field values from a NAMED PRESET, already checked against this
   *   bundle by StylePresets.
   *
   *   These are the one exception to "styling is refused". The refusal exists
   *   because a model choosing colours produces a section nobody checked; a
   *   preset is colours a PERSON chose and checked once, so applying them is
   *   repeating a human decision rather than making a machine one. They
   *   therefore arrive in their own parameter — never mixed into $values,
   *   where a model's answer lives.
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
    array $styling = [],
  ): CreationResult;

}
