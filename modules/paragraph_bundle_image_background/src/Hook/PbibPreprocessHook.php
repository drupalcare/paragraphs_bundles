<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_image_background\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_bundles\Service\Field\PbImageFieldUrlResolverInterface;

/**
 * Preprocess hook: the background image URLs the template prints.
 *
 * A section whose Pattern names an image style for a background field gets
 * that style's derivative; any other section gets the file as uploaded.
 */
final class PbibPreprocessHook {

  public function __construct(
    private readonly PbImageFieldUrlResolverInterface $imageFieldUrls,
  ) {}

  /**
   * Implements hook_preprocess_paragraph__image_background_bundle().
   *
   * @param array<string, mixed> $variables
   *   Render variables (passed by reference).
   */
  #[Hook('preprocess_paragraph__image_background_bundle')]
  public function preprocessImageBackground(array &$variables): void {
    $paragraph = $variables['paragraph'] ?? NULL;
    if (!$paragraph instanceof ParagraphInterface) {
      return;
    }
    $cacheability = CacheableMetadata::createFromRenderArray($variables);
    $variables['pb_image_url'] = $this->imageFieldUrls->patternUrl($paragraph, 'pb_display_background_image', $cacheability);
    $variables['pb_image_url_sm'] = $this->imageFieldUrls->patternUrl($paragraph, 'pb_display_background_image_sm', $cacheability);
    // Core bubbles a preprocess hook's #cache: the style config tags and every
    // parent paragraph the Pattern lookup read.
    $cacheability->applyTo($variables);
  }

}
