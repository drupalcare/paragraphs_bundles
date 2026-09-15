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
   * @var array<string, int>
   */
  private const ALLOWED_INLINE_STYLE = [
    'modules/paragraph_bundle_3d_flip_box/templates/paragraph--3d-flip-box-bundle.html.twig' => 1,
    'modules/paragraph_bundle_image_overlay/templates/paragraph--image-overlay-bundle.html.twig' => 1,
    'modules/paragraph_bundle_parallax/templates/paragraph--parallax-section-bundle.html.twig' => 3,
    'templates/pb-content-title.html.twig' => 1,
  ];

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
