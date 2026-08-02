<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Field;

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Resolves absolute URLs for image and media reference fields.
 */
interface PbImageFieldUrlResolverInterface {

  /**
   * Returns an absolute URL for a file-backed image field.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Fieldable entity (paragraph or node).
   * @param string $field
   *   Image or media reference field machine name.
   *
   * @return string
   *   Absolute URL, or empty when the field has no usable image.
   */
  public function absoluteUrl(
    FieldableEntityInterface $entity,
    string $field,
  ): string;

}
