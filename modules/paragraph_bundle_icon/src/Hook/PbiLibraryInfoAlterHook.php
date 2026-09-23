<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_icon\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraph_bundle_icon\Service\PbiFontUrlBuilderInterface;
use Drupal\paragraph_bundle_icon\Service\PbiIconUsageInterface;

/**
 * Points the Material Symbols libraries at a subset of the icons in use.
 *
 * WHAT THIS CHANGES, in one number. `css/material-symbols-outlined.css`
 * declares an `@font-face` for a bundled 3,411,436-byte file carrying all
 * 4,284 icons. This rewrites that one asset to a Google Fonts URL naming only
 * the icons this site actually uses — measured on the maintainer's site, the
 * two Outlined icons stored there return 3,156 bytes.
 *
 * WHY AN ALTER AND NOT A NEW LIBRARY. The template attaches
 * `paragraph_bundle_icon/paragraph-bundle-icon-{style}` and the `.yml` declares
 * it. Introducing a second library name would mean changing both, and would
 * risk Drupal's "attempted to attach a non-existent library" on any request
 * where the library cache had not yet rebuilt. Altering the asset in place
 * leaves the template, the `.libraries.yml` and every attach site untouched:
 * same library, same name, different font URL.
 *
 * THE SHAPE HERE IS THE YAML ONE, NOT THE BUILT ONE, AND GETTING THAT BACKWARDS
 * COST A ROUND OF THIS WORK. `LibraryDiscoveryParser::buildByExtension()` calls
 * `parseLibraryInfo()` at line 82, and the alter fires inside it at line 343 —
 * BEFORE the loop starting at line 85 that rewrites each stylesheet into an
 * asset array carrying `data`, `type` and `group`. So what arrives here is
 * still `css => [group => [path => options]]`, with the file path as the KEY
 * and no `data` key anywhere.
 *
 * The first version of this hook matched on `$asset['data']`, because a
 * `var_export()` of the FINAL library from `getLibraryByName()` was mistaken
 * for the hook's input. It matched nothing and returned silently: no error, the
 * suite green, PHPCS clean, the page rendering — and the 3.4 MB font still
 * loading. Nothing caught it except asking `library.discovery` afterwards what
 * it actually returned. A control did not help, because the only other
 * library_info_alter in this project touches `dependencies`, a key that is
 * identical in both shapes.
 *
 * FAILING SAFE IS THE WHOLE DESIGN. Every branch that cannot produce a good
 * URL leaves the asset alone, so the bundled font on disk keeps rendering.
 * That is what separates this from the change reverted in 4ea0ce5, which
 * removed the fonts outright and turned every icon into its ligature name in
 * plain text on upgrade.
 */
final class PbiLibraryInfoAlterHook {

  /**
   * The extension whose libraries this hook may touch.
   */
  private const string EXTENSION = 'paragraph_bundle_icon';

  public function __construct(
    private readonly PbiIconUsageInterface $usage,
    private readonly PbiFontUrlBuilderInterface $urls,
  ) {}

  /**
   * Implements hook_library_info_alter().
   *
   * @param array<string, array<string, mixed>> $libraries
   *   Library definitions for the extension being altered.
   * @param string $extension
   *   Module or theme extension name.
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $extension): void {
    if ($extension !== self::EXTENSION) {
      return;
    }

    $byStyle = $this->usage->iconsByStyle();
    if ($byStyle === []) {
      return;
    }

    foreach ($byStyle as $style => $icons) {
      $this->repointStyle($libraries, (string) $style, $icons);
    }
  }

  /**
   * Repoint one style's stylesheet at its subset URL.
   *
   * @param array<string, array<string, mixed>> $libraries
   *   Library definitions, by reference.
   * @param string $style
   *   Style key: `outlined`, `rounded` or `sharp`.
   * @param list<string> $icons
   *   Validated icon names for this style.
   */
  private function repointStyle(array &$libraries, string $style, array $icons): void {
    $key = 'paragraph-bundle-icon-' . $style;

    $groups = $libraries[$key]['css'] ?? NULL;
    if (!is_array($groups)) {
      return;
    }

    $url = $this->urls->buildUrl($style, $icons);
    if ($url === '') {
      return;
    }

    // Match by filename rather than by position. The library carries one
    // stylesheet today, and a positional match would silently rewrite whatever
    // happened to be first if that ever changed.
    $needle = 'material-symbols-' . $style . '.css';

    // `css` is keyed by CSS group (`theme`, `component`, `base`, …), and each
    // group maps a file PATH to its options array. Swapping the source means
    // replacing the key, not assigning to one.
    // Mutate the local, typed copy and write it back once. Writing through
    // `$libraries[$key]['css'][$group][$path]` reaches offsets on a `mixed`
    // intermediate, which is unchecked at every level.
    $changed = FALSE;

    foreach ($groups as $group => $files) {
      if (!is_array($files)) {
        continue;
      }

      foreach ($files as $path => $options) {
        if (!is_string($path) || !str_ends_with($path, $needle)) {
          continue;
        }

        unset($groups[$group][$path]);

        $options = is_array($options) ? $options : [];
        // Without this the parser treats the URL as a file path relative to
        // the module and the stylesheet 404s. See LibraryDiscoveryParser:168.
        $options['type'] = 'external';

        $groups[$group][$url] = $options;
        $changed = TRUE;
      }
    }

    if ($changed) {
      $libraries[$key]['css'] = $groups;
    }
  }

}
