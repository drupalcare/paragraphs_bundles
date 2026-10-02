<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * No stylesheet may size or space core's contextual-links wrapper by accident.
 *
 * Core puts `div.contextual` as a direct child of every <nav> block, of block
 * and view wrappers, and of node wrappers. A rule like `.region > nav > *` that
 * sets a width or margin therefore stretches that wrapper over the menu and
 * makes its links unclickable for anyone logged in with contextual links. This
 * guard fails on any selector whose subject is `*` directly under `nav`,
 * `.node__content` or a `.node--view-mode-*` wrapper unless that `*` excludes
 * `.contextual`. It reads every stylesheet of the module and its sub-modules.
 */
#[Group('paragraphs_bundles')]
final class PbContextualWrapperCssTest extends TestCase {

  /**
   * Tells whether one selector can match the contextual wrapper by accident.
   *
   * @param string $selector
   *   One selector, not a comma list.
   */
  public static function isUnsafe(string $selector): bool {
    $pattern = '/(?:\bnav|\.node__content|\.node--view-mode-[\w-]+)\s*>\s*\*((?::[\w-]+(?:\((?:[^()]|\([^()]*\))*\))?)*)\s*$/';
    if (preg_match($pattern, trim($selector), $match) !== 1) {
      return FALSE;
    }
    return !(preg_match('/:not\([^)]*\.contextual/', $match[1]) === 1);
  }

  /**
   * Splits a selector list on top-level commas (not those inside :not()).
   *
   * @return list<string>
   *   The selectors.
   */
  private static function splitSelectors(string $list): array {
    $parts = [];
    $depth = 0;
    $current = '';
    foreach (str_split($list) as $char) {
      if ($char === '(') {
        $depth++;
      }
      elseif ($char === ')') {
        $depth--;
      }
      if ($char === ',' && $depth === 0) {
        $parts[] = $current;
        $current = '';
        continue;
      }
      $current .= $char;
    }
    $parts[] = $current;
    return $parts;
  }

  /**
   * Finds the unsafe selectors of one stylesheet.
   *
   * @return list<string>
   *   One "line N: selector" entry each.
   */
  public static function findUnsafe(string $css): array {
    // Blank out comments but keep their newlines so line numbers stay true.
    $css = (string) preg_replace_callback('~/\*.*?\*/~s', static fn(array $m): string => str_repeat("\n", substr_count($m[0], "\n")), $css);
    $found = [];
    if (preg_match_all('/([^{}]+)\{/', $css, $matches, PREG_OFFSET_CAPTURE) === FALSE) {
      return [];
    }
    foreach ($matches[1] as [$list, $offset]) {
      // Skip at-rule preludes such as @media (...).
      if (str_starts_with(ltrim($list), '@')) {
        continue;
      }
      $line = substr_count($css, "\n", 0, $offset) + substr_count($list, "\n", 0, strlen($list) - strlen(ltrim($list))) + 1;
      foreach (self::splitSelectors($list) as $selector) {
        if (self::isUnsafe($selector)) {
          $found[] = 'line ' . ($line + substr_count(substr($list, 0, (int) strpos($list, $selector)), "\n")) . ': ' . trim((string) preg_replace('/\s+/', ' ', $selector));
        }
      }
    }
    return $found;
  }

  /**
   * Selectors the checker must flag, and ones it must leave alone.
   *
   * @return array<string, array{0: string, 1: bool}>
   *   Each case: selector, whether it is unsafe.
   */
  public static function selectorProvider(): array {
    return [
      'nav child, bare' => ['.region > nav > *', TRUE],
      'nav child, with ancestors' => ['.page-node-type-pb-content .sidebar-box-main-inner > nav > *', TRUE],
      'node content child' => ['.page-node-type-x .node--view-mode-full > .node__content > *', TRUE],
      'node wrapper child' => ['.node--view-mode-full > *', TRUE],
      'not() that forgets contextual' => ['.node__content > *:not(.paragraphs-bundles)', TRUE],
      'nav child, excluded' => ['.region > nav > :not(.contextual)', FALSE],
      'node content, excluded' => ['.node__content > *:not(.paragraphs-bundles, .contextual)', FALSE],
      'star not the subject' => ['.node__content > * > p', FALSE],
      'named child' => ['nav > a', FALSE],
      'unrelated wrapper' => ['.pb-alert > *', FALSE],
    ];
  }

  /**
   * The checker flags what it should and passes what it should.
   */
  #[DataProvider('selectorProvider')]
  public function testSelectorClassification(string $selector, bool $unsafe): void {
    $this->assertSame($unsafe, self::isUnsafe($selector), $selector);
  }

  /**
   * The line numbers in a failure message point at the selector.
   */
  public function testFindUnsafeReportsTheLine(): void {
    $css = "/* a\n   comment */\n.ok > a { }\n.region > nav > *,\n.fine { width: 1px; }\n.x .node__content > *:not(.contextual) { }\n";
    $this->assertSame(['line 4: .region > nav > *'], self::findUnsafe($css));
  }

  /**
   * Every stylesheet of the module and its sub-modules is clean.
   */
  public function testNoStylesheetCanSizeTheContextualWrapper(): void {
    $root = dirname(__DIR__, 3);
    $problems = [];
    $files = 0;
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
      $path = $file->getPathname();
      if (!str_ends_with($path, '.css') || str_ends_with($path, '.min.css')
        || preg_match('~/(vendor|node_modules)/~', $path) === 1) {
        continue;
      }
      $files++;
      $css = file_get_contents($path);
      foreach (self::findUnsafe($css === FALSE ? '' : $css) as $problem) {
        $problems[] = substr($path, strlen($root) + 1) . ' ' . $problem;
      }
    }
    $this->assertGreaterThan(20, $files, 'The scan really found the module\'s stylesheets.');
    $this->assertSame([], $problems, "These selectors can size core's .contextual wrapper; add `:not(.contextual)`:\n" . implode("\n", $problems));
  }

}
