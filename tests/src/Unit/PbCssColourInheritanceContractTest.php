<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit;

use Drupal\Tests\paragraphs_bundles\Traits\ModuleFilesTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Text colour is painted on a paragraph's container, never on its contents.
 *
 * THE RULE. A paragraph's text colour (`--pb-tx`, `--pb-tx-h`) is set ONCE,
 * on the element that owns the surface - `.pb-ui-inner` or a bundle's own
 * container class - and everything inside inherits it. Inheritance is what
 * makes the editor's hover colour reach every line when the paragraph is
 * hovered, and what lets a theme's or editor's colour flow down unbroken.
 *
 * WHY THIS EXISTS. Measured 2026-10-08 on a Solo site: the hero painted its
 * `p` and `h2` with `var(--pb-tx)`, so hovering the box turned the box to the
 * editor's hover colour #ffd166 while every visible line stayed #ffffff. The
 * same pattern was in core (`.paragraphs-bundles .field`), node reference,
 * modal and image overlay (`*`), and card text section (`> div`). Each one
 * rendered, passed every test, and returned HTTP 200.
 *
 * WHAT FAILS: a rule whose `color` uses a `--pb-*` token, on a selector whose
 * subject is `*`, a text element (`p`, `span`, `div`, `li`, `td`, `h1`...), or
 * a Drupal field wrapper (`.field`, `.field__item`, `.field--name-*`).
 *
 * WHAT IS ALLOWED, and this is what keeps it from going red on correct CSS:
 * - `color: inherit` resets. They RESTORE inheritance after a theme breaks
 *   it; they paint nothing (see the Solo and APEX bridges).
 * - Links using a link token (`--pb-lk*`): a link keeps its own colour so it
 *   stays distinguishable from text (WCAG failure F73 otherwise).
 * - Buttons: user agents style form controls themselves, so they do not
 *   inherit text colour and must be painted.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
class PbCssColourInheritanceContractTest extends TestCase {

  use ModuleFilesTrait;

  /**
   * Subjects that must inherit text colour, never be painted with it.
   */
  private const string FORBIDDEN_SUBJECT = '/^(?:\*|(?:p|span|div|li|ul|ol|td|th|tr|table|h[1-6]|strong|em|b|i|u|small|label|dd|dt|figcaption|blockquote)(?![\w-])|\.field(?:__item|--name-[\w-]+)?(?![\w-]))/';

  /**
   * Returns the module root.
   */
  private function root(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * Every stylesheet in the module, relative to its root.
   *
   * @return list<string>
   *   Paths.
   */
  private function stylesheets(): array {
    $root = $this->root();
    $out = [];
    foreach ($this->moduleFiles($root) as $file) {
      $path = $file->getPathname();
      if (!$file->isFile() || $file->getExtension() !== 'css'
        || str_contains($path, '/.git/') || str_contains($path, '/node_modules/')) {
        continue;
      }
      $out[] = ltrim(str_replace($root, '', $path), '/');
    }
    sort($out);
    return $out;
  }

  /**
   * Splits a selector list on its top-level commas only.
   *
   * @param string $selectors
   *   A selector list.
   *
   * @return list<string>
   *   One selector each.
   */
  private function splitTopLevel(string $selectors, string $on = ','): array {
    $parts = [];
    $depth = 0;
    $buffer = '';
    foreach (str_split($selectors) as $char) {
      if ($char === '(' || $char === '[') {
        $depth++;
      }
      elseif ($char === ')' || $char === ']') {
        $depth--;
      }
      if ($depth === 0 && $char === $on) {
        $parts[] = trim($buffer);
        $buffer = '';
        continue;
      }
      $buffer .= $char;
    }
    $parts[] = trim($buffer);
    return array_values(array_filter($parts, static fn(string $p): bool => $p !== ''));
  }

  /**
   * The subject compounds of one selector: what the rule actually styles.
   *
   * The last compound after any top-level combinator. A leading `:where()`
   * or `:is()` is unwrapped, since each of its arguments is a subject.
   *
   * @param string $selector
   *   One selector.
   *
   * @return list<string>
   *   Subject compounds.
   */
  private function subjects(string $selector): array {
    $compounds = $this->splitTopLevel((string) preg_replace('/\s*([>+~])\s*/', ' ', $selector), ' ');
    $subject = (string) end($compounds);
    if (preg_match('/^:(?:where|is)\((.*)\)(.*)$/', $subject, $m)) {
      return array_map(static fn(string $s): string => $s . $m[2], $this->splitTopLevel($m[1]));
    }
    return [$subject];
  }

  /**
   * Every rule that paints text with a `--pb-*` token on a forbidden subject.
   *
   * @param string $css
   *   A stylesheet.
   *
   * @return list<string>
   *   "line N: selector -> value" for each violation.
   */
  private function violations(string $css): array {
    $css = (string) preg_replace_callback('!/\*.*?\*/!s', static fn(array $m): string => str_repeat(' ', strlen($m[0])), $css);
    $found = [];
    preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
    foreach ($rules as $rule) {
      $selectorText = trim($rule[1][0]);
      if ($selectorText === '' || str_starts_with($selectorText, '@')) {
        continue;
      }
      if (!preg_match('/(?<![\w-])color\s*:\s*([^;]+)/', $rule[2][0], $declaration)) {
        continue;
      }
      $value = trim($declaration[1]);
      if (!str_contains($value, 'var(--pb-') || str_starts_with($value, 'var(--pb-lk')) {
        continue;
      }
      $line = substr_count(substr($css, 0, $rule[1][1] + strlen($rule[1][0]) - strlen(ltrim($rule[1][0]))), "\n") + 1;
      foreach ($this->splitTopLevel($selectorText) as $selector) {
        foreach ($this->subjects($selector) as $subject) {
          if (preg_match(self::FORBIDDEN_SUBJECT, $subject)) {
            $found[] = sprintf('line %d: %s -> %s', $line, preg_replace('/\s+/', ' ', $selector), $value);
          }
        }
      }
    }
    return $found;
  }

  /**
   * No PB stylesheet paints text colour onto a paragraph's contents.
   */
  public function testTextColourIsPaintedOnContainersOnly(): void {
    $files = $this->stylesheets();
    // The control: a scan that read nothing would pass vacuously.
    $this->assertGreaterThan(40, count($files), 'The module stylesheets were found.');

    $all = [];
    foreach ($files as $file) {
      foreach ($this->violations((string) file_get_contents($this->root() . '/' . $file)) as $violation) {
        $all[] = $file . ' ' . $violation;
      }
    }
    $this->assertSame([], $all, "Paint text colour on the container and let its contents inherit. Use `color: inherit` to undo a theme's repaint.\n" . implode("\n", $all));
  }

  /**
   * The detector catches each shape of the defect, and spares the allowed ones.
   *
   * Without this, a parser that matched nothing would pass the test above on
   * any stylesheet at all.
   */
  public function testTheDetectorTellsPaintingFromInheriting(): void {
    $bad = [
      '.paragraphs-bundles .field { color: var(--pb-tx); }',
      '.paragraphs-bundles.pb-modal * { color: var(--pb-tx); }',
      '.paragraphs-bundles.pb-modal *:hover { color: var(--pb-tx-h, var(--pb-tx)); }',
      '.paragraphs-bundles.pb-hero .pb-hero__text-inner p { color: var(--pb-tx, inherit); }',
      '.x .y > div:hover { color: var(--pb-tx-h); }',
      '.x .y *:not(.contextual-links a, a) { color: var(--pb-tx, inherit); }',
      '.x :where(h2, p) { color: var(--pb-tx); }',
      '.x .field--name-pb-content-title { color: var(--pb-tx-h, var(--pb-tx)); }',
    ];
    foreach ($bad as $css) {
      $this->assertNotEmpty($this->violations($css), 'Caught: ' . $css);
    }

    $good = [
      '.paragraphs-bundles > .pb-ui-inner { color: var(--pb-tx); }',
      '.paragraphs-bundles > .pb-ui-inner:hover { color: var(--pb-tx-h, var(--pb-tx)); }',
      '.x .pb-ui-inner :where(p, li) { color: inherit; }',
      '.x * { color: inherit; }',
      '.x .pb-ui-inner :where(a) { color: var(--pb-lk); }',
      '.x :where(button.pb-ui-btn, a.button):hover { color: var(--pb-tx-h, var(--pb-tx)); }',
      '.x .pb-parallax__text { color: var(--pb-tx); border-color: var(--pb-br); }',
      '.x .field { border-color: var(--pb-br); background-color: var(--pb-bg); }',
      '.x .pb-divider { color: red; }',
    ];
    foreach ($good as $css) {
      $this->assertSame([], $this->violations($css), 'Allowed: ' . $css);
    }
  }

}
