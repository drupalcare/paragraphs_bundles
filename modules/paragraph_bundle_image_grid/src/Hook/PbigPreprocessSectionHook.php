<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_image_grid\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\media\MediaInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Preprocess hook — full-size image URL for grid lightbox.
 */
final class PbigPreprocessSectionHook {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_preprocess_paragraph__image_grid_section_bundle().
   *
   * @param array<string, mixed> $variables
   *   Render variables (passed by reference).
   */
  #[Hook('preprocess_paragraph__image_grid_section_bundle')]
  public function preprocessImageGridSection(array &$variables): void {
    $paragraph = $variables['paragraph'] ?? NULL;
    if (!$paragraph instanceof ParagraphInterface) {
      return;
    }
    if (!$paragraph->hasField('pb_content_image')
      || $paragraph->get('pb_content_image')->isEmpty()) {
      return;
    }

    $media = $paragraph->get('pb_content_image')->entity;
    if (!$media instanceof MediaInterface) {
      return;
    }
    if (!$media->hasField('pb_image_field')
      || $media->get('pb_image_field')->isEmpty()) {
      return;
    }

    $file = $media->get('pb_image_field')->entity;
    if (!$file instanceof FileInterface) {
      return;
    }

    // A File row can carry a NULL uri if the record is damaged; buildUrl()
    // is typed string and would TypeError, white-screening the whole page.
    $uri = $file->getFileUri();
    if ($uri === NULL) {
      return;
    }

    $style_storage = $this->entityTypeManager->getStorage('image_style');
    $style = $style_storage->load('pb_image_grid_full');
    if (!$style instanceof ImageStyleInterface) {
      $style = $style_storage->load('pb_original_size');
    }
    if (!$style instanceof ImageStyleInterface) {
      return;
    }
    $variables['pb_full_size_url'] = $style->buildUrl($uri);

    // The URL is derived from the file, the media that owns it and the style
    // that builds it — all three have to invalidate it.
    $cacheability = CacheableMetadata::createFromRenderArray($variables);
    $cacheability->addCacheableDependency($file);
    $cacheability->addCacheableDependency($media);
    $cacheability->addCacheableDependency($style);
    $cacheability->applyTo($variables);
  }

}
