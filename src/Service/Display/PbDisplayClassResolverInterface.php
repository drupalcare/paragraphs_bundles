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

}
