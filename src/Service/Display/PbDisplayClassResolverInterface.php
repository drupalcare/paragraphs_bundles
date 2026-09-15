<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Display;

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Resolves stored display-field values into the CSS classes templates emit.
 *
 * The single authority on "stored value → class". Both the `pb_class` Twig
 * filter and the preprocess hook that builds a paragraph's inner-wrapper
 * classes go through here.
 */
interface PbDisplayClassResolverInterface {

  /**
   * Maps one stored display value to its BEM class.
   *
   * @param string $value
   *   Stored field value (a v1 class token).
   *
   * @return string
   *   BEM class, or the value verbatim when it is not in the map.
   */
  public function className(string $value): string;

  /**
   * Returns the inner-wrapper display classes for a paragraph, in order.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $paragraph
   *   The paragraph being rendered.
   *
   * @return list<string>
   *   Classes for the fields this bundle places on its inner wrapper, in the
   *   bundle's declared field order, with empty values omitted.
   */
  public function innerClasses(FieldableEntityInterface $paragraph): array;

  /**
   * Returns the author-supplied custom classes for a paragraph, sanitised.
   *
   * Reads `pb_display_css_classes` - a free-text field whose whole purpose is
   * to let a site style every instance of a bundle at once. The value is
   * author input destined for a class attribute, so it is filtered here
   * rather than in Twig: one testable home, and no template can forget it.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $paragraph
   *   The paragraph being rendered.
   *
   * @return list<string>
   *   Accepted class tokens in author order, de-duplicated. Empty when the
   *   bundle has no such field, the field is empty, or nothing survived.
   */
  public function customClasses(FieldableEntityInterface $paragraph): array;

  /**
   * Splits a raw CSS-classes value into what is kept and what is refused.
   *
   * The single home for the rule, so the validator that WARNS an author and
   * the preprocess hook that RENDERS can never disagree about what is
   * acceptable. A message saying a class was rejected, on a page that then
   * emits it, would be worse than no message at all.
   *
   * @param string $value
   *   Raw field value as an author typed it.
   *
   * @return array{accepted: list<string>, rejected: list<string>, over_cap: list<string>}
   *   `accepted` are emitted; `rejected` contain a character that is unsafe in
   *   an attribute; `over_cap` are valid but beyond the per-paragraph limit.
   *   All three are de-duplicated and in author order.
   */
  public function filterClassTokens(string $value): array;

}
