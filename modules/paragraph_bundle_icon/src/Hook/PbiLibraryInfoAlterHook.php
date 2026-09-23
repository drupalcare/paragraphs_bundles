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
 * 4,284 icons. This ADDS a Google Fonts stylesheet naming only the icons this
 * site actually uses — measured on the maintainer's site, the two Outlined
 * icons stored there return 3,156 bytes.
 *
 * THE BUNDLED STYLESHEET STAYS, AND KEEPING IT COSTS NOTHING. An earlier
 * version of this hook `unset()` the local path, on the assumption that two
 * `@font-face` rules for one family would make the browser download both
 * fonts. MEASURED IN CHROME 2026-09-23, AND THAT ASSUMPTION IS FALSE. The
 * browser requests only the LAST declared face, and requests an earlier one
 * ONLY when the last one fails:
 *
 * - Two valid faces, 3.41 MB declared then 4.64 MB: ONE network request, for
 *   the second. The first is never fetched; its status stays `unloaded`.
 * - A valid face then a 404: the 404 is attempted first and goes to `error`,
 *   and the browser THEN fetches the earlier face and paints with it.
 *
 * So appending the subset keeps the 374x saving on the happy path AND restores
 * a working fallback for sites that cannot reach Google. Removing the local
 * key also removed the class rules that share that file — the
 * `.material-symbols-*` block here carries `!important` and Google's
 * stylesheet does not — so the old swap silently changed the cascade too.
 *
 * ⚠ Measured in Chrome only. Other engines are unverified.
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
 *
 * ORDER IS LOAD-BEARING. The subset only wins the family while it renders
 * AFTER the bundled `@font-face`, so it is appended carrying a weight one
 * above the local file's rather than left to the stability of core's sort.
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
    // group maps a file PATH to its options array — so a source is a KEY, and
    // adding one means adding a key rather than assigning to one.
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

        // The local key is KEPT deliberately — see the class docblock. It is
        // the fallback when Google is unreachable, and it carries the
        // `.material-symbols-*` class rules, which Google's stylesheet does
        // not replace like for like.
        $options = is_array($options) ? $options : [];

        // Render the subset after the bundled `@font-face`, so it is the last
        // declared face for the family and therefore the one fetched.
        $weight = $options['weight'] ?? 0;
        $options['weight'] = is_int($weight) || is_float($weight) ? $weight + 1 : 1;

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
