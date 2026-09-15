<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit;

use Drupal\paragraphs_bundles\Service\Css\PbDynamicCssBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Every DOM id this module emits follows one grammar, on every surface.
 *
 * The rule (`docs/PB-V2-CLASS-NAMING-SPEC.md` §3.10) is
 * `pb-{bundle-slug}-{paragraph id}` for a bundle's outer element and
 * `pb-{bundle-slug}__{element}-{paragraph id}` for anything inside it. v1 had
 * thirty bespoke patterns, most of them abbreviations, and 2.0.0 replaced them.
 *
 * This is a **cross-surface** test on purpose. An id is written in Twig,
 * targeted in CSS, looked up in JS and asserted in tests, and the way this
 * module has repeatedly shipped dead selectors is by changing one of those and
 * not the others — three CSS rules that had never matched anything were found
 * that way (see `CSS-CLASS-MIGRATION.md`). A grep in a review catches it once;
 * this catches it every run.
 *
 * Scope is deliberately narrow: **ids only**. `pb__` survives legitimately as
 * a class in three frozen families that are data rather than markup — stored
 * display values (`pb__pmw_500`), breakpoint tokens (`pb__992`) and admin
 * form-group names (`pb__tab-display`) — so this must not police `pb__`
 * wholesale, only `pb__` reached as an id.
 */
#[Group('paragraphs_bundles')]
final class PbDomIdGrammarTest extends TestCase {

  /**
   * Absolute path to the module root.
   */
  private static function root(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * The module's own asset directories, relative to the root.
   *
   * **Enumerated, not discovered.** On drupal.org CI this module *is* the
   * repository root and the job installs Drupal into `web/` underneath it, so
   * a recursive walk from the root reads the whole of core — which is how the
   * first version of this test failed the pipeline on
   * `ckeditor5-settings-toolbar.html.twig`. Naming our own directories makes
   * the scan immune to whatever else the runner puts beside them.
   *
   * `scripts/` and `docs/` are maintainer-only and never reach a user;
   * `tests/` holds this file and its siblings, which quote the old names on
   * purpose.
   *
   * @var list<string>
   */
  private const array ASSET_DIRECTORIES = ['css', 'js', 'templates', 'modules', 'src'];

  /**
   * Files of one extension inside the module's own directories.
   *
   * @param string $extension
   *   File extension without the dot.
   *
   * @return list<string>
   *   Absolute paths.
   */
  private static function shippedFiles(string $extension): array {
    $root = self::root();
    $found = [];
    foreach (self::ASSET_DIRECTORIES as $directory) {
      $base = $root . '/' . $directory;
      if (!is_dir($base)) {
        continue;
      }
      $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
      );
      foreach ($iterator as $file) {
        if (!$file instanceof \SplFileInfo || $file->getExtension() !== $extension) {
          continue;
        }
        // A sub-module's own `tests/` directory is out of scope for the same
        // reason the parent's is.
        if (str_contains($file->getPathname(), '/tests/')) {
          continue;
        }
        $found[] = $file->getPathname();
      }
    }
    sort($found);
    return $found;
  }

  /**
   * The scan reaches the files it claims to check.
   *
   * A file sweep that silently matches nothing passes every assertion built on
   * it, so the count is asserted rather than assumed. These floors are far
   * below the real totals (70 CSS, 40 templates, 20 scripts at 2.0.0) and only
   * fail if the scan stops finding the tree at all.
   */
  public function testTheScanFindsTheModulesOwnFiles(): void {
    $this->assertGreaterThan(30, count(self::shippedFiles('css')), 'CSS files are being scanned.');
    $this->assertGreaterThan(30, count(self::shippedFiles('twig')), 'Templates are being scanned.');
    $this->assertGreaterThan(10, count(self::shippedFiles('js')), 'Scripts are being scanned.');
    // Nothing from a Drupal install that may sit beside us on CI.
    //
    // Asserted against the module root, not against the literal '/web/': the
    // module is normally INSTALLED under a `web/` docroot, so the string test
    // failed for anyone running this suite from inside a site rather than
    // from a bare checkout. It passed on drupal.org CI only because the repo
    // sits at the root there. Measured 2026-09-14 on sts.local, where every
    // scanned path legitimately contains '/web/'.
    $root = self::root();
    foreach ([...self::shippedFiles('twig'), ...self::shippedFiles('css')] as $path) {
      $this->assertStringStartsWith(
        $root . '/',
        $path,
        'The scan stays inside the module and never reads an installed Drupal.',
      );
    }
  }

  /**
   * Reports every line of a file matching a pattern.
   *
   * @param string $path
   *   Absolute file path.
   * @param string $pattern
   *   PCRE pattern.
   *
   * @return list<string>
   *   Human-readable `file:line: text` hits.
   */
  private static function hits(string $path, string $pattern): array {
    $contents = file_get_contents($path);
    if ($contents === FALSE) {
      return [];
    }
    $out = [];
    foreach (explode("\n", $contents) as $number => $line) {
      if (preg_match($pattern, $line) === 1) {
        $out[] = sprintf(
          '%s:%d: %s',
          str_replace(self::root() . '/', '', $path),
          $number + 1,
          trim($line),
        );
      }
    }
    return $out;
  }

  /**
   * No CSS file may scope a rule to a legacy `#pb__…` id.
   *
   * A selector naming an id nothing emits is invisible: the page renders, the
   * rule simply never applies.
   */
  public function testNoCssSelectorTargetsLegacyId(): void {
    $hits = [];
    foreach (self::shippedFiles('css') as $file) {
      $hits = [...$hits, ...self::hits($file, '/#pb__/')];
    }
    $this->assertSame(
      [],
      $hits,
      "CSS targets a `#pb__` id, but ids are `pb-{slug}-{id}` since 2.0.0:\n"
      . implode("\n", $hits),
    );
  }

  /**
   * No template may render a legacy `pb__…` id attribute.
   *
   * Covers both the literal (`id="pb__x-…"`) and the Twig-composed form
   * (`{% set foo_id = 'pb__x-' ~ paragraph.id.value %}`), which is how every
   * bespoke v1 id was actually built.
   */
  public function testNoTemplateEmitsLegacyId(): void {
    $hits = [];
    foreach (self::shippedFiles('twig') as $file) {
      $hits = [...$hits, ...self::hits($file, '/id\s*=\s*["\']pb__|_id\s*=\s*\'pb__/')];
    }
    $this->assertSame(
      [],
      $hits,
      "A template builds a `pb__` DOM id; use `pb_paragraph_id`:\n"
      . implode("\n", $hits),
    );
  }

  /**
   * No JavaScript may look an element up by a legacy `pb__…` id.
   */
  public function testNoJavascriptLooksUpLegacyId(): void {
    $hits = [];
    foreach (self::shippedFiles('js') as $file) {
      $hits = [...$hits, ...self::hits($file, '/getElementById\([`\'"]pb__|[`\'"]#pb__/')];
    }
    $this->assertSame(
      [],
      $hits,
      "JavaScript looks up a `pb__` DOM id that nothing emits any more:\n"
      . implode("\n", $hits),
    );
  }

  /**
   * Every id a template renders starts with the `pb-` prefix.
   *
   * An id is global to the document. `#next-42` or `#item-42-1` can be owned
   * just as legitimately by a host theme or another module, and whichever one
   * loses is silently broken — no error, no warning, just a control that stops
   * responding. Until 2.0.0 this module emitted eight such ids.
   */
  public function testNoTemplateEmitsUnprefixedId(): void {
    $hits = [];
    foreach (self::shippedFiles('twig') as $file) {
      // `id="…"` whose value does not begin with `pb-`, `{{`, or `{%`.
      foreach (self::hits($file, '/\sid\s*=\s*"(?!pb-|\{\{|\{%)/') as $hit) {
        $hits[] = $hit;
      }
      // `{% set …_id = 'x-' ~ … %}` where the literal does not start `pb-`.
      foreach (self::hits($file, '/set\s+\w*_?id\s*=\s*\'(?!pb-)[a-z]/') as $hit) {
        $hits[] = $hit;
      }
    }
    $this->assertSame(
      [],
      $hits,
      "A template emits an id outside the `pb-` namespace:\n" . implode("\n", $hits),
    );
  }

  /**
   * The one id-building function produces the documented grammar.
   *
   * Asserted against literals rather than against the function's own output,
   * so this fails if the rule changes — not merely if a caller drifts from it.
   *
   * @param string $slug
   *   Bundle slug.
   * @param int $id
   *   Paragraph entity id.
   * @param string $expected
   *   The id the rule requires.
   */
  #[DataProvider('outerIdCases')]
  public function testOuterIdFollowsTheDocumentedGrammar(string $slug, int $id, string $expected): void {
    $this->assertSame(
      $expected,
      PbDynamicCssBuilder::outerElementId($slug, $id),
    );
  }

  /**
   * Bundles whose v1 id was an abbreviation, plus the two edge shapes.
   *
   * @return list<array{string, int, string}>
   *   Slug, paragraph id, expected DOM id.
   */
  public static function outerIdCases(): array {
    return [
      // v1: pb__accor-wrap-42.
      ['accordion', 42, 'pb-accordion-42'],
      // v1: pb__nf-42.
      ['node-reference', 42, 'pb-node-reference-42'],
      // v1: pb__slide-wrap-42.
      ['slideshow', 42, 'pb-slideshow-42'],
      // v1: pb__image-n-42.
      ['image-narrow', 42, 'pb-image-narrow-42'],
      // v1: pb__modal-id-42.
      ['modal', 42, 'pb-modal-42'],
      // Shared `pb__parallax-` with parallax_bundle in v1; distinct now.
      ['parallax-section', 42, 'pb-parallax-section-42'],
      // A slug that starts with a digit still yields a valid HTML id,
      // because the `pb-` prefix is always first.
      ['3d-carousel', 7, 'pb-3d-carousel-7'],
      // Already conformant in v1 — the one id that did not change.
      ['card-text-section', 1, 'pb-card-text-section-1'],
    ];
  }

}
