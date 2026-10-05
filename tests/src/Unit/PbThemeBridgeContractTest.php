<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Guards the theme-bridge colour contract against silent drift.
 *
 * THE CONTRACT. The host theme supplies a default for a paragraph's colours;
 * any of the eight editor colour fields that is set takes over, PER FIELD,
 * for that paragraph and everything inside it.
 *
 * WHY THIS EXISTS. Two defects were measured on 2026-09-12, on a test site
 * node 317 under apex_theme, and neither made anything go red:
 *
 * 1. Every bridge mapped its tokens with a bare `:where(.paragraphs-bundles)`,
 *    which matches NESTED paragraphs too. Re-declaring the token on a child
 *    discards the value it would have inherited from an ancestor the editor
 *    styled, so the theme beat the editor on 39 nested paragraphs. Zero
 *    specificity does not help: on the nested element nothing competes, so
 *    the bridge is unopposed.
 *
 * 2. APEX repaints `color` on nearly every element rather than letting it
 *    inherit. PB owns no colour rule on `.pb-content__full` or `.field__item`,
 *    so inheritance from the editor-styled `.pb-ui-inner` was severed at the
 *    very next `div` - the token stayed correct while the paint did not.
 *
 * Both are invisible to a passing suite and to an HTTP 200. This test is what
 * stops them coming back.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
class PbThemeBridgeContractTest extends TestCase {

  /**
   * The bridge stylesheets, and the host variables each one reads.
   *
   * @var array<string, list<string>>
   */
  private const array BRIDGES = [
    'pb-theme-bridge-apex.css' => ['--r-text', '--r-border', '--r-link'],
    'pb-theme-bridge-solo.css' => ['--r-tx', '--r-br', '--r-lk'],
    'pb-theme-bridge-neutral.css' => ['CanvasText', 'currentColor', 'LinkText'],
  ];

  /**
   * Tokens a bridge may map. The other five are editor-only.
   *
   * `--pb-bg` and every `-h` hover token are never bridged: a paragraph must
   * not repaint the surface its region already owns, and hover consumption
   * already falls back to the bridged base token.
   *
   * @var list<string>
   */
  private const array BRIDGEABLE = ['--pb-tx', '--pb-br', '--pb-lk'];

  /**
   * Tokens a bridge must never map outside the forced-colors block.
   *
   * @var list<string>
   */
  private const array EDITOR_ONLY = [
    '--pb-bg',
    '--pb-bg-h',
    '--pb-tx-h',
    '--pb-br-h',
    '--pb-lk-h',
  ];

  /**
   * Returns the bridge directory.
   */
  private function dir(): string {
    return dirname(__DIR__, 3) . '/css/bridge';
  }

  /**
   * Returns a bridge stylesheet with comments stripped.
   */
  private function css(string $file): string {
    $path = $this->dir() . '/' . $file;
    $this->assertFileExists($path, "Bridge stylesheet is missing: $file");
    $raw = (string) file_get_contents($path);
    return (string) preg_replace('!/\*.*?\*/!s', '', $raw);
  }

  /**
   * Returns a bridge with its `@media` blocks removed.
   *
   * The forced-colors block legitimately re-declares tokens on every
   * paragraph - accessibility overrides are meant to reach nested ones too -
   * so the scoping rules below apply to the unconditional part only.
   */
  private function unconditional(string $file): string {
    $css = $this->css($file);
    // Remove `@media ... { ... }` including one level of nested braces.
    return (string) preg_replace('/@media[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/s', '', $css);
  }

  /**
   * A bridge maps tokens on the OUTERMOST paragraph only.
   */
  public function testBridgesScopeTokenMappingToOutermostParagraph(): void {
    foreach (array_keys(self::BRIDGES) as $file) {
      $css = $this->unconditional($file);

      $this->assertMatchesRegularExpression(
        '/:where\(\s*\.paragraphs-bundles:not\(\s*\.paragraphs-bundles\s+\.paragraphs-bundles\s*\)\s*\)/',
        $css,
        sprintf(
          "%s no longer scopes its token mapping to the outermost paragraph.\n\n"
          . "Expected `:where(.paragraphs-bundles:not(.paragraphs-bundles "
          . ".paragraphs-bundles))`.\n\nA bare `.paragraphs-bundles` matches "
          . "NESTED paragraphs too, and re-declaring a token on a child "
          . "discards the value it would have inherited from an ancestor the "
          . "editor styled -- so the theme beats the editor, which is the "
          . "opposite of the contract. Zero specificity does not save it: on "
          . "the nested element nothing competes with the bridge.",
          $file
        )
      );

      // The bare form must not appear anywhere in the unconditional CSS.
      $bare = preg_replace('/:where\(\s*\.paragraphs-bundles:not\([^)]*\)\s*\)/', '', $css);
      $this->assertDoesNotMatchRegularExpression(
        '/:where\(\s*\.paragraphs-bundles\s*\)/',
        (string) $bare,
        sprintf(
          "%s still contains a bare `:where(.paragraphs-bundles)` selector.\n\n"
          . "That form matches nested paragraphs and resets their inherited "
          . "editor colours. Scope it with "
          . "`:not(.paragraphs-bundles .paragraphs-bundles)`.",
          $file
        )
      );
    }
  }

  /**
   * A bridge maps only the three bridgeable tokens, never the editor-only five.
   */
  public function testBridgesNeverMapEditorOnlyTokens(): void {
    foreach (array_keys(self::BRIDGES) as $file) {
      $css = $this->unconditional($file);
      foreach (self::EDITOR_ONLY as $token) {
        // Match the token only where it is being DECLARED (`--pb-bg:`), not
        // where it is read inside a `var()`.
        $this->assertDoesNotMatchRegularExpression(
          '/' . preg_quote($token, '/') . '\s*:/',
          $css,
          sprintf(
            "%s declares `%s`, which is editor-only and must never be "
            . "bridged.\n\nSurface (`--pb-bg`) and hover (`-h`) tokens belong "
            . "to the editor alone: a paragraph must not repaint the surface "
            . "its region already owns, and hover consumption already falls "
            . "back to the bridged base token, e.g. "
            . "`var(--pb-tx-h, var(--pb-tx))`.",
            $file,
            $token
          )
        );
      }
      foreach (self::BRIDGEABLE as $token) {
        $this->assertMatchesRegularExpression(
          '/' . preg_quote($token, '/') . '\s*:/',
          $css,
          sprintf('%s no longer maps `%s`.', $file, $token)
        );
      }
    }
  }

  /**
   * Each bridge reads the host variables its theme family actually publishes.
   */
  public function testEachBridgeReadsItsOwnHostVariables(): void {
    foreach (self::BRIDGES as $file => $hostVars) {
      $css = $this->unconditional($file);
      foreach ($hostVars as $var) {
        $this->assertStringContainsString($var, $css, sprintf(
          "%s no longer reads `%s`.\n\nThe bridge is the only thing that "
          . "knows the host's variable names. Nothing else in the module does, "
          . "and nothing goes red in the browser when a host renames one -- "
          . "the token simply resolves to nothing.",
          $file,
          $var
        ));
      }
    }
  }

  /**
   * Elements each host was MEASURED to repaint, and so each bridge counters.
   *
   * A bridge gets a `color: inherit` counter-rule only for a host that was
   * observed repainting `color` on elements PB owns no rule for. Both hosts
   * measured so far do it, by different means and at different specificity:
   *
   *   APEX (0,1,1): `.apex-page :where(p, span, div, li, td, th, small)
   *                  :not(:has(svg))` plus `.field, .views-field, .block`
   *   Solo (0,0,0): `:where(.page-wrapper) :where(img, code, pre, kbd, samp,
   *                  p, hr, ul, ol, li, table, th, td, tr, blockquote)`
   *
   * Specificity is not what decides it. On these elements PB owns no colour
   * rule at all, so even Solo's (0,0,0) declaration is unopposed and beats
   * plain inheritance from the editor-styled `.pb-ui-inner`.
   *
   * Only elements paragraphs-bundles-custom.css does not already cover are
   * listed. It handles the headings plus `code, kbd, samp, pre, li,
   * blockquote, cite, q, mark, dt, dd, figcaption, summary` at (0,2,0).
   *
   * NULL means no counter-rule: the host was never measured blanket-
   * repainting, so forcing inheritance would shadow its legitimate intent
   * (e.g. `.warning span { color: red }`).
   *
   * @var array<string, list<string>|null>
   */
  private const array COUNTERED_ELEMENTS = [
    'pb-theme-bridge-apex.css' => ['p', 'span', 'div', 'li', 'td', 'th', 'small'],
    'pb-theme-bridge-solo.css' => ['p', 'hr', 'ul', 'ol', 'table', 'th', 'td', 'tr'],
    'pb-theme-bridge-neutral.css' => NULL,
  ];

  /**
   * A bridge counters exactly what its host was measured to repaint.
   */
  public function testBridgesCounterTheirHostsColourRepaint(): void {
    foreach (self::COUNTERED_ELEMENTS as $file => $elements) {
      $css = $this->unconditional($file);

      if ($elements === NULL) {
        $this->assertStringNotContainsString('color: inherit', $css, sprintf(
          "%s carries a `color: inherit` counter-rule, but no host of this "
          . "family has been measured blanket-repainting.\n\nThat rule exists "
          . "only to undo a host that repaints elements PB owns no rule for. "
          . "Applied to a theme that lets colour inherit naturally it shadows "
          . "the theme's own intent.\n\nIf a host in this family DOES "
          . "blanket-repaint, measure it, add its element list to "
          . "COUNTERED_ELEMENTS with the selector in the docblock, and then "
          . "add the rule.",
          $file
        ));
        continue;
      }

      $this->assertStringContainsString('color: inherit', $css, sprintf(
        "%s has lost its `color: inherit` counter-rule.\n\nWithout it, "
        . "inheritance from the editor-styled `.pb-ui-inner` is severed at the "
        . "first element the host repaints -- the `--pb-*` token stays correct "
        . "while the paint reverts to the theme's colour, which is the "
        . "opposite of the contract and reads as a contrast bug, not a "
        . "colour-precedence one.",
        $file
      ));

      foreach ($elements as $element) {
        $this->assertMatchesRegularExpression(
          '/:where\([^)]*\b' . preg_quote($element, '/') . '\b[^)]*\)\s*:not\(\.pb-ui-inner\)/',
          $css,
          sprintf(
            "%s no longer counters `<%s>`, which its host was measured "
            . "repainting.\n\nEither the host stopped repainting it -- "
            . "re-measure and remove it from COUNTERED_ELEMENTS with the "
            . "evidence -- or the rule has drifted and that element is now "
            . "losing the editor's colour silently.",
            $file,
            $element
          )
        );
      }

      $this->assertMatchesRegularExpression(
        '/\.pb-ui-inner\s+:where\([^)]*\)\s*:not\(\.pb-ui-inner\)/',
        $css,
        sprintf(
          "%s's counter-rule no longer excludes `.pb-ui-inner`.\n\n"
          . "`:not(.pb-ui-inner)` is load-bearing on APEX, where the list "
          . "contains `div`: without it the rule also matches a NESTED "
          . "paragraph's inner and outranks "
          . "`.paragraphs-bundles > .pb-ui-inner { color: var(--pb-tx) }`, so "
          . "that inner stops painting and its whole subtree inherits its way "
          . "up to black. That was measured while the rule was being written. "
          . "Every bridge carries it so the two read as one pattern.",
          $file
        )
      );
    }
  }

}
