<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Display;

use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Resolves stored display-field values into the CSS classes templates emit.
 *
 * V1 stored some display values *as* the CSS class (e.g. `pb__round_large`).
 * V2 renders a modern BEM class without changing the stored value: the map is
 * generated from `scripts/pb-class-naming-rules.json` into
 * `src/Twig/PbValueClassMap.php`. Any value absent from the map is returned
 * verbatim, so an unmapped value can never break styling.
 */
final class PbDisplayClassResolver implements PbDisplayClassResolverInterface {

  /**
   * Display fields whose value becomes a class on the inner wrapper.
   *
   * Every bundle uses this list unless it appears in the overrides below. The
   * list is a DOM contract, not something to derive from the fields a bundle
   * happens to have: `hero_bundle` carries `pb_display_margin` but puts it on
   * a different element, so deriving would silently move it.
   */
  private const array INNER_CLASS_FIELDS = [
    'pb_display_width',
    'pb_display_border',
    'pb_display_border_radius',
    'pb_display_margin',
    'pb_display_padding',
    'pb_display_shadow',
  ];

  /**
   * Bundles whose inner wrapper takes a different set of display fields.
   *
   * @var array<string, list<string>>
   */
  private const array INNER_CLASS_FIELD_OVERRIDES = [
    // Margin goes on `.hero-bundle-inner`, not the text overlay; the overlay
    // additionally takes the nine-position `pb_display_hero` class.
    'hero_bundle' => [
      'pb_display_width',
      'pb_display_border',
      'pb_display_border_radius',
      'pb_display_padding',
      'pb_display_shadow',
      'pb_display_hero',
    ],
    // The background image owns the box; only width and padding apply.
    'image_background_bundle' => [
      'pb_display_width',
      'pb_display_padding',
    ],
    // Padding would push the overlay off its image.
    'image_overlay_bundle' => [
      'pb_display_width',
      'pb_display_border',
      'pb_display_border_radius',
      'pb_display_margin',
      'pb_display_shadow',
    ],
  ];

  /**
   * Cached stored-value → BEM class map (generated file, loaded once).
   *
   * @var array<string, string>|null
   */
  private ?array $valueClassMap = NULL;

  /**
   * {@inheritdoc}
   */
  public function className(string $value): string {
    if ($value === '') {
      return '';
    }
    return $this->valueClassMap()[$value] ?? $value;
  }

  /**
   * {@inheritdoc}
   */
  public function innerClasses(FieldableEntityInterface $paragraph): array {
    $fields = self::INNER_CLASS_FIELD_OVERRIDES[$paragraph->bundle()]
      ?? self::INNER_CLASS_FIELDS;

    $classes = [];
    foreach ($fields as $field) {
      if (!$paragraph->hasField($field)) {
        continue;
      }
      $items = $paragraph->get($field);
      if ($items->isEmpty()) {
        continue;
      }
      $value = $items->value;
      if (!is_string($value) || $value === '') {
        continue;
      }
      $classes[] = $this->className($value);
    }
    return $classes;
  }

  /**
   * Loads the generated stored-value → BEM class map once per request.
   *
   * @return array<string, string>
   *   Map keyed by v1 value/class, valued by v2 BEM class.
   */
  private function valueClassMap(): array {
    if ($this->valueClassMap !== NULL) {
      return $this->valueClassMap;
    }
    $file = __DIR__ . '/../../Twig/PbValueClassMap.php';
    $raw = is_file($file) ? require $file : [];
    $map = [];
    if (is_array($raw)) {
      foreach ($raw as $key => $value) {
        if (is_string($key) && is_string($value)) {
          $map[$key] = $value;
        }
      }
    }
    $this->valueClassMap = $map;
    return $map;
  }

}
