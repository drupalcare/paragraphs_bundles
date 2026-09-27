<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_3d_carousel\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_bundles\Utility\PbRenderArray;

/**
 * Attaches the geometry stylesheet in PHP so a template override cannot lose it.
 *
 * 🚨 THE REGRESSION THIS EXISTS TO PREVENT, AND IT IS A v1 UPGRADE PATH.
 *
 * Until v2 this bundle shipped ONE stylesheet, attached by its template. v2
 * splits it into a shared base plus one geometry layer (`-ring` or
 * `-coverflow`), and the template attaches the geometry layer with a line that
 * did not exist in v1:
 *
 * @code
 * {{ attach_library('…/paragraph-bundle-3d-carousel-' ~ pb_car_style) }}
 * @endcode
 *
 * A site that overrode `paragraph--3d-carousel-bundle.html.twig` in its theme
 * under v1 keeps serving ITS copy after the update. That copy still attaches
 * the base library — the name is unchanged — so the carousel renders and looks
 * broadly right, which is exactly why this is easy to miss. What it cannot
 * attach is the line above, so the ring silently loses its `transition`, its
 * `pointer-events` pair and its end-of-run arrow hiding. The single-library v1
 * arrangement could not fail this way: there was nothing for a stale template
 * to be stale ABOUT.
 *
 * So the geometry library is attached here too. `#attached` set in preprocess
 * is merged into the paragraph's render array regardless of which template
 * renders it, and Drupal de-duplicates libraries, so a current template and
 * this hook together attach it exactly once.
 *
 * This mirrors the reasoning already recorded in
 * \Drupal\paragraphs_bundles\Hook\PbPreprocessHook, which seeds the base
 * classes in preprocess "not only the template … so they survive a theme's
 * paragraph-template override". Same failure, same remedy; the template keeps
 * its attach because that is where a reader looks first.
 *
 * @see \Drupal\paragraphs_bundles\Hook\PbPreprocessHook
 */
final class Pb3dcLibraryHook {

  /**
   * The bundle this hook owns.
   */
  private const BUNDLE = '3d_carousel_bundle';

  /**
   * Field holding the chosen geometry.
   */
  private const STYLE_FIELD = 'pb_display_3d_car_style';

  /**
   * The geometry used when the field is empty, absent or unrecognised.
   *
   * Load-bearing, for the same reason the template carries the same default: a
   * field default applies only when an entity is CREATED, so every carousel
   * that predates the field holds an empty value and must still render as the
   * ring it has always been.
   */
  private const DEFAULT_STYLE = 'ring';

  /**
   * Every geometry that has a stylesheet of its own.
   */
  private const STYLES = ['ring', 'coverflow'];

  /**
   * Implements hook_preprocess_HOOK() for paragraph.
   *
   * @param array<string, mixed> $variables
   *   Paragraph preprocess variables, by reference.
   */
  #[Hook('preprocess_paragraph')]
  public function preprocessParagraph(array &$variables): void {
    $paragraph = $variables['paragraph'] ?? NULL;
    if (!$paragraph instanceof ParagraphInterface) {
      return;
    }
    if ($paragraph->bundle() !== self::BUNDLE) {
      return;
    }

    // PbRenderArray, not `$variables['#attached']['library'][] = …`. Hooks
    // receive `array<string, mixed>`, so that hand-written line reads an
    // offset on a `mixed` intermediate: PHPStan rejects it at level max, and
    // at runtime it fatals whenever something non-array already sits there.
    // The utility is the one place that knows how to widen the intermediate,
    // and this module already depends on paragraphs_bundles.
    PbRenderArray::attachLibraries(
      $variables,
      'paragraph_bundle_3d_carousel/paragraph-bundle-3d-carousel-' . $this->styleOf($paragraph),
    );
  }

  /**
   * Resolves a paragraph's geometry to one with a stylesheet.
   *
   * The value is bounded by the field's allowed values, but it is checked
   * rather than trusted: a stray value would otherwise ask Drupal for a
   * library that does not exist.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The carousel paragraph.
   *
   * @return string
   *   Either `ring` or `coverflow`.
   */
  private function styleOf(ParagraphInterface $paragraph): string {
    if (!$paragraph->hasField(self::STYLE_FIELD)) {
      return self::DEFAULT_STYLE;
    }

    // Read through isEmpty()/first()/getValue(), mirroring
    // PbDynamicUserVarBuilder::fieldStringValue() — this module's canonical
    // way to take a single string off a field.
    //
    // ⚠ NOT `->value`, which is magic and cannot be stubbed without a dynamic
    // property, and NOT `getString()`. Both read correctly at runtime; the
    // reason is the pure-Unit suite. Its bootstrap supplies minimal stub
    // interfaces whenever Drupal core is unreachable, and those stubs declare
    // isEmpty(), first() and getValue() but NOT getString() — so a test of a
    // getString() call passes wherever core happens to be present and dies
    // with "method cannot be configured because it does not exist" in the
    // bootstrap-free run the stubs exist to serve. A test that only passes on
    // one of the two runs is worse than none.
    $items = $paragraph->get(self::STYLE_FIELD);
    if ($items->isEmpty()) {
      return self::DEFAULT_STYLE;
    }

    $item = $items->first();
    $raw = $item === NULL ? NULL : $item->getValue();
    $value = is_array($raw) ? ($raw['value'] ?? NULL) : NULL;
    $style = is_string($value) ? trim($value) : '';

    return in_array($style, self::STYLES, TRUE) ? $style : self::DEFAULT_STYLE;
  }

}
