<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Field;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\paragraphs\ParagraphInterface;

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

  /**
   * Returns the URL of an image field in the style its Pattern names.
   *
   * A paragraph whose nearest Pattern names an image style for the field gets
   * that style's derivative URL; any other paragraph, or a file the style
   * cannot process, gets exactly absoluteUrl().
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph that holds the field.
   * @param string $field
   *   Image field machine name.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects the parent paragraphs read and the style's config tags.
   *
   * @return string
   *   Absolute URL, or empty when the field has no usable image.
   */
  public function patternUrl(
    ParagraphInterface $paragraph,
    string $field,
    CacheableMetadata $cacheability,
  ): string;

}
