<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Css;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_bundles\Service\Bundle\BundleSluggerInterface;

/**
 * Default {@see PbDynamicCssBuilderInterface} implementation.
 *
 * Emits two un-layered rule blocks scoped to the paragraph's wrapper
 * via the standardised v2 outer-element id (`pb__{slug}-{id}`):
 *
 *   #pb__{slug}-{id} > .paragraph__inner {
 *     --pb-bg: ...;  --pb-bg-h: ...;  ...
 *     --pb-lk: ...;  --pb-lk-h: ...;
 *     background-color: var(--pb-bg);
 *     border-color:     var(--pb-br);
 *     color:            var(--pb-tx);
 *   }
 *   #pb__{slug}-{id} > .paragraph__inner:hover {
 *     background-color: var(--pb-bg-h);
 *     border-color:     var(--pb-br-h);
 *     color:            var(--pb-tx-h);
 *   }
 *   #pb__{slug}-{id} > .paragraph__inner :where(a) {
 *     color: var(--pb-lk);
 *   }
 *   #pb__{slug}-{id} > .paragraph__inner:hover :where(a) {
 *     color: var(--pb-lk-h);
 *   }
 *
 * Link colours use the same id-scoped selector as surface colours so
 * host-theme link rules cannot override editor settings. Foundation CSS
 * applies bridged text/border tokens; background is bundle-scoped only.
 *
 * --- Why this selector ---------------------------------------------
 *
 * v2 standardises the outer-element id as `pb__{slug}-{id}` for every
 * bundle (v1 used 30 distinct bespoke patterns: `pb__nf-`, `pb__caro-wrap-`,
 * `pb__slide-wrap-`, `pb-card-text-section-`, etc.). Each v2 paragraph
 * template sets the same id format via `pb_paragraph_id` from
 * {@see \Drupal\paragraphs_bundles\Hook\PbPreprocessHook}, so the
 * builder and the templates stay in lock-step.
 *
 * Selector specificity: `#pb__{slug}-{id} > .paragraph__inner` = (1,1,1).
 * Beats `paragraphs-bundles-custom.css` rule `.paragraph > .paragraph__inner`
 * (0,2,1) and any reasonable host-theme rule decisively. Editor
 * settings always win without `!important`.
 *
 * The variables are kept on the inner element so sub-themes that read
 * `--pb-bg` / `--pb-tx` directly (v1 contract) keep working. The
 * dedicated `:hover` selector pins hover via the same compound class
 * so a theme can't override it via plain `.paragraph__inner:hover`.
 *
 * Un-layered emission is required: per CSS Cascade L5 un-layered
 * author CSS beats layered author CSS. PB stays un-layered for exactly
 * this reason — wrapping this in a `@layer pb.dynamic` would let an
 * un-layered host-theme `:root` declaration shadow our values.
 *
 * --- Where to change the DOM contract ------------------------------
 *
 * Two class constants below define the selector contract:
 *
 *   - {@see self::OUTER_SCOPE_PATTERN}  — `#pb__%s-%d` (slug, id)
 *   - {@see self::INNER_DESCENDANT}     — `> .paragraph__inner`
 *
 * If a future v2 template change moves the outer id format or
 * restructures the inner element, update these two constants — no
 * other call site needs to know. Templates must keep the outer
 * `id="pb__{slug}-{id}"` in sync (set by `pb_paragraph_id` in the
 * preprocess hook).
 *
 * Property declarations are only emitted for variables that are
 * actually set; partial input (e.g. only `--pb-bg`) does NOT zero out
 * unrelated properties (border, color).
 */
final class PbDynamicCssBuilder implements PbDynamicCssBuilderInterface {

  /**
   * Outer-scope selector pattern.
   *
   * `%s` is the kebab-case bundle slug (e.g. `simple` for `simple_bundle`,
   * `3d-carousel` for `3d_carousel_bundle`); `%d` is the paragraph
   * entity id.
   *
   * Specificity (1,0,0); composed with {@see self::INNER_DESCENDANT}
   * the final scope is (1,1,1) — beats any class-chain rule.
   */
  private const string OUTER_SCOPE_PATTERN = '#pb__%s-%d';

  /**
   * Inner-element descendant selector relative to the outer scope.
   *
   * Set to '' (empty string) if a future template renders the
   * `--pb-*` variables directly on the outer wrapper instead of an
   * inner div. The `build()` composer trims redundant whitespace.
   */
  private const string INNER_DESCENDANT = '> .pb-ui-inner';

  /**
   * Bundle-specific color scope when v1 Twig targets a nested element.
   *
   * Key = paragraph bundle machine name; value = descendant selector
   * relative to {@see self::OUTER_SCOPE_PATTERN} (no leading space).
   * Falls back to {@see self::INNER_DESCENDANT} when not listed.
   *
   * @var array<string, string>
   */
  private const array INNER_DESCENDANT_OVERRIDES = [
    'hero_bundle' => '.pb-hero__text-inner',
    'modal_bundle' => '.pb-modal__content > .pb-ui-inner',
    'parallax_section_bundle' => '.pb-parallax__text',
  ];

  /**
   * V1 outer-element id prefix overrides (bundle machine name => prefix).
   *
   * When set, preprocess and CSS scope use `prefix-{id}` instead of
   * `pb__{slug}-{id}`. Must stay in sync with the bundle Twig template.
   *
   * @var array<string, string>
   */
  public const array OUTER_ID_PREFIX_OVERRIDES = [
    'hero_bundle' => 'pb__hero',
    'image_narrow_bundle' => 'pb__image-n',
    'image_wide_bundle' => 'pb__image-w',
    'accordion_bundle' => 'pb__accor-wrap',
    'card_two_columns_bundle' => 'pb__card-two-columns',
    'card_three_columns_bundle' => 'pb__card-three-columns',
    'card_text_section_bundle' => 'pb-card-text-section',
    'card_image_section_bundle' => 'pb__image',
    'modal_bundle' => 'pb__modal-id',
    'node_reference_bundle' => 'pb__nf',
    'parallax_bundle' => 'pb-parallax',
    'parallax_section_bundle' => 'pb-parallax',
    'slideshow_bundle' => 'pb__slide-wrap',
    'image_background_bundle' => 'pb__image-bg',
    'image_grid_bundle' => 'pb__image-grid',
    '3d_carousel_bundle' => 'pb-3d-carousel',
  ];

  /**
   * V1 outer-element id suffix appended after `{prefix}-{id}`.
   *
   * @var array<string, string>
   */
  private const array OUTER_ID_SUFFIX_OVERRIDES = [
    'modal_bundle' => '-win',
  ];

  /**
   * Split color scopes when v1 Twig targets multiple DOM nodes.
   *
   * Each entry lists custom-property keys (`pb-bg`, etc.) for one
   * descendant selector relative to the outer `#prefix-{id}` scope.
   *
   * @var array<string, list<array{relative: string, include: list<string>}>>
   */
  private const array SPLIT_COLOR_SCOPES = [
    'card_image_section_bundle' => [
      [
        'relative' => '> .pb-ui-inner',
        'include' => ['pb-br', 'pb-br-h', 'pb-lk', 'pb-lk-h'],
      ],
      [
        'relative' => ' .pb-card__image-title',
        'include' => ['pb-bg', 'pb-bg-h', 'pb-tx', 'pb-tx-h'],
      ],
    ],
    'image_grid_section_bundle' => [
      [
        'relative' => '> .pb-ui-inner',
        'include' => ['pb-br', 'pb-br-h', 'pb-lk', 'pb-lk-h'],
      ],
      [
        'relative' => ' .pb-image-grid__title',
        'include' => ['pb-bg', 'pb-bg-h', 'pb-tx', 'pb-tx-h'],
      ],
    ],
    'image_overlay_bundle' => [
      [
        'relative' => '> .pb-ui-inner',
        'include' => ['pb-br', 'pb-br-h', 'pb-lk', 'pb-lk-h'],
      ],
    ],
  ];

  /**
   * Bundles where specific colour keys must not emit on the inner scope.
   *
   * @var array<string, list<string>>
   */
  private const array BUNDLE_COLOR_EXCLUDES = [
    'image_background_bundle' => ['pb-bg', 'pb-bg-h'],
  ];

  /**
   * CSS file slug for `pb_content` nodes (`pb-pb-content-{nid}.css`).
   */
  public const string NODE_PB_CONTENT_SLUG = 'pb-content';

  /**
   * Map of `--pb-*` custom property name → paragraph field machine name.
   *
   * Identical to the `pb_styles` map in `paragraph--default.html.twig`.
   *
   * @var array<string, string>
   */
  private const array COLOR_FIELDS = [
    'pb-bg' => 'pb_display_bg',
    'pb-bg-h' => 'pb_display_bg_hover',
    'pb-br' => 'pb_display_border_color',
    'pb-br-h' => 'pb_display_border_hover',
    'pb-tx' => 'pb_display_text',
    'pb-tx-h' => 'pb_display_text_hover',
    'pb-lk' => 'pb_display_link',
    'pb-lk-h' => 'pb_display_link_hover',
  ];

  /**
   * Bundle-specific custom properties beyond {@see self::COLOR_FIELDS}.
   *
   * @var array<string, array<string, string>>
   */
  private const array BUNDLE_EXTRA_COLOR_FIELDS = [
    'accordion_bundle' => [
      'pb-bt-a' => 'pb_display_accor_active_item',
    ],
    'tabs_bundle' => [
      'pb-bt-a' => 'pb_display_tab_active_item',
    ],
    'slideshow_bundle' => [
      'pb-bt-a' => 'pb_display_slide_active_item',
    ],
  ];

  /**
   * Custom-property short name → CSS property to apply via `var()`.
   *
   * Used to mirror each set custom property as an actual property
   * declaration so hover state can be pinned by selector specificity.
   *
   * @var array<string, string>
   */
  private const array PROPERTY_MAP = [
    'pb-bg' => 'background-color',
    'pb-br' => 'border-color',
    'pb-tx' => 'color',
  ];

  public function __construct(
    private readonly BundleSluggerInterface $slugger,
    private readonly PbColorNormalizerInterface $colorNormalizer,
    private readonly PbDynamicUserVarBuilderInterface $userVarBuilder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function build(ParagraphInterface $paragraph): string {
    $bundle = $paragraph->bundle();
    $paragraphId = (int) $paragraph->id();
    $slug = $this->slugger->slugify($bundle);
    $outer = $this->outerScopeId($bundle, $slug, $paragraphId);

    $colorCss = $this->buildColorCss($paragraph, $bundle, $slug, $paragraphId);
    $userCss = $this->userVarBuilder->build($paragraph, $outer);

    if ($colorCss === '' && $userCss === '') {
      return '';
    }
    return $colorCss . $userCss;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForNode(NodeInterface $node): string {
    if ($node->bundle() !== 'pb_content') {
      return '';
    }
    $bg = $this->fieldStringValue($node, 'pb_display_content_bg');
    // overlayRgba() rejects malformed colours, but check here too so the
    // guarantee is visible at the emit site rather than implied.
    if ($bg === '' || !$this->colorNormalizer->isSafeColorValue($bg)) {
      return '';
    }
    $background = $this->colorNormalizer->overlayRgba(
      $bg,
      $this->fieldStringValue($node, 'pb_display_content_bg_opacity'),
    );
    if ($background === '') {
      return '';
    }
    return sprintf(
      "/* Auto-generated by paragraphs_bundles. Do not edit. */\n#node-%d {\n  background-color: %s;\n}\n",
      (int) $node->id(),
      $background,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildInlineCustomPropertiesStyle(
    ParagraphInterface $paragraph,
    array $include,
  ): string {
    $variables = $this->collectColorVariables($paragraph);
    if ($variables === []) {
      return '';
    }
    $declarations = [];
    foreach ($include as $key) {
      if (!is_string($key) || !isset($variables[$key])) {
        continue;
      }
      $declarations[] = rtrim($variables[$key], ';');
    }
    return implode('; ', $declarations);
  }

  /**
   * Collects `--pb-*` declarations from display colour fields.
   *
   * @return array<string, string>
   *   Map of custom-property short name → full declaration.
   */
  private function collectColorVariables(ParagraphInterface $paragraph): array {
    $bundle = $paragraph->bundle();
    $opacity = $this->colorNormalizer->formatOpacity(
      $this->fieldStringValue($paragraph, 'pb_display_bg_opacity'),
    );
    $variables = [];
    $colorFields = self::COLOR_FIELDS;
    if (isset(self::BUNDLE_EXTRA_COLOR_FIELDS[$bundle])) {
      $colorFields = array_merge($colorFields, self::BUNDLE_EXTRA_COLOR_FIELDS[$bundle]);
    }
    $excludes = self::BUNDLE_COLOR_EXCLUDES[$bundle] ?? [];
    foreach ($colorFields as $custom => $field) {
      if (!is_string($custom) || in_array($custom, $excludes, TRUE)) {
        continue;
      }
      $value = $this->fieldStringValue($paragraph, $field);
      if ($value === '') {
        continue;
      }
      $declaration = $this->buildDeclaration($custom, $field, $value, $opacity);
      // A rejected value yields no declaration. Skip the key entirely —
      // emitScopeBlock() and buildLinkRules() branch on isset(), so keeping
      // an empty entry would emit `color: var(--pb-tx);` against a custom
      // property that was never defined.
      if ($declaration === '') {
        continue;
      }
      $variables[$custom] = $declaration;
    }
    return $variables;
  }

  /**
   * Builds colour-variable CSS for a paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph entity.
   * @param string $bundle
   *   Bundle machine name.
   * @param string $slug
   *   Kebab-case bundle slug.
   * @param int $paragraphId
   *   Paragraph entity id.
   *
   * @return string
   *   Colour CSS source, or empty when no colour fields are set.
   */
  private function buildColorCss(
    ParagraphInterface $paragraph,
    string $bundle,
    string $slug,
    int $paragraphId,
  ): string {
    $variables = $this->collectColorVariables($paragraph);
    if ($variables === []) {
      return '';
    }

    if (isset(self::SPLIT_COLOR_SCOPES[$bundle])) {
      $css = '';
      foreach (self::SPLIT_COLOR_SCOPES[$bundle] as $scope) {
        $filtered = [];
        foreach ($scope['include'] as $key) {
          if (isset($variables[$key])) {
            $filtered[$key] = $variables[$key];
          }
        }
        if ($filtered === []) {
          continue;
        }
        $outer = $this->outerScopeId($bundle, $slug, $paragraphId);
        $selector = trim($outer . ' ' . $scope['relative']);
        $css .= $this->emitScopeBlock($selector, $filtered);
      }
      return $css;
    }

    $selector = $this->scopeSelector($bundle, $slug, $paragraphId);
    return $this->emitScopeBlock($selector, $variables);
  }

  /**
   * Emits variable declarations, property rules, hover, and link colours.
   *
   * @param string $selector
   *   Full CSS scope selector.
   * @param array<string, string> $variables
   *   Custom-property declarations keyed by short name.
   *
   * @return string
   *   CSS source for this scope.
   */
  private function emitScopeBlock(string $selector, array $variables): string {
    $base = array_values($variables);
    foreach (self::PROPERTY_MAP as $custom => $property) {
      if (isset($variables[$custom])) {
        $base[] = sprintf('%s: var(--%s);', $property, $custom);
      }
    }

    $hover = [];
    foreach (self::PROPERTY_MAP as $custom => $property) {
      $hoverCustom = $custom . '-h';
      if (isset($variables[$hoverCustom])) {
        $hover[] = sprintf('%s: var(--%s);', $property, $hoverCustom);
      }
    }

    $css = sprintf(
      "/* Auto-generated by paragraphs_bundles. Do not edit. */\n%s {\n  %s\n}\n",
      $selector,
      implode("\n  ", $base),
    );
    if ($hover !== []) {
      $css .= sprintf(
        "%s:hover {\n  %s\n}\n",
        $selector,
        implode("\n  ", $hover),
      );
    }

    $css .= $this->buildLinkRules($selector, $variables);

    return $css;
  }

  /**
   * Builds id-scoped link colour rules when link display fields are set.
   *
   * @param string $selector
   *   Scope selector for the paragraph inner wrapper.
   * @param array<string, string> $variables
   *   Custom-property declarations keyed by short name (`pb-lk`, etc.).
   *
   * @return string
   *   Additional CSS rules, or an empty string.
   */
  private function buildLinkRules(string $selector, array $variables): string {
    $css = '';
    if (isset($variables['pb-lk'])) {
      $css .= sprintf(
        "%s :where(a) {\n  color: var(--pb-lk);\n}\n",
        $selector,
      );
    }
    if (isset($variables['pb-lk-h'])) {
      $css .= sprintf(
        "%s :where(a):hover {\n  color: var(--pb-lk-h);\n}\n",
        $selector,
      );
    }
    return $css;
  }

  /**
   * Compose the full scope selector for a paragraph entity.
   *
   * Outer scope (`OUTER_SCOPE_PATTERN`) + space-separated inner
   * descendant (`INNER_DESCENDANT`). When `INNER_DESCENDANT` is
   * empty the trailing space is stripped so `:hover` attaches to
   * the outer scope instead.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   * @param string $slug
   *   Kebab-case bundle slug (substituted as `%s`).
   * @param int $paragraphId
   *   Paragraph entity id (substituted as `%d`).
   *
   * @return string
   *   Selector string without trailing whitespace.
   */
  private function scopeSelector(string $bundle, string $slug, int $paragraphId): string {
    $outer = $this->outerScopeId($bundle, $slug, $paragraphId);
    $inner = self::INNER_DESCENDANT_OVERRIDES[$bundle] ?? self::INNER_DESCENDANT;
    $inner = trim($inner);
    return $inner === '' ? $outer : $outer . ' ' . $inner;
  }

  /**
   * Resolves the v1-compatible outer `#prefix-{id}{suffix}` selector.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   * @param string $slug
   *   Kebab-case bundle slug.
   * @param int $paragraphId
   *   Paragraph entity id.
   *
   * @return string
   *   Outer scope selector (includes leading `#`).
   */
  private function outerScopeId(string $bundle, string $slug, int $paragraphId): string {
    $prefix = self::OUTER_ID_PREFIX_OVERRIDES[$bundle] ?? ('pb__' . $slug);
    $suffix = self::OUTER_ID_SUFFIX_OVERRIDES[$bundle] ?? '';
    return sprintf('#%s-%d%s', $prefix, $paragraphId, $suffix);
  }

  /**
   * Build a single `--pb-x: value;` declaration.
   *
   * @param string $custom
   *   Custom property short name (e.g. `pb-bg`, `pb-tx`).
   * @param string $field
   *   Source field machine name. Determines RGBA wrapping.
   * @param string $value
   *   Raw stored value (hex like `#abcdef` or rgb triplet `0,0,0`).
   * @param string $opacity
   *   Pre-formatted opacity (`1` or `0.NN`).
   *
   * @return string
   *   Full declaration ending with `;`.
   */
  private function buildDeclaration(string $custom, string $field, string $value, string $opacity): string {
    // Fail closed. The widget's #element_validate does not run for config
    // import, migration, or REST/JSON:API writes, so an arbitrary string can
    // reach storage. A value that is not a well-formed colour has no valid
    // rendering — emit nothing rather than a scrubbed remnant.
    if (!$this->colorNormalizer->isSafeColorValue($value)) {
      return '';
    }
    $value = trim($value);
    if ($field === 'pb_display_bg') {
      return sprintf(
        '--%s: rgba(%s, %s);',
        $custom,
        $this->colorNormalizer->normaliseToRgbTriplet($value),
        $opacity,
      );
    }
    if (str_starts_with($value, '#')) {
      return sprintf('--%s: %s;', $custom, $value);
    }
    // Legacy widget output (raw triplet).
    return sprintf('--%s: rgb(%s);', $custom, $value);
  }

  /**
   * Read a single scalar field value as a string.
   *
   * **Numbers must be coerced, not rejected.** 47 of this module's 171
   * paragraph fields are integer, decimal, boolean or range storages, and an
   * integer column is an `int` on an entity whose value was just set
   * programmatically — it only becomes a numeric string after a round trip
   * through the database. `hook_paragraph_insert()` builds the stylesheet from
   * exactly that in-memory entity, so an `is_string()` gate here silently
   * dropped every numeric declaration for any paragraph created by a recipe,
   * a migration or a demo-content script, and left the file wrong until
   * somebody re-saved the paragraph through the form.
   *
   * The allowlist matches PbTwigExtension::cssValue(): numbers are coerced,
   * booleans / arrays / objects have no meaningful CSS spelling and emit
   * nothing, which keeps this path fail-closed the same way the colour path
   * is.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The fieldable entity.
   * @param string $field
   *   Field machine name.
   *
   * @return string
   *   Trimmed value, or empty string when missing/empty/not scalar.
   */
  private function fieldStringValue(FieldableEntityInterface $entity, string $field): string {
    if (!$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return '';
    }
    $item = $entity->get($field)->first();
    $raw = $item === NULL ? NULL : $item->getValue();
    $value = is_array($raw) ? ($raw['value'] ?? NULL) : NULL;
    if (is_int($value) || is_float($value)) {
      return (string) $value;
    }
    return is_string($value) ? trim($value) : '';
  }

}
