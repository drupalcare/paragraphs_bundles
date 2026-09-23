<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit\Security;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Pins the inline-asset surface this project presents to a strict CSP.
 *
 * WHY THIS TEST EXISTS. A site running a strict CSP forbids inline code, so
 * every inline <script>, on* handler or style attribute a module emits forces
 * the site owner to weaken the policy back to 'unsafe-inline' — which is most
 * of the protection gone. Issue #3620924 asked whether this project makes that
 * necessary. The answer, measured, is that it emits NO inline JavaScript at
 * all; what it emits is a small, known set of inline CSS.
 *
 * That answer was true on the day it was measured and nothing enforced it.
 * A single `'#tag' => 'script'` with a `#value` added later would silently
 * make the project unusable under a strict policy, and nobody would find out
 * until a user reported it. This test is the enforcement.
 *
 * The two halves are deliberately asymmetric:
 *
 * - Inline SCRIPT is absolute: zero, everywhere, no allowlist. There has
 *   never been a reason for this project to inline JavaScript, and a change
 *   that needs to should be argued rather than merged.
 * - Inline STYLE is an allowlist, because a handful of genuinely per-paragraph
 *   values still ride on style attributes. The allowlist stops the count
 *   creeping back up silently: adding one is a conscious edit to this file,
 *   which is where a reviewer will see it.
 *
 * NOTE ON WHAT DOES *NOT* COUNT. `<script src="...">` is a source URL governed
 * by `script-src`, not inline code, and JavaScript assigning `element.style`
 * at runtime is a CSSOM write that CSP does not police. Neither is a finding.
 *
 * @group paragraphs_bundles
 */
#[Group('paragraphs_bundles')]
final class PbInlineAssetSurfaceTest extends TestCase {

  /**
   * Files permitted to carry inline `style="..."` attributes, and how many.
   *
   * Every entry is a per-paragraph value that varies per entity, which is why
   * it cannot live in a static stylesheet. The long-term home for these is the
   * generated per-paragraph CSS file that PbCssFileManager already writes for
   * the custom properties — moving them there removes them from this list.
   *
   * A site enforcing CSP needs `style-src-attr 'unsafe-inline'` for these.
   * A nonce cannot help: nonces authorise <style> ELEMENTS, never attributes.
   *
   * SHRINKING, NOT GROWING — the log of what has left this list:
   * - 2026-09-20, `3d_flip_box_bundle`: the scene height moved into the
   *   generated per-paragraph CSS via PbDynamicUserVarBuilder::USER_STYLE_SCOPES.
   *   Same field (`pb_display_3d_height`), same `px` transform, same rendered
   *   result; only the transport changed. The `{% if %}` guard the template
   *   carried is not lost — build() skips a rule whose value resolves empty.
   *   Covered by PbDynamicUserVarBuilderTest::testFlipBoxHeightOnScene and
   *   ::testFlipBoxWithoutHeightEmitsNoCss, because this test proves only that
   *   the attribute is gone, never that the value still arrives.
   * - 2026-09-20, `parallax_section_bundle`: all three moved — the section
   *   height onto `> .pb-parallax-section__inner`, and the background image
   *   plus the three animation custom properties onto ` .pb-parallax__bg`,
   *   matching the elements the template used so the cascade is unchanged.
   *   Every piece of wiring already existed and had never been connected: the
   *   `parallax_section_height` and `seconds` transforms and the
   *   `parallax_not_legacy` guard were all written for this bundle and left
   *   unreferenced. ⚠ That guard had a bug — it read `!== 'legacy'`, which is
   *   TRUE for an unset field, and every parallax section on the test site has
   *   it unset. Covered by PbDynamicUserVarBuilderTest's five parallax tests,
   *   including the unset-scroll case specifically.
   *
   * - 2026-09-20, `image_overlay_bundle` AND `templates/pb-content-title`:
   *   both in one change, because the title template's `title_style` was fed
   *   only by the overlay. The four colour custom properties now land on the
   *   paragraph wrapper via PbDynamicUserVarBuilder, and the existing
   *   stylesheet rules — `:not(.pb-image-overlay--title) .pb-overlay__container`
   *   and `.pb-image-overlay--title … :hover .pb-overlay__title` — pick them
   *   up by inheritance. That removed the PHP mode-routing entirely:
   *   `preprocessImageOverlayInlineColors()` and `OVERLAY_INLINE_COLOR_KEYS`
   *   are gone.
   *
   * 🚨 **AN EMPTY LIST HERE DOES NOT MEAN PB EMITS NO INLINE STYLES.** This
   * test scans TWIG ONLY. `ColorSwatchFormatter.php` emits four inline styles
   * from PHP render arrays (`'style' => '…'` in `#attributes`) and no guard
   * covers them. Extend this test to PHP before treating an empty allowlist as
   * a clean bill of health.
   *
   * @var array<string, int>
   */
  private const ALLOWED_INLINE_STYLE = [];

  /**
   * Extensions that can carry markup or emit it.
   *
   * @var list<string>
   */
  private const MARKUP_EXTENSIONS = ['twig', 'php', 'module', 'inc', 'theme', 'html'];

  /**
   * No inline JavaScript is emitted, in any form, anywhere.
   */
  public function testNoInlineScriptIsEmitted(): void {
    $findings = [];

    foreach ($this->sourceFiles(self::MARKUP_EXTENSIONS) as $relative => $source) {
      // A literal opening script tag in markup.
      if (preg_match('/<script\b/i', $source)) {
        $findings[] = "$relative: literal <script> tag in markup";
      }
      // A render array building a script tag that carries a body. The `src`
      // form is legitimate — it is an external reference, not inline code —
      // so only a '#value' alongside '#tag' => 'script' is a finding.
      if (preg_match("/'#tag'\s*=>\s*'script'/", $source)
        && preg_match("/'#tag'\s*=>\s*'script'.{0,400}?'#value'/s", $source)) {
        $findings[] = "$relative: render array inlines script content via '#value'";
      }
      // Inline event handler attributes.
      if (preg_match('/\son(?:click|load|change|submit|error|mouseover|mouseout|focus|blur|keyup|keydown|input)\s*=\s*["\']/i', $source)) {
        $findings[] = "$relative: inline on* event handler attribute";
      }
    }

    foreach ($this->sourceFiles(['js']) as $relative => $source) {
      if (preg_match('/\bnew\s+Function\s*\(|(?<![\w.$])eval\s*\(/', $source)) {
        $findings[] = "$relative: eval() or new Function()";
      }
    }

    self::assertSame(
      [],
      $findings,
      "This project must emit no inline JavaScript, so that a site can run a\n"
      . "strict script-src without 'unsafe-inline'. Found:\n - "
      . implode("\n - ", $findings),
    );
  }

  /**
   * PHP files that build inline `style` attributes, and how many.
   *
   * SEPARATE FROM THE TWIG LIST ON PURPOSE. The two scans look for different
   * syntax — `style="` in markup versus `'style' => '…'` in a render array —
   * carry different false-positive risks, and fail for different reasons. One
   * merged assertion would leave a future reader unable to tell which kind of
   * leak had appeared.
   *
   * 🚨 THIS LIST EXISTS BECAUSE THE TWIG SCAN ALONE WAS NOT ENOUGH. Until
   * 2026-09-21 this test scanned Twig only, so these four were invisible while
   * the Twig allowlist read "empty" — which looked like a clean bill of health
   * the project had not earned.
   *
   * `ColorSwatchFormatter` is unlike the template cases: three of its four are
   * entirely static and belong in a stylesheet, but the fourth carries an
   * arbitrary user-chosen hex, and a field formatter has no per-entity
   * generated CSS file to put it in. Awaiting the maintainer's decision —
   * split, delete (it is assigned to zero displays and two sibling formatters
   * cover the same field type), or keep and document.
   *
   * @var array<string, int>
   */
  private const ALLOWED_INLINE_STYLE_PHP = [
    'src/Plugin/Field/FieldFormatter/ColorSwatchFormatter.php' => 4,
  ];

  /**
   * Inline style attributes built in PHP stay on the PHP allowlist.
   *
   * The regex was red-proofed against this project before being trusted:
   * `'style' =>` matches exactly the four render-array attributes and none of
   * the near misses — `$form['choices']['style']` is bracket access and
   * `$form_state->getValue('style')` is a method argument, neither of which is
   * array-key syntax. The double-quoted spelling is matched too, although
   * nothing uses it today: a guard that only catches the spelling already in
   * the codebase is half a guard.
   */
  public function testInlineStylePhpAttributesStayOnTheAllowlist(): void {
    $found = [];

    foreach ($this->sourceFiles(['php', 'module', 'inc', 'theme']) as $relative => $source) {
      $count = preg_match_all('/[\'"]style[\'"]\s*=>/', $source);

      if ($count > 0) {
        $found[$relative] = $count;
      }
    }

    ksort($found);
    $expected = self::ALLOWED_INLINE_STYLE_PHP;
    ksort($expected);

    self::assertSame(
      $expected,
      $found,
      "The set of PHP files building inline style attributes changed.\n"
      . "A render array writing `'style' => '…'` into `#attributes` produces\n"
      . "exactly the same attribute a template would, and a CSP-enforcing site\n"
      . "must allow it through style-src-attr — which cannot be narrowed by a\n"
      . "nonce. Move the value into a stylesheet, or into the entity's\n"
      . 'generated CSS, and update ALLOWED_INLINE_STYLE_PHP here if you cannot.',
    );
  }

  /**
   * Inline style attributes appear only where they are already accounted for.
   */
  public function testInlineStyleAttributesStayOnTheAllowlist(): void {
    $found = [];

    foreach ($this->sourceFiles(['twig']) as $relative => $source) {
      // Twig comments describe the OLD v1 behaviour in places, and
      // `data-col-icon-style="..."` is a data attribute that merely ends in
      // the same characters. Neither reaches the browser as a style attribute.
      $stripped = (string) preg_replace('/\{#.*?#\}/s', '', $source);
      $count = preg_match_all('/(?<![-\w])style\s*=\s*"/', $stripped);

      if ($count > 0) {
        $found[$relative] = $count;
      }
    }

    ksort($found);
    $expected = self::ALLOWED_INLINE_STYLE;
    ksort($expected);

    self::assertSame(
      $expected,
      $found,
      "The set of templates carrying inline style attributes changed.\n"
      . "Adding one widens what a CSP-enforcing site must allow via\n"
      . "style-src-attr, so it is a deliberate decision: move the value into\n"
      . "the generated per-paragraph CSS file if you can, and update\n"
      . 'ALLOWED_INLINE_STYLE in this test if you genuinely cannot.',
    );
  }

  /**
   * Exactly one sink inlines a <style> element, and it is the documented one.
   *
   * PbPreprocessHook falls back to an inline <style> block only when the
   * generated per-paragraph CSS file is missing; normally the file exists and
   * is attached as a library, which the CSP module's Libraries API
   * integration allows automatically. One sink is reviewable. Several,
   * scattered, is how a project ends up needing style-src 'unsafe-inline'
   * with nobody able to say why.
   */
  public function testExactlyOneInlineStyleSinkExists(): void {
    $sinks = [];

    foreach ($this->sourceFiles(['php', 'module', 'inc', 'theme']) as $relative => $source) {
      $count = preg_match_all("/'#tag'\s*=>\s*'style'/", $source);
      if ($count > 0) {
        $sinks[$relative] = $count;
      }
    }

    self::assertSame(
      ['src/Hook/PbPreprocessHook.php' => 1],
      $sinks,
      'The inline <style> fallback should exist in exactly one reviewable place.',
    );
  }

  /**
   * Directories and root files that belong to THIS project.
   *
   * WHY AN EXPLICIT LIST RATHER THAN "everything under the project root".
   * Measured in CI, 2026-09-06: the drupal.org pipeline installs Drupal INSIDE
   * the project directory, at `web/`. So walking the project root walked all
   * of core and every contrib module with it, and the first run of this test
   * dutifully reported core's own inline scripts — BigPipe, the filter module,
   * navigation templates — as findings against this project. Locally there is
   * no `web/`, so it passed here and failed there.
   *
   * Naming this project's own directories is immune to whatever else appears
   * in the root, now or later.
   *
   * @var list<string>
   */
  private const PROJECT_PATHS = ['src', 'templates', 'modules', 'js', 'css', 'config'];

  /**
   * Yields shipped source files, keyed by path relative to the project root.
   *
   * Tests are excluded deliberately: fixtures here assert that markup like
   * `</style><script>alert(1)</script>` is escaped, and a scanner that
   * flagged its own fixtures would be useless.
   *
   * @param list<string> $extensions
   *   Lower-case extensions to include.
   *
   * @return \Generator<string, string>
   *   Relative path => file contents.
   */
  private function sourceFiles(array $extensions): \Generator {
    $root = dirname(__DIR__, 4);
    $seen = 0;

    foreach (self::PROJECT_PATHS as $dir) {
      $base = $root . '/' . $dir;
      if (!is_dir($base)) {
        continue;
      }
      $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
      );
      foreach ($iterator as $file) {
        $path = $file->getPathname();
        if (!in_array(strtolower($file->getExtension()), $extensions, TRUE)) {
          continue;
        }
        foreach (['/tests/', '/node_modules/', '/.git/', '/vendor/'] as $skip) {
          if (str_contains($path, $skip)) {
            continue 2;
          }
        }
        $seen++;
        yield str_replace($root . '/', '', $path) => (string) file_get_contents($path);
      }
    }

    // Root-level files only, never a recursive walk of the root — see
    // PROJECT_PATHS for what a recursive walk picked up in CI.
    foreach ((array) scandir($root) as $entry) {
      $path = $root . '/' . $entry;
      if (!is_file($path)) {
        continue;
      }
      $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
      if (!in_array($ext, $extensions, TRUE)) {
        continue;
      }
      $seen++;
      yield $entry => (string) file_get_contents($path);
    }

    // A scanner that reaches nothing and a project that is clean look
    // identical from the outside. Fail loudly instead.
    self::assertGreaterThan(
      5,
      $seen,
      sprintf(
        'The scan reached only %d file(s) of type [%s] under %s, which means it '
        . 'is not looking where it thinks it is. A pass here would be vacuous.',
        $seen,
        implode(', ', $extensions),
        $root,
      ),
    );
  }

}
