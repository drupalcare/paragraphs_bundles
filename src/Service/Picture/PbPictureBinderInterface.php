<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Picture;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Draws a patterned section's pictures with the view mode its Pattern names.
 *
 * The bundle's view display is never changed: only the render array of one
 * paragraph is. A paragraph outside any Pattern keeps the bundle display.
 */
interface PbPictureBinderInterface {

  /**
   * Adds a Pattern set's provider. Called by the service collector.
   *
   * @param \Drupal\paragraphs_bundles\Service\Picture\PbPictureMapProviderInterface $provider
   *   The provider.
   */
  public function addProvider(PbPictureMapProviderInterface $provider): void;

  /**
   * Re-draws the paragraph's picture fields that its nearest Pattern names.
   *
   * @param array<string, mixed> $build
   *   The paragraph's field render arrays, from the view display.
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph being shown.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The view display that built $build.
   * @param string $view_mode
   *   The paragraph view mode asked for. Only the page displays (default,
   *   full, preview) are bound; every other view mode is left as it is.
   */
  public function bind(array &$build, ParagraphInterface $paragraph, EntityViewDisplayInterface $display, string $view_mode): void;

  /**
   * Returns the image style the nearest Pattern names for an image field.
   *
   * For a picture no formatter draws (an `image` field a template prints as a
   * URL, or a builder that draws the file itself), the Pattern's `style` is the
   * only size it can carry. Media fields are left to bind().
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph that holds the field.
   * @param string $field
   *   The `image` field machine name.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects every parent paragraph read and the style's config tags.
   *
   * @return string|null
   *   The image style id, or NULL when no Pattern names one for the field, the
   *   field is not an `image` field, or the style does not exist on the site.
   */
  public function imageStyle(ParagraphInterface $paragraph, string $field, CacheableMetadata $cacheability): ?string;

}
