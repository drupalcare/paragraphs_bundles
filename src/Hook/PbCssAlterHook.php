<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs_bundles\Service\Css\PbDynamicLibraryRegistrarInterface;

/**
 * CSS-alter hook — pins PB stylesheets in the correct cascade order.
 *
 * Per-paragraph dynamic files (`public://paragraphs_bundles/pb-*.css`)
 * are moved into the theme aggregate group at
 * {@see PbDynamicLibraryRegistrarInterface::ASSET_WEIGHT} so they load
 * after theme CSS and can override host styles (same pattern as
 * apex_color region-colors and apex_injector dynamic CSS).
 *
 * Foundation stylesheets keep a moderate weight so they load early. PB
 * CSS is un-layered (no `@layer` ladder), so cascade ordering relies on
 * asset weight and specificity, not layers.
 */
final class PbCssAlterHook {

  /**
   * Filename of the foundation base stylesheet.
   */
  private const string BASE_FILENAME = 'paragraphs-bundles-base.css';

  /**
   * Filename of the foundation custom stylesheet.
   */
  private const string CUSTOM_FILENAME = 'paragraphs-bundles-custom.css';

  /**
   * Prefix for theme-bridge stylesheets.
   */
  private const string BRIDGE_FILENAME_PREFIX = 'pb-theme-bridge-';

  /**
   * Implements hook_css_alter().
   *
   * @param array<string, array<string, mixed>> $css
   *   Asset definitions keyed by file path.
   */
  #[Hook('css_alter')]
  public function cssAlter(array &$css): void {
    foreach ($css as $key => &$asset) {
      if ($this->isPerParagraphDynamicCss($key, $asset)) {
        $asset['group'] = CSS_AGGREGATE_THEME;
        $asset['weight'] = (float) PbDynamicLibraryRegistrarInterface::ASSET_WEIGHT;
        continue;
      }
      if (!is_string($key)) {
        continue;
      }
      $name = basename($key);
      if ($name === self::BASE_FILENAME
        || $name === self::CUSTOM_FILENAME
        || str_starts_with($name, self::BRIDGE_FILENAME_PREFIX)) {
        $asset['weight'] = 100.0;
      }
    }
  }

  /**
   * Whether a CSS asset is a per-paragraph dynamic file.
   *
   * Files live at `public://paragraphs_bundles/pb-{slug}-{id}.css` and
   * are keyed by their public URL in the asset collection.
   *
   * @param string|int $key
   *   Asset collection key.
   * @param array<string, mixed> $asset
   *   Asset definition.
   *
   * @return bool
   *   TRUE for per-paragraph dynamic CSS.
   */
  private function isPerParagraphDynamicCss(string|int $key, array $asset): bool {
    $paths = [];
    if (is_string($key)) {
      $paths[] = $key;
    }
    if (isset($asset['data']) && is_string($asset['data'])) {
      $paths[] = $asset['data'];
    }
    foreach ($paths as $path) {
      $name = basename($path);
      if (str_starts_with($name, 'pb-') && str_ends_with($name, '.css')) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
