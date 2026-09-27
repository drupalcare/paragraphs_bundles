<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

/**
 * Says what goes inside a section: which slots, and which bundles in each.
 *
 * ONE OWNER FOR THE QUESTION, asked by both the form and the drafter. The
 * form uses it to decide whether "How many" means anything; the drafter uses
 * it to decide what the model is asked to write. When those two asked
 * different questions, sections were created empty and reported as done.
 */
interface ChildSlotsInterface {

  /**
   * The slots of a section that can actually be filled.
   *
   * A slot is fillable when the field itself names one bundle, or when the
   * briefs' `nesting` list says what belongs in it. A slot that accepts
   * anything and has no nesting entry is left out: the site has never said
   * what goes there, and guessing is how empty shells were built.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   *
   * @return list<\Drupal\paragraphs_bundles_ai\Model\ChildSlot>
   *   The fillable slots, in field order. Empty when there are none.
   */
  public function slotsFor(string $bundle): array;

  /**
   * Whether "How many" applies to this section.
   *
   * It does when the section has exactly one fillable slot: slides, rows,
   * carousel items. A section with several slots — two columns, the front and
   * back of a flip box — gets one section in each, so a count would not say
   * where anything goes.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   *
   * @return bool
   *   TRUE when a count can be acted on.
   */
  public function countable(string $bundle): bool;

}
