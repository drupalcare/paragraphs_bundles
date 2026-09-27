<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Default shape reader: formatter, then view mode, then image style.
 */
final class PictureShapes implements PictureShapesInterface {

  /**
   * Image effects that fix both dimensions.
   *
   * A scale keeps the picture's own proportions, so it says nothing about the
   * shape a replacement should be.
   */
  private const array CROPS = ['image_scale_and_crop', 'image_crop'];

  /**
   * Constructs the reader.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Loads view displays, image styles and responsive image styles.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function shapeFor(string $bundle, string $field): ?array {
    $component = $this->component('paragraph', $bundle, $field);

    if ($component === NULL) {
      return NULL;
    }

    // A media reference: the picture is drawn by the media item's own display
    // in the view mode this field asks for.
    if ($component['type'] === 'entity_reference_entity_view') {
      $settings = is_array($component['settings'] ?? NULL) ? $component['settings'] : [];
      $viewMode = is_string($settings['view_mode'] ?? NULL) ? $settings['view_mode'] : 'default';

      return $this->mediaShape($bundle, $field, $viewMode);
    }

    return $this->formatterShape($component);
  }

  /**
   * The shape a media view mode draws its picture at.
   *
   * @param string $bundle
   *   Paragraph bundle.
   * @param string $field
   *   The media reference field.
   * @param string $viewMode
   *   The media view mode the field renders with.
   *
   * @return array{0: int, 1: int}|null
   *   Width and height, or NULL.
   */
  private function mediaShape(string $bundle, string $field, string $viewMode): ?array {
    $definition = $this->entityTypeManager->getStorage('field_config')->load("paragraph.$bundle.$field");
    $settings = $definition?->getSetting('handler_settings');
    $targets = is_array($settings) && is_array($settings['target_bundles'] ?? NULL) ? array_values($settings['target_bundles']) : [];

    // The first media type with a shape answers; PB's picture fields accept
    // exactly one, `pb_media_image`.
    foreach ($targets as $mediaType) {
      $media = $this->entityTypeManager->getStorage('media_type')->load((string) $mediaType);
      $source = $media?->getSource()->getConfiguration()['source_field'] ?? NULL;

      if (!is_string($source)) {
        continue;
      }

      $component = $this->component('media', (string) $mediaType, $source, $viewMode)
        ?? $this->component('media', (string) $mediaType, $source);
      $shape = $component === NULL ? NULL : $this->formatterShape($component);

      if ($shape !== NULL) {
        return $shape;
      }
    }

    return NULL;
  }

  /**
   * The shape an image or responsive image formatter produces.
   *
   * @param array<string, mixed> $component
   *   A view display component.
   *
   * @return array{0: int, 1: int}|null
   *   Width and height, or NULL.
   */
  private function formatterShape(array $component): ?array {
    $settings = is_array($component['settings'] ?? NULL) ? $component['settings'] : [];

    if ($component['type'] === 'responsive_image' && is_string($settings['responsive_image_style'] ?? NULL)) {
      $responsive = $this->entityTypeManager->getStorage('responsive_image_style')->load($settings['responsive_image_style']);

      if ($responsive === NULL) {
        return NULL;
      }

      // The LARGEST mapped style: a replacement at least that big is sharp at
      // every width the style serves.
      $best = NULL;

      foreach (array_unique(array_merge([$responsive->getFallbackImageStyle()], $responsive->getImageStyleIds())) as $style) {
        $shape = $this->styleShape((string) $style);

        if ($shape !== NULL && ($best === NULL || $shape[0] > $best[0])) {
          $best = $shape;
        }
      }

      return $best;
    }

    if ($component['type'] === 'image' && is_string($settings['image_style'] ?? NULL)) {
      return $this->styleShape($settings['image_style']);
    }

    return NULL;
  }

  /**
   * The fixed size an image style crops to.
   *
   * @param string $style
   *   Image style id.
   *
   * @return array{0: int, 1: int}|null
   *   Width and height, or NULL when the style does not fix both.
   */
  private function styleShape(string $style): ?array {
    if ($style === '') {
      return NULL;
    }

    $imageStyle = $this->entityTypeManager->getStorage('image_style')->load($style);

    if ($imageStyle === NULL) {
      return NULL;
    }

    $shape = NULL;

    // The LAST fixing effect wins: it is the one that decides what comes out.
    foreach ($imageStyle->getEffects() as $effect) {
      $configuration = $effect->getConfiguration();
      $width = $configuration['data']['width'] ?? NULL;
      $height = $configuration['data']['height'] ?? NULL;

      if (in_array($configuration['id'] ?? '', self::CROPS, TRUE) && is_numeric($width) && is_numeric($height)) {
        $shape = [(int) $width, (int) $height];
      }
    }

    return $shape;
  }

  /**
   * One field's component on a view display.
   *
   * @param string $entityType
   *   Entity type id.
   * @param string $bundle
   *   Bundle.
   * @param string $field
   *   Field machine name.
   * @param string $mode
   *   View mode.
   *
   * @return array<string, mixed>|null
   *   The component, or NULL when the display or the field on it is missing.
   */
  private function component(string $entityType, string $bundle, string $field, string $mode = 'default'): ?array {
    $display = $this->entityTypeManager->getStorage('entity_view_display')->load("$entityType.$bundle.$mode");

    if ($display === NULL || !$display->status()) {
      return NULL;
    }

    $component = $display->getComponent($field);

    return is_array($component) && is_string($component['type'] ?? NULL) ? $component : NULL;
  }

}
