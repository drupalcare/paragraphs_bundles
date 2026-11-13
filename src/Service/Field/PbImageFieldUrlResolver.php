<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Field;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\media\MediaInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_bundles\Service\Picture\PbPictureBinderInterface;

/**
 * Resolves file URLs from direct image fields or PB media references.
 */
final class PbImageFieldUrlResolver implements PbImageFieldUrlResolverInterface {

  public function __construct(
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly PbPictureBinderInterface $pictureBinder,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function absoluteUrl(
    FieldableEntityInterface $entity,
    string $field,
  ): string {
    $uri = $this->resolveFile($entity, $field)?->getFileUri();
    // getFileUri() is nullable, so a damaged File row would otherwise reach
    // generateAbsoluteString() as NULL and raise a TypeError while rendering
    // the page. Treat it exactly like a missing file.
    if ($uri === NULL || $uri === '') {
      return '';
    }
    return $this->fileUrlGenerator->generateAbsoluteString($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function patternUrl(
    ParagraphInterface $paragraph,
    string $field,
    CacheableMetadata $cacheability,
  ): string {
    $file = $this->resolveFile($paragraph, $field);
    if ($file !== NULL) {
      // The file itself: a replaced or deleted file changes the URL.
      $cacheability->addCacheableDependency($file);
    }
    $uri = $file?->getFileUri();
    if ($uri === NULL || $uri === '') {
      return '';
    }
    $style_id = $this->pictureBinder->imageStyle($paragraph, $field, $cacheability);
    $style = $style_id === NULL ? NULL : $this->entityTypeManager->getStorage('image_style')->load($style_id);
    if ($style instanceof ImageStyleInterface && $style->supportsUri($uri)) {
      return $style->buildUrl($uri);
    }
    return $this->fileUrlGenerator->generateAbsoluteString($uri);
  }

  /**
   * Resolves a file entity from a direct image field or media reference.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   Fieldable entity.
   * @param string $field
   *   Image or media reference field machine name.
   *
   * @return \Drupal\file\FileInterface|null
   *   File entity, or NULL when the field has no usable image.
   */
  private function resolveFile(
    FieldableEntityInterface $entity,
    string $field,
  ): ?FileInterface {
    if ($field === '' || !$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return NULL;
    }
    $referenced = $entity->get($field)->entity;
    if ($referenced instanceof FileInterface) {
      return $referenced;
    }
    if (!$referenced instanceof MediaInterface) {
      return NULL;
    }
    if (!$referenced->hasField('pb_image_field')
      || $referenced->get('pb_image_field')->isEmpty()) {
      return NULL;
    }
    $file = $referenced->get('pb_image_field')->entity;
    return $file instanceof FileInterface ? $file : NULL;
  }

}
