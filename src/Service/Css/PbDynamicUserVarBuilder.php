<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Css;

use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_bundles\Service\Field\PbImageFieldUrlResolverInterface;

/**
 * Emits user field values as CSS declarations on v1 inline DOM targets.
 *
 * Each scope maps to the exact element that carried `style=""` in v1 Twig.
 * Properties are written directly (e.g. `gap: 24px`, `perspective: 800px`)
 * so static libraries only supply structure, not user values.
 */
final class PbDynamicUserVarBuilder implements PbDynamicUserVarBuilderInterface {

  /**
   * Bundle scopes keyed by paragraph bundle machine name.
   *
   * `relative` is a descendant selector from the outer `#pb__…-{id}` scope.
   * Empty string targets the outer wrapper itself.
   *
   * @var array<string, list<array{
   *   relative: string,
   *   rules: list<array{
   *     property: string,
   *     field?: string,
   *     fields?: list<string>,
   *     transform: string,
   *     when?: string,
   *   }>,
   * }>>
   */
  private const array USER_STYLE_SCOPES = [
    'image_background_bundle' => [
      [
        'relative' => '',
        'rules' => [
          [
            'property' => 'background-image',
            'transform' => 'bg_image_if_no_sm',
          ],
          [
            'property' => 'background-attachment',
            'field' => 'pb_display_background_attachment',
            'transform' => 'string',
          ],
          [
            'property' => 'background-position',
            'field' => 'pb_display_image_alignment',
            'transform' => 'underscore_space',
          ],
          [
            'property' => 'background-repeat',
            'field' => 'pb_display_image_repetition',
            'transform' => 'underscore_dash',
          ],
          [
            'property' => 'background-size',
            'field' => 'pb_display_image_sizing',
            'transform' => 'string',
          ],
          [
            'property' => 'min-height',
            'field' => 'pb_display_image_min_height',
            'transform' => 'px',
          ],
        ],
      ],
      [
        'relative' => ' .img-color-overlay',
        'rules' => [
          [
            'property' => 'background-color',
            'fields' => ['pb_display_bg', 'pb_display_bg_opacity'],
            'transform' => 'overlay_rgba',
          ],
        ],
      ],
    ],
    'grid_bundle' => [
      [
        'relative' => ' .pb-grid__wrapper',
        'rules' => [
          [
            'property' => 'gap',
            'field' => 'pb_display_grid_gap',
            'transform' => 'px',
          ],
        ],
      ],
    ],
    'carousel_bundle' => [
      [
        'relative' => ' .pb-carousel__slides',
        'rules' => [
          [
            'property' => 'gap',
            'field' => 'pb_display_slide_gap',
            'transform' => 'px_gt_zero',
          ],
        ],
      ],
      [
        'relative' => ' .pb-carousel__slide',
        'rules' => [
          [
            'property' => 'max-width',
            'field' => 'pb_display_custom_slide_width',
            'transform' => 'px_gt_zero',
          ],
        ],
      ],
    ],
    'image_grid_bundle' => [
      [
        'relative' => ' .pb-image-grid__wrapper',
        'rules' => [
          [
            'property' => 'grid-template-columns',
            'field' => 'pb_display_grid_width',
            'transform' => 'grid_minmax_px',
          ],
          [
            'property' => 'gap',
            'field' => 'pb_display_image_grid_gap',
            'transform' => 'px_gt_zero',
          ],
        ],
      ],
    ],
    'two_columns_bundle' => [
      [
        'relative' => ' .pb-two-columns',
        'rules' => [
          [
            'property' => '--pb-col-anim-ms',
            'field' => 'pb_display_2c_anim_ms',
            'transform' => 'ms',
          ],
          [
            'property' => '--pb-col-sticky-top',
            'field' => 'pb_display_2c_stk_top',
            'transform' => 'px',
          ],
        ],
      ],
    ],
    'three_columns_bundle' => [
      [
        'relative' => ' .pb-three-columns',
        'rules' => [
          [
            'property' => '--pb-col-anim-ms',
            'field' => 'pb_display_3c_anim_ms',
            'transform' => 'ms',
          ],
          [
            'property' => '--pb-col-sticky-top',
            'field' => 'pb_display_3c_stk_top',
            'transform' => 'px',
          ],
        ],
      ],
    ],
    '3d_carousel_bundle' => [
      [
        'relative' => ' > .pb-3d-carousel__inner',
        'rules' => [
          [
            'property' => 'perspective',
            'field' => 'pb_display_3d_car_perspective',
            'transform' => 'px',
          ],
        ],
      ],
    ],
    'icon_bundle' => [
      [
        'relative' => ' .icon-wrapper',
        'rules' => [
          [
            'property' => 'font-variation-settings',
            'transform' => 'gms_font_variation',
          ],
        ],
      ],
      [
        'relative' => ' .icon-wrapper span',
        'rules' => [
          [
            'property' => 'color',
            'field' => 'pb_content_gms_icon_color',
            'transform' => 'color',
          ],
          [
            'property' => 'font-size',
            'field' => 'pb_content_gms_icon_font_size',
            'transform' => 'rem',
          ],
        ],
      ],
    ],
  ];

  public function __construct(
    private readonly PbColorNormalizerInterface $colorNormalizer,
    private readonly PbImageFieldUrlResolverInterface $imageFieldUrls,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function build(ParagraphInterface $paragraph, string $outerSelector): string {
    $bundle = $paragraph->bundle();
    if (!isset(self::USER_STYLE_SCOPES[$bundle])) {
      return '';
    }

    $css = '';
    foreach (self::USER_STYLE_SCOPES[$bundle] as $scope) {
      $declarations = [];
      foreach ($scope['rules'] as $rule) {
        if (!$this->passesWhen($paragraph, $rule)) {
          continue;
        }
        $value = $this->resolveValue($paragraph, $rule);
        if ($value === '') {
          continue;
        }
        $declarations[] = sprintf(
          '%s: %s;',
          $rule['property'],
          $this->sanitizeCssValue($value),
        );
      }
      if ($declarations === []) {
        continue;
      }
      $relative = trim($scope['relative']);
      $selector = $relative === '' ? $outerSelector : trim($outerSelector . ' ' . $relative);
      $css .= $this->emitRuleBlock($selector, $declarations);
    }

    return $css;
  }

  /**
   * Checks optional `when` guard on a rule definition.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph entity.
   * @param array<string, mixed> $rule
   *   Rule definition.
   *
   * @return bool
   *   TRUE when the rule should be evaluated.
   */
  private function passesWhen(ParagraphInterface $paragraph, array $rule): bool {
    $when = $rule['when'] ?? '';
    if ($when === '') {
      return TRUE;
    }
    return match ($when) {
      'parallax_not_legacy' => $this->fieldStringValue($paragraph, 'pb_display_parallax_scroll') !== 'legacy',
      default => TRUE,
    };
  }

  /**
   * Resolves a CSS value from a rule definition.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph entity.
   * @param array<string, mixed> $rule
   *   Rule definition from USER_STYLE_SCOPES.
   *
   * @return string
   *   Resolved CSS value without trailing semicolon.
   */
  private function resolveValue(ParagraphInterface $paragraph, array $rule): string {
    $field = $this->ruleField($rule, 'field');
    return match ($rule['transform']) {
      'string' => $this->fieldStringValue($paragraph, $field),
      'underscore_space' => str_replace('_', ' ', $this->fieldStringValue($paragraph, $field)),
      'underscore_dash' => str_replace('_', '-', $this->fieldStringValue($paragraph, $field)),
      'px' => $this->withUnit($this->fieldStringValue($paragraph, $field), 'px'),
      'px_gt_zero' => $this->withUnitGtZero($this->fieldStringValue($paragraph, $field), 'px'),
      'ms' => $this->withUnit($this->fieldStringValue($paragraph, $field), 'ms'),
      'rem' => $this->withUnit($this->fieldStringValue($paragraph, $field), 'rem'),
      'seconds' => $this->withUnit($this->fieldStringValue($paragraph, $field), 's'),
      'color' => $this->resolveColor($paragraph, $field),
      'image_url' => $this->imageFieldUrl($paragraph, $field),
      'overlay_rgba' => $this->colorNormalizer->overlayRgba(
        $this->fieldStringValue($paragraph, $this->ruleField($rule, 'fields', 0)),
        $this->fieldStringValue($paragraph, $this->ruleField($rule, 'fields', 1)),
      ),
      'bg_image_if_no_sm' => $this->backgroundImageIfNoSmall($paragraph),
      'grid_minmax_px' => $this->gridMinmaxPx($this->fieldStringValue($paragraph, $field)),
      'parallax_section_height' => $this->parallaxSectionHeight($paragraph),
      'gms_font_variation' => $this->gmsFontVariation($paragraph),
      default => '',
    };
  }

  /**
   * A field machine name out of a rule definition, or '' if it is not one.
   *
   * USER_STYLE_SCOPES is a class constant, so in practice these keys always
   * hold strings. Narrowing here rather than at each of the thirteen call
   * sites keeps this emit path fail-closed the same way the colour path is: an
   * unexpected value yields no field name, so nothing is looked up and nothing
   * is emitted, instead of a `mixed` reaching a `string` parameter.
   *
   * @param array<string, mixed> $rule
   *   Rule definition.
   * @param string $key
   *   Rule key holding the field name, or a list of them.
   * @param int|null $delta
   *   Index into that list, when the key holds one.
   *
   * @return string
   *   Field machine name, or an empty string.
   */
  private function ruleField(array $rule, string $key, ?int $delta = NULL): string {
    $value = $rule[$key] ?? NULL;
    if ($delta !== NULL) {
      $value = is_array($value) ? ($value[$delta] ?? NULL) : NULL;
    }
    return is_string($value) ? $value : '';
  }

  /**
   * Background image URL when no responsive small image is set.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph entity.
   *
   * @return string
   *   `url('...')` or empty string.
   */
  private function backgroundImageIfNoSmall(ParagraphInterface $paragraph): string {
    if ($this->imageFieldUrl($paragraph, 'pb_display_background_image_sm') !== '') {
      return '';
    }
    return $this->imageFieldUrl($paragraph, 'pb_display_background_image');
  }

  /**
   * Parallax section height with unit suffix.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph entity.
   *
   * @return string
   *   Height with unit, or empty.
   */
  private function parallaxSectionHeight(ParagraphInterface $paragraph): string {
    $height = $this->fieldStringValue($paragraph, 'pb_display_parallax_height');
    if ($height === '') {
      return '';
    }
    $unit = $this->fieldStringValue($paragraph, 'pb_display_para_height_unit');
    if ($unit === 'pr') {
      $unit = '%';
    }
    return $height . $unit;
  }

  /**
   * GMS icon font-variation-settings value.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph entity.
   *
   * @return string
   *   Settings string, or empty when icon name is missing.
   */
  private function gmsFontVariation(ParagraphInterface $paragraph): string {
    if ($this->fieldStringValue($paragraph, 'pb_content_gms_icon_name') === '') {
      return '';
    }
    $fill = $this->fieldStringValue($paragraph, 'pb_content_gms_fill');
    $fill = $fill === '' ? '0' : $fill;
    $wght = $this->fieldStringValue($paragraph, 'pb_content_gms_weight');
    $grad = $this->fieldStringValue($paragraph, 'pb_content_gms_grade');
    $opsz = $this->fieldStringValue($paragraph, 'pb_content_gms_optical_size');
    if ($wght === '' && $grad === '' && $opsz === '') {
      return '';
    }
    return sprintf(
      "'FILL' %s, 'wght' %s, 'GRAD' %s, 'opsz' %s",
      $fill,
      $wght === '' ? '400' : $wght,
      $grad === '' ? '0' : $grad,
      $opsz === '' ? '48' : $opsz,
    );
  }

  /**
   * Grid template columns value for image grid min column width.
   *
   * @param string $width
   *   Column min width in pixels.
   *
   * @return string
   *   `repeat(auto-fill, minmax(Npx, 1fr))` or empty.
   */
  private function gridMinmaxPx(string $width): string {
    if ($width === '' || !is_numeric($width)) {
      return '';
    }
    return sprintf('repeat(auto-fill, minmax(%spx, 1fr))', $width);
  }

  /**
   * Resolves a colour field to a CSS colour value.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph entity.
   * @param string $field
   *   Field machine name.
   *
   * @return string
   *   Hex or rgb() value.
   */
  private function resolveColor(ParagraphInterface $paragraph, string $field): string {
    $value = $this->fieldStringValue($paragraph, $field);
    if ($value === '') {
      return '';
    }
    if (str_starts_with($value, '#')) {
      return $value;
    }
    return sprintf('rgb(%s)', $value);
  }

  /**
   * Absolute URL for an image field, wrapped for background-image.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph entity.
   * @param string $field
   *   Image field machine name.
   *
   * @return string
   *   `url('...')` or empty.
   */
  private function imageFieldUrl(ParagraphInterface $paragraph, string $field): string {
    $url = $this->imageFieldUrls->absoluteUrl($paragraph, $field);
    if ($url === '') {
      return '';
    }
    return sprintf("url('%s')", $this->escapeUrl($url));
  }

  /**
   * Escapes single quotes inside a URL for CSS string literals.
   *
   * @param string $url
   *   Raw URL.
   *
   * @return string
   *   Escaped URL.
   */
  private function escapeUrl(string $url): string {
    return str_replace("'", "\\'", $url);
  }

  /**
   * Appends a CSS unit when the field value is non-empty.
   *
   * @param string $value
   *   Raw field value.
   * @param string $unit
   *   CSS unit suffix.
   *
   * @return string
   *   Value with unit, or empty.
   */
  private function withUnit(string $value, string $unit): string {
    $value = trim($value);
    return $value === '' ? '' : $value . $unit;
  }

  /**
   * Appends a CSS unit when the numeric field value is greater than zero.
   *
   * @param string $value
   *   Raw field value.
   * @param string $unit
   *   CSS unit suffix.
   *
   * @return string
   *   Value with unit, or empty.
   */
  private function withUnitGtZero(string $value, string $unit): string {
    $value = trim($value);
    if ($value === '' || !is_numeric($value) || (float) $value <= 0) {
      return '';
    }
    return $value . $unit;
  }

  /**
   * Read a single scalar field value as a string.
   *
   * Numbers are coerced rather than rejected — see the matching note on
   * PbDynamicCssBuilder::fieldStringValue() for why an `is_string()` gate here
   * dropped every numeric declaration on the programmatic-creation path. The
   * rules that reach this method through the `px`, `px_gt_zero`, `ms`, `rem`
   * and `seconds` transforms all read integer storages, so it was most of
   * them.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity.
   * @param string $field
   *   Field machine name.
   *
   * @return string
   *   Trimmed value, or empty string when missing/empty/not scalar.
   */
  private function fieldStringValue(ParagraphInterface $paragraph, string $field): string {
    if ($field === '' || !$paragraph->hasField($field) || $paragraph->get($field)->isEmpty()) {
      return '';
    }
    $item = $paragraph->get($field)->first();
    $raw = $item === NULL ? NULL : $item->getValue();
    $value = is_array($raw) ? ($raw['value'] ?? NULL) : NULL;
    if (is_int($value) || is_float($value)) {
      return (string) $value;
    }
    return is_string($value) ? trim($value) : '';
  }

  /**
   * Emits a CSS rule block with property declarations.
   *
   * @param string $selector
   *   Full CSS selector.
   * @param list<string> $declarations
   *   Property declaration lines.
   *
   * @return string
   *   CSS source.
   */
  private function emitRuleBlock(string $selector, array $declarations): string {
    return sprintf(
      "/* Auto-generated by paragraphs_bundles. Do not edit. */\n%s {\n  %s\n}\n",
      $selector,
      implode("\n  ", $declarations),
    );
  }

  /**
   * Neutralises characters that could break out of a CSS declaration.
   *
   * The values written here derive from typed, widget-validated fields, so
   * this is defence in depth: even if a value reached storage through a path
   * that bypassed field validation (config import, migration, direct API
   * write), it cannot escape its declaration or rule to inject arbitrary CSS.
   * No legitimate value produced by this builder (numbers, units, hex/rgb
   * colours, url(), minmax(), font-variation-settings) contains these
   * characters.
   *
   * @param string $value
   *   The resolved CSS value.
   *
   * @return string
   *   The value with rule/declaration-breaking characters removed.
   */
  private function sanitizeCssValue(string $value): string {
    // Delegates to the shared implementation so there is exactly one
    // definition of "what is stripped" for the whole module. Previously this
    // method was the only sanitizer in the codebase and the colour path in
    // PbDynamicCssBuilder had none.
    return $this->colorNormalizer->sanitizeCssValue($value);
  }

}
