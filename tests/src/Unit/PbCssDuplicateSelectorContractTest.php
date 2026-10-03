<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit;

use Drupal\Tests\paragraphs_bundles\Traits\ModuleFilesTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * No selector may be defined twice in the same place in one stylesheet.
 *
 * WHY THIS EXISTS. `pb-ai-bar.css` defined `.pb-ai-note` twice at top level.
 * The second block won the cascade and carried
 * `transform: translateY(calc(-100% - 6px))`, so every id tooltip was lifted by
 * its own full height on top of the position its script had already computed.
 * It was found by a person looking at the screen and saying the tooltip was too
 * far from the top -- a long way round for something a parser answers in
 * milliseconds, and only after time had been spent rewriting the placement
 * maths, which was never wrong.
 *
 * WHY NOT JUST stylelint, measured 2026-09-17:
 * 1. Drupal core's own `.stylelintrc.json` sets `"no-duplicate-selectors":
 *    null`. Core DISABLES this rule, so a module inheriting core's convention
 *    inherits the silence.
 * 2. stylelint is not installed on the maintainer's machine -- no
 *    `node_modules` in the site root or in `web/core` -- and this project's CI
 *    mentions stylelint zero times. A `.stylelintrc.json` ships beside this
 *    test with the rule switched back on, for the day CI grows a node job; it
 *    is not what makes the rule hold today.
 * 3. A first attempt put this in `scripts/`. `.gitignore:70` ignores `scripts/`
 *    wholesale, so it would never have reached drupal.org and never run in CI:
 *    a harness that does not ship is not a harness.
 *
 * WHAT IT DELIBERATELY ALLOWS, and this is the half that makes it survivable:
 * the same selector inside a DIFFERENT at-rule. `.pb-ai-note` appears again
 * inside `@media (prefers-reduced-motion: reduce)` in that very file, and that
 * is correct CSS. A check that flagged it would go red on clean code, be
 * switched off, and protect nothing.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
class PbCssDuplicateSelectorContractTest extends TestCase {

  use ModuleFilesTrait;

  /**
   * Duplicates that already existed when this contract was written.
   *
   * MEASURED, NOT ASSUMED: 71 stylesheets scanned on 2026-09-17, four hits.
   * They are listed rather than fixed because removing one changes which block
   * wins the cascade, and that can change how a shipped bundle renders -- a
   * decision for the maintainer with a page in front of them, not a tidy-up to
   * be smuggled into a test commit.
   *
   * Removing an entry here is the goal. Adding one needs a reason.
   *
   * @var array<string, string>
   */
  private const array KNOWN_DUPLICATES = [
    'modules/paragraph_bundle_3d_carousel/css/paragraph-bundle-3d-carousel.css|.paragraphs-bundles.pb-3d-carousel .pb-3d-carousel__options .pb-ui-btn'
    => 'pre-existing at the time this contract was added; the later block wins and the visual result has not been reviewed',
    'modules/paragraph_bundle_3d_flip_box/css/paragraph-bundle-3d-flip-box.css|.paragraphs-bundles.pb-3d-flip-box .pb-3d-flip-box__toggle-icon'
    => 'pre-existing; the two blocks sit six lines apart and may well be an accidental paste',
    'modules/paragraph_bundle_hero/css/paragraph-bundle-hero.css|.paragraphs-bundles.pb-hero .pb-hero__text-inner'
    => 'pre-existing; hero has two of these and its cascade should be read as a whole before either is touched',
    'modules/paragraph_bundle_hero/css/paragraph-bundle-hero.css|.paragraphs-bundles.pb-hero .pb-hero__text-inner > .pb-ui-link'
    => 'pre-existing; see the entry above, same file and same review',
  ];

  /**
   * Returns the module root.
   */
  private function root(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * Returns every file under the module matching an extension.
   *
   * @param list<string> $extensions
   *   Extensions to collect, without the dot.
   *
   * @return list<string>
   *   Paths relative to the module root.
   */
  private function files(array $extensions): array {
    $root = $this->root();
    $out = [];
    $it = $this->moduleFiles($root);
    foreach ($it as $file) {
      if (!$file->isFile()) {
        continue;
      }
      $path = $file->getPathname();
      if (str_contains($path, '/.git/') || str_contains($path, '/node_modules/')) {
        continue;
      }
      if (!in_array($file->getExtension(), $extensions, TRUE)) {
        continue;
      }
      $out[] = ltrim(str_replace($root, '', $path), '/');
    }
    sort($out);
    return $out;
  }

  /**
   * Blanks comments while keeping every other byte at its original offset.
   *
   * Replacing each comment with the same number of spaces means the line
   * numbers reported to a developer still point at the real file.
   *
   * @param string $css
   *   The stylesheet.
   *
   * @return string
   *   The stylesheet with comments blanked.
   */
  private function stripComments(string $css): string {
    return (string) preg_replace_callback(
      '!/\*.*?\*/!s',
      static fn(array $m): string => str_repeat(' ', strlen($m[0])),
      $css
    );
  }

  /**
   * Normalises a selector so spacing differences still compare equal.
   *
   * `.a , .b` and `.a, .b` are one selector written two ways. Case is NOT
   * folded, because CSS class names are case-sensitive.
   *
   * @param string $selector
   *   Raw selector text.
   *
   * @return string
   *   Normalised selector.
   */
  private function normalise(string $selector): string {
    $selector = (string) preg_replace('/\s+/', ' ', trim($selector));
    return (string) preg_replace('/\s*,\s*/', ', ', $selector);
  }

  /**
   * Every rule in a stylesheet, with the at-rule context it sits inside.
   *
   * @param string $css
   *   The stylesheet.
   *
   * @return list<array{selector: string, context: string, line: int}>
   *   One entry per rule block.
   */
  private function rules(string $css): array {
    $css = $this->stripComments($css);
    $length = strlen($css);
    $buffer = '';
    $rules = [];
    $context = [];
    $depth = 0;

    for ($i = 0; $i < $length; $i++) {
      $char = $css[$i];

      if ($char === '{') {
        $prelude = trim($buffer);
        $buffer = '';

        if ($prelude !== '' && $prelude[0] === '@') {
          // An at-rule block is its own space: a selector repeated inside it is
          // not a duplicate of the one outside.
          $context[] = $this->normalise($prelude);
          $depth++;
          continue;
        }

        if ($prelude !== '') {
          $before = substr($css, 0, $i);
          $rules[] = [
            'selector' => $this->normalise($prelude),
            'context' => implode(' > ', $context),
            'line' => substr_count($before, "\n") + 1 - substr_count(trim($prelude, " \t"), "\n"),
          ];
        }

        $depth++;
        continue;
      }

      if ($char === '}') {
        $depth = max(0, $depth - 1);
        if (count($context) > $depth) {
          array_pop($context);
        }
        $buffer = '';
        continue;
      }

      // Declarations inside a rule are not selectors.
      if ($char === ';' && $depth > count($context)) {
        $buffer = '';
        continue;
      }

      $buffer .= $char;
    }

    return $rules;
  }

  /**
   * Duplicate selectors in one stylesheet.
   *
   * @param string $css
   *   The stylesheet source.
   *
   * @return list<array{selector: string, context: string, line: int, first: int}>
   *   One entry per duplicate.
   */
  private function duplicates(string $css): array {
    $seen = [];
    $found = [];

    foreach ($this->rules($css) as $rule) {
      if ($rule['selector'] === '' || str_contains($rule['selector'], ';')) {
        continue;
      }

      $key = $rule['context'] . '|' . $rule['selector'];

      if (isset($seen[$key])) {
        $found[] = $rule + ['first' => $seen[$key]];
        continue;
      }

      $seen[$key] = $rule['line'];
    }

    return $found;
  }

  /**
   * No stylesheet defines a selector twice, beyond the known baseline.
   */
  public function testNoSelectorIsDefinedTwice(): void {
    $found = [];
    $detail = [];

    foreach ($this->files(['css']) as $rel) {
      $css = (string) file_get_contents($this->root() . '/' . $rel);

      foreach ($this->duplicates($css) as $duplicate) {
        $key = $rel . '|' . $duplicate['selector'];
        $found[] = $key;
        $detail[$key] = sprintf(
          '%s:%d  `%s` is already defined at line %d (%s)',
          $rel,
          $duplicate['line'],
          $duplicate['selector'],
          $duplicate['first'],
          $duplicate['context'] === '' ? 'top level' : $duplicate['context']
        );
      }
    }

    sort($found);
    $known = array_keys(self::KNOWN_DUPLICATES);
    sort($known);

    $new = array_values(array_diff($found, $known));
    $fixed = array_values(array_diff($known, $found));

    $this->assertSame([], $new, sprintf(
      "A selector is defined twice in the same place.\n\n"
      . "The LATER block wins the cascade, so the earlier one is dead code and "
      . "the live declarations are not the ones being read. This is how the id "
      . "tooltip ended up lifted by its own height: two `.pb-ai-note` blocks, "
      . "and the second carried a transform nobody knew was still applying.\n\n"
      . "Merge the blocks, or scope one of them. If the repetition is genuinely "
      . "intended, add it to KNOWN_DUPLICATES in %s with the reason.\n\n%s",
      basename(__FILE__),
      implode("\n", array_map(static fn(string $k): string => '  ' . ($detail[$k] ?? $k), $new))
    ));

    $this->assertSame([], $fixed, sprintf(
      "A baseline duplicate has been fixed -- good -- but KNOWN_DUPLICATES in "
      . "%s still lists it.\n\nRemove the entry, so the list keeps meaning what "
      . "it says and the next person is not told a defect exists when it does "
      . "not.\n\n  %s",
      basename(__FILE__),
      implode("\n  ", $fixed)
    ));
  }

  /**
   * The detector itself behaves, in BOTH directions.
   *
   * A checker only ever tried against broken input cannot tell you it is not
   * simply flagging everything. The second case is the one that matters here:
   * this module's own stylesheet legitimately repeats a selector inside a media
   * query, and a detector without that exemption would go red on clean code.
   */
  public function testTheDetectorItselfBehaves(): void {
    $cases = [
      'a genuine top-level duplicate' => [
        ".pb-ai-note { color: red; }\n.other { color: blue; }\n.pb-ai-note { color: green; }\n",
        1,
      ],
      'the SAME selector inside a media query is legitimate' => [
        ".pb-ai-note { opacity: 0; }\n@media (prefers-reduced-motion: reduce) {\n  .pb-ai-note { opacity: 1; }\n}\n",
        0,
      ],
      'two different selectors are not a duplicate' => [
        ".a { color: red; }\n.b { color: blue; }\n",
        0,
      ],
      'spacing differences still count as the same selector' => [
        ".a , .b { color: red; }\n.a, .b { color: blue; }\n",
        1,
      ],
      'a duplicate INSIDE one media query is still a duplicate' => [
        "@media screen {\n  .a { color: red; }\n  .a { color: blue; }\n}\n",
        1,
      ],
      'a commented-out block is not a definition' => [
        ".a { color: red; }\n/* .a { color: blue; } */\n",
        0,
      ],
    ];

    foreach ($cases as $name => [$css, $expected]) {
      $this->assertCount(
        $expected,
        $this->duplicates($css),
        sprintf('The duplicate detector misread this case: %s', $name)
      );
    }
  }

  /**
   * The shipped stylelint config keeps the rule core switches off.
   *
   * Belt and braces, and the braces are the point: core's own config sets
   * `no-duplicate-selectors` to null, so inheriting core's conventions without
   * saying otherwise would silently disable the very rule this file enforces.
   */
  public function testTheStylelintConfigReEnablesTheRule(): void {
    $path = $this->root() . '/.stylelintrc.json';
    $this->assertFileExists($path, 'The module ships a stylelint config.');

    $config = json_decode((string) file_get_contents($path), TRUE);
    $this->assertIsArray($config, '.stylelintrc.json is valid JSON.');

    $this->assertTrue(
      ($config['rules']['no-duplicate-selectors'] ?? NULL) === TRUE,
      "`no-duplicate-selectors` must be TRUE in .stylelintrc.json.\n\n"
      . "Drupal core sets it to null. If this module ever inherits that, the "
      . "config stops agreeing with this test, and whichever one a future "
      . "author trusts will be the wrong one."
    );
  }

}
