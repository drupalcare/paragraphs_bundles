<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Guards the pb_content width contract against silent drift.
 *
 * WHY THIS EXISTS. The width behaviour of a pb_content node used to be enforced
 * by repetition: a four-item structural exclusion list copied into four
 * stylesheets, and nineteen separate selectors each carrying
 * `:not(.page-node-type-pb-content .paragraphs-bundles)`. Both are the same
 * mistake -- a rule that only holds while every future author remembers it.
 *
 * `paragraph-bundle-parallax.css` forgot. Its `.pb-parallax__text` consumed
 * `--pb-width` without the negation, so the bundle max-width beat the node
 * width on every pb_content node. One module out of forty, silent, and found
 * only by rendering a real page and reading computed styles.
 *
 * The contract now lives in two places instead of twenty-three: templates emit
 * `pb-width-own` on the elements that own their width, and one rule blanks the
 * bundle token for everything else. This test is what stops that collapsing
 * back -- a rule without a harness decays into a suggestion.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
class PbWidthContractTest extends TestCase {

  /**
   * Templates allowed to emit `pb-width-own`, and why each one may.
   *
   * Adding an entry here is a deliberate act: it exempts an element from the
   * node width an editor chose. Two of the four exclusions this contract
   * inherited turned out to be WRONG -- the slideshow and the plain carousel
   * were never meant to escape the node width, and had been escaping it since
   * the structural list was written. Both now follow it.
   *
   * @var array<string, string>
   */
  private const array WIDTH_OWNERS = [
    'modules/paragraph_bundle_3d_carousel/templates/paragraph--3d-carousel-bundle.html.twig'
    => 'slide transforms and perspective are computed from this box; clamping it skews the 3D geometry rather than merely narrowing it',
    'modules/paragraph_bundle_modal/templates/paragraph--modal-bundle.html.twig'
    => 'a modal is an overlay, lifted out of the content column, so the node content width has no authority over it',
  ];

  /**
   * The structural exclusion list this contract replaced.
   *
   * If any of these reappears in a stylesheet, the four-copy design is coming
   * back and the class contract is being bypassed.
   *
   * @var list<string>
   */
  private const array RETIRED_SELECTORS = [
    '.pb-carousel__viewport.pb-content__full',
    '.pb-3d-carousel__inner.pb-content__full',
    '.pb-slideshow__wrap-inner > .pb-content__full',
    '.pb-modal__content .pb-content__full',
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
    $it = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
    );
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
   * Only the approved templates may exempt themselves from the node width.
   */
  public function testOnlyApprovedTemplatesClaimTheirOwnWidth(): void {
    $found = [];
    foreach ($this->files(['twig']) as $rel) {
      $body = file_get_contents($this->root() . '/' . $rel);
      // Ignore Twig comments: the approved templates explain themselves in one,
      // and a mention is not an emission.
      $code = preg_replace('/\{#.*?#\}/s', '', $body) ?? $body;
      if (str_contains($code, 'pb-width-own')) {
        $found[] = $rel;
      }
    }
    sort($found);
    $approved = array_keys(self::WIDTH_OWNERS);
    sort($approved);

    $this->assertSame($approved, $found, sprintf(
      "A template emits `pb-width-own` without being on the approved list.\n\n"
      . "That class exempts an element from the width the editor chose on the "
      . "node. It is not a layout convenience.\n\n"
      . "If the exemption is genuine, add the file to WIDTH_OWNERS in %s with "
      . "the reason -- and check it against the two that were already found to "
      . "be wrong.\n\nApproved:\n  %s\nFound:\n  %s",
      basename(__FILE__),
      implode("\n  ", $approved) ?: '(none)',
      implode("\n  ", $found) ?: '(none)'
    ));
  }

  /**
   * The `pb-width-own` class modifies `pb-content__full`, never standing alone.
   */
  public function testWidthOwnAlwaysAccompaniesContentFull(): void {
    foreach (array_keys(self::WIDTH_OWNERS) as $rel) {
      $path = $this->root() . '/' . $rel;
      $this->assertFileExists($path, "Approved width owner is missing: $rel");
      $code = preg_replace('/\{#.*?#\}/s', '', file_get_contents($path)) ?? '';
      $this->assertStringContainsString('pb-width-own', $code, "$rel no longer emits pb-width-own");

      // Checking only that BOTH strings appear in the file is too weak, and a
      // red-proof caught it: removing `pb-content__full` from the modal's class
      // list left this test green, because the string still occurred elsewhere
      // in the template. The two must be on the SAME element, so look only at
      // the class context immediately around each `pb-width-own`.
      $offset = 0;
      while (($pos = strpos($code, 'pb-width-own', $offset)) !== FALSE) {
        $offset = $pos + 1;
        $start = max(0, $pos - 220);
        $context = substr($code, $start, ($pos - $start) + 60);
        // Trim back to the start of this class list -- a `class="` attribute or
        // the opening bracket of a Twig class array.
        if (($cut = strrpos($context, 'class="')) !== FALSE) {
          $context = substr($context, $cut);
        }
        elseif (($cut = strrpos($context, '[')) !== FALSE) {
          $context = substr($context, $cut);
        }
        $this->assertStringContainsString('pb-content__full', $context, sprintf(
          "%s emits `pb-width-own` on an element that does not also carry "
          . "`pb-content__full`.\n\nThe class is only a MODIFIER of the element "
          . "the width rules target. On its own it is inert, and the element "
          . "silently takes the node width -- the opposite of what marking it "
          . "was meant to achieve.\n\nClass context:\n  %s",
          $rel, trim($context)
        ));
      }
    }
  }

  /**
   * The retired structural exclusion list must not come back.
   */
  public function testStructuralExclusionListStaysRetired(): void {
    $offenders = [];
    foreach ($this->files(['css']) as $rel) {
      $css = file_get_contents($this->root() . '/' . $rel);
      foreach (self::RETIRED_SELECTORS as $sel) {
        if (str_contains($css, $sel)) {
          $offenders[] = "$rel  ->  $sel";
        }
      }
    }
    $this->assertSame([], $offenders, sprintf(
      "A retired structural exclusion has reappeared.\n\n"
      . "These four selectors were duplicated across four stylesheets. Two of "
      . "them were WRONG and nobody could see the list as a list. They are "
      . "replaced by `:not(.pb-width-own)`, which the templates drive.\n\n%s",
      implode("\n", $offenders)
    ));
  }

  /**
   * Declaring `all:` first matters: placed last, it wipes the whole block.
   *
   * Not strictly a width rule, but the same class of silent failure and it has
   * now bitten twice: `all: unset` declared last removed `box-sizing:
   * border-box` from every button and link in the module (clipping the
   * accordion's plus icon), and removed `position: absolute` from the slideshow
   * arrows (dropping them from the middle of the slide to the bottom).
   * Declarations carrying `!important` survive, which is what made both look
   * like something other than an ordering bug.
   */
  public function testAllShorthandNeverWipesItsOwnBlock(): void {
    $offenders = [];
    foreach ($this->files(['css']) as $rel) {
      $css = file_get_contents($this->root() . '/' . $rel);
      // Strip comments so prose about `all:` cannot trip the scan.
      $css = preg_replace('!/\*.*?\*/!s', '', $css) ?? $css;
      if (!preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER)) {
        continue;
      }
      foreach ($matches as $rule) {
        $body = $rule[2];
        if (!preg_match('/(^|;)\s*all\s*:/', $body)) {
          continue;
        }
        $decls = array_values(array_filter(array_map('trim', explode(';', $body)), 'strlen'));
        $index = NULL;
        foreach ($decls as $i => $decl) {
          if (preg_match('/^all\s*:/', $decl)) {
            $index = $i;
            break;
          }
        }
        if ($index === NULL || $index === 0) {
          continue;
        }
        $wiped = array_filter(
          array_slice($decls, 0, $index),
          static fn(string $d): bool => !str_contains($d, '!important')
        );
        if ($wiped) {
          $offenders[] = sprintf(
            "%s\n    selector: %s\n    `all:` is declaration %d of %d and wipes: %s",
            $rel,
            trim(explode(',', trim($rule[1]))[0]),
            $index + 1,
            count($decls),
            implode(', ', array_map(
              static fn(string $d): string => trim(explode(':', $d)[0]),
              $wiped
            ))
          );
        }
      }
    }
    $this->assertSame([], $offenders, sprintf(
      "`all:` is declared after other declarations, which silently removes "
      . "them.\n\nMove `all: unset` to the FIRST declaration in the block. It "
      . "is there to strip the UA styling before applying ours, not after.\n\n%s",
      implode("\n\n", $offenders)
    ));
  }

}
