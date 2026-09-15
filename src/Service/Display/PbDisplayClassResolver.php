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
   * Field holding author-supplied classes for the outer element.
   */
  public const string CUSTOM_CLASS_FIELD = 'pb_display_css_classes';

  /**
   * Most class tokens one paragraph may contribute.
   *
   * The field is capped at 255 characters, so this is a backstop against a
   * pathological value rather than a limit an author will meet.
   */
  private const int CUSTOM_CLASS_MAX = 32;

  /**
   * Characters a class token may contain.
   *
   * Deliberately permissive: Tailwind utilities need `:` (`md:w-full`), `/`
   * (`top-1/2`), `[]()` and `#` (`bg-[#1da1f2]`, `w-[calc(100%-2rem)]`), `!`
   * (`!font-bold`) and `@` (container queries). Rejected are the characters
   * that end a class attribute or open a tag - `< > " \' & = \\ ` - plus
   * braces, semicolons and control characters.
   *
   * Tailwind's arbitrary-VARIANT syntax (`[&>*]:mt-0`) contains `&` and `>`
   * and is refused on purpose: those two characters are what an attribute
   * injection needs, and no styling need justifies admitting them here.
   *
   * @see ::customClasses()
   */
  private const string CUSTOM_CLASS_PATTERN = '/^[A-Za-z0-9_:.\\/\\[\\]()%#,!@*+~$-]+$/';

  /**
   * Classes the preprocess hook pins itself; never duplicated from the field.
   */
  private const array CUSTOM_CLASS_RESERVED = ['paragraph', 'paragraphs-bundles'];

  /**
   * {@inheritdoc}
   */
  public function customClasses(FieldableEntityInterface $paragraph): array {
    if (!$paragraph->hasField(self::CUSTOM_CLASS_FIELD)) {
      return [];
    }
    $items = $paragraph->get(self::CUSTOM_CLASS_FIELD);
    if ($items->isEmpty()) {
      return [];
    }
    $value = $items->value;
    if (!is_string($value)) {
      return [];
    }
    return $this->filterClassTokens($value)['accepted'];
  }

  /**
   * {@inheritdoc}
   */
  public function filterClassTokens(string $value): array {
    $result = ['accepted' => [], 'rejected' => [], 'over_cap' => []];
    if (trim($value) === '') {
      return $result;
    }

    // Split on any whitespace run, including the newlines a paste can carry.
    $tokens = preg_split('/\\s+/', trim($value)) ?: [];

    $accepted = [];
    foreach ($tokens as $token) {
      if ($token === '' || in_array($token, self::CUSTOM_CLASS_RESERVED, TRUE)) {
        continue;
      }
      // Reject, never rewrite. Html::getClass() would lowercase `myBrandBox`
      // and mangle every Tailwind token containing `[`, `/` or `#`; silently
      // emitting a class the author did not type is worse than emitting none.
      if (preg_match(self::CUSTOM_CLASS_PATTERN, $token) !== 1) {
        $result['rejected'][$token] = $token;
        continue;
      }
      if (count($accepted) >= self::CUSTOM_CLASS_MAX && !isset($accepted[$token])) {
        $result['over_cap'][$token] = $token;
        continue;
      }
      $accepted[$token] = $token;
    }

    $result['accepted'] = array_values($accepted);
    $result['rejected'] = array_values($result['rejected']);
    $result['over_cap'] = array_values($result['over_cap']);
    return $result;
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
