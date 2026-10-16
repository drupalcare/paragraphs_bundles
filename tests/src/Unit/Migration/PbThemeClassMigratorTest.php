<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit\Migration;

use Drupal\paragraphs_bundles\Migration\PbThemeClassMigrator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Tests the v1 -> v2 theme class migrator behind `drush pb:migrate-theme`.
 *
 * The fixture is a synthetic v1 theme in tests/fixtures/pb-migrate-theme/v1
 * and the files it must become in .../expected. Files with no expected twin
 * must come out byte-identical.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
class PbThemeClassMigratorTest extends TestCase {

  /**
   * A scratch copy of the v1 fixture.
   */
  private string $work = '';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->work = sys_get_temp_dir() . '/pb-migrate-theme-' . bin2hex(random_bytes(6));
    $this->copyTree($this->fixture('v1'), $this->work);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $this->removeTree($this->work);
    parent::tearDown();
  }

  /**
   * The whole fixture theme migrates to the expected tree, and only that.
   */
  public function testFixtureThemeMigratesToExpected(): void {
    $this->runMigration(PbThemeClassMigrator::EXTENSIONS);

    $expected = $this->fixture('expected');
    foreach ($this->relativeFiles($this->work) as $relative) {
      $twin = $expected . '/' . $relative;
      $source = is_file($twin) ? $twin : $this->fixture('v1') . '/' . $relative;
      $this->assertSame(
        file_get_contents($source),
        file_get_contents($this->work . '/' . $relative),
        "$relative differs from " . (is_file($twin) ? 'expected/' : 'v1/ (must be untouched)'),
      );
    }
  }

  /**
   * A second run over migrated files changes nothing.
   */
  public function testSecondRunChangesNothing(): void {
    $this->assertGreaterThan(0, $this->runMigration(PbThemeClassMigrator::EXTENSIONS));
    $this->assertSame(0, $this->runMigration(PbThemeClassMigrator::EXTENSIONS));
  }

  /**
   * A v1 name that prefixes a longer one never rewrites inside it.
   */
  public function testPrefixCollision(): void {
    $out = $this->migrate('.pb__card .pb__card-image .pb__card-image-title {}', 'a.css');
    $this->assertSame('.pb-card .pb-card__image .pb-card__image-title {}', $out['content']);
  }

  /**
   * Per-paragraph ids are rewritten as ids, classes and Twig expressions.
   */
  public function testIdRewrite(): void {
    $out = $this->migrate('#pb__hero-9, .pb__grid-36 {}', 'a.css');
    $this->assertSame('#pb-hero-9, .pb-grid-36 {}', $out['content']);

    $out = $this->migrate('<div id="pb__accor-wrap-{{ id }}">', 'a.html.twig');
    $this->assertSame('<div id="pb-accordion-{{ id }}">', $out['content']);
  }

  /**
   * Ambiguous v1 ids are reported, never rewritten.
   */
  public function testAmbiguousIdReported(): void {
    $out = $this->migrate('#pb__parallax-4 {}', 'a.css');
    $this->assertSame('#pb__parallax-4 {}', $out['content']);
    $this->assertContains('ambiguous-id', array_column($out['notes'], 'kind'));

    // Followed by a quote, as in a Twig concatenation: reported, not silent.
    $twig = "{{ 'pb__image-' ~ id }}";
    $out = $this->migrate($twig, 'a.html.twig');
    $this->assertSame($twig, $out['content']);
    $this->assertSame(['ambiguous-id'], array_column($out['notes'], 'kind'));
  }

  /**
   * SCSS parent nesting near a PB class is reported, never rewritten.
   */
  public function testScssNestingReported(): void {
    $scss = ".pb__card {\n  &-image { border: 0; }\n}\n.pb { &__card {} }\n.plain { &-x {} }\n.a { &:hover {} }\n";
    $out = $this->migrate($scss, 'n.scss');
    $this->assertSame([2, 4], array_column(array_filter($out['notes'], fn (array $n): bool => $n['kind'] === 'scss-nesting'), 'line'));
    $this->assertStringContainsString('  &-image { border: 0; }', $out['content']);
    // Plain CSS has no nesting: no such note.
    $this->assertNotContains('scss-nesting', array_column($this->migrate('.pb__card &-x {}', 'a.css')['notes'], 'kind'));
  }

  /**
   * File names holding a v1 name are kept and reported, in every file type.
   */
  public function testFileReferencesKeptAndReported(): void {
    $cases = [
      ['.x { background: url(../images/pb__card.png); }', 'a.css'],
      ["{% include '@t/pb__card.html.twig' %}", 'a.html.twig'],
      ["import './pb__card.js';", 'a.js'],
    ];
    foreach ($cases as [$text, $file]) {
      $out = $this->migrate($text, $file);
      $this->assertSame($text, $out['content'], $file);
      $this->assertSame(['file-reference'], array_column($out['notes'], 'kind'), $file);
    }
    // A class chained with a `js-` hook is a selector, not a file name.
    $this->assertSame('.pb-card.js-x {}', $this->migrate('.pb__card.js-x {}', 'a.css')['content']);
  }

  /**
   * Symlinks and hard-linked files are skipped and listed by scan().
   */
  public function testScanReportsSkippedLinks(): void {
    symlink($this->work . '/css/overrides.css', $this->work . '/css/link.css');
    link($this->work . '/js/card.js', $this->work . '/js/hard.js');
    $scan = $this->migrator()->scan($this->work, PbThemeClassMigrator::EXTENSIONS);
    $this->assertSame(['css/overrides.css', 'templates/card.html.twig'], $this->relative($scan['files']));
    $this->assertSame(['css/link.css'], $this->relative($scan['symlinks']));
    $this->assertSame(['js/card.js', 'js/hard.js'], $this->relative($scan['hardlinks']));
  }

  /**
   * A Twig stored-value comparison is reported, not rewritten.
   */
  public function testTwigValueComparisonUntouched(): void {
    $twig = "{% if radius == 'pb__round_large' %}";
    $out = $this->migrate($twig, 'a.html.twig');
    $this->assertSame($twig, $out['content']);
    $this->assertSame(['value-in-template'], array_column($out['notes'], 'kind'));

    // The same token in a stylesheet is a rendered class: rewritten.
    $out = $this->migrate('.pb__round_large {}', 'a.css');
    $this->assertSame('.pb-u-round--large {}', $out['content']);
  }

  /**
   * A generic token is rewritten only when chained onto a PB class in CSS.
   */
  public function testChainedGenericToken(): void {
    $out = $this->migrate('.pb__slide-number.dot {}', 'a.css');
    $this->assertSame('.pb-slideshow__number.pb-slideshow__nav--dot {}', $out['content']);

    $out = $this->migrate('.pb__slides .dot {}', 'a.css');
    $this->assertSame('.pb-slideshow__slides .dot {}', $out['content']);
    $this->assertContains('generic-token', array_column($out['notes'], 'kind'));

    $out = $this->migrate('.menu .active {}', 'a.css');
    $this->assertSame('.menu .active {}', $out['content']);
    $this->assertSame([], $out['notes']);
  }

  /**
   * Context-dependent, removed and unknown tokens are reported only.
   */
  public function testReportOnlyKinds(): void {
    $cases = [
      ['.two-l6_l6 {}', 'context-dependent'],
      ['.pb__slides .play {}', 'context-dependent'],
      ['.pb__accor-btn {}', 'removed'],
      ['.pb__made-up__inner {}', 'unknown-v1-token'],
    ];
    foreach ($cases as [$css, $kind]) {
      $out = $this->migrate($css, 'a.css');
      $this->assertContains($kind, array_column($out['notes'], 'kind'), $css);
      $this->assertStringNotContainsString('pb-slideshow__nav--', $out['content'], $css);
    }
    // In a stylesheet a context token in a property value is not a class.
    foreach ([
      '.brand { font-family: play; }',
      'font-family: play, sans-serif;',
      '.pb__slides { animation: two-l6_l6 1s; }',
    ] as $css) {
      $out = $this->migrate($css, 'a.css');
      $this->assertNotContains('context-dependent', array_column($out['notes'], 'kind'), $css);
    }
    // In Twig the class sits in an attribute string, without a dot.
    $out = $this->migrate('<div class="two-l6_l6">', 'a.html.twig');
    $this->assertContains('context-dependent', array_column($out['notes'], 'kind'));
    // Frozen breakpoint tokens are neither rewritten nor reported.
    $out = $this->migrate('.pb__992 {}', 'a.css');
    $this->assertSame('.pb__992 {}', $out['content']);
    $this->assertSame([], $out['notes']);
  }

  /**
   * YAML, minified files, skipped directories and symlinks are never listed.
   */
  public function testListFilesSkipsYamlMinVendorAndSymlinks(): void {
    mkdir($this->work . '/node_modules');
    file_put_contents($this->work . '/node_modules/x.css', '.pb__card {}');
    symlink($this->work . '/css/overrides.css', $this->work . '/css/link.css');

    $files = $this->relative($this->migrator()->listFiles($this->work, PbThemeClassMigrator::EXTENSIONS));
    $this->assertSame(['css/overrides.css', 'js/card.js', 'templates/card.html.twig'], $files);
  }

  /**
   * The --ext=css option leaves Twig and JS untouched.
   */
  public function testExtCssLeavesTwigAndJsUntouched(): void {
    $extensions = $this->migrator()->normalizeExtensions('.CSS');
    $this->assertSame(['css'], $extensions);
    $this->runMigration($extensions);

    foreach (['templates/card.html.twig', 'js/card.js'] as $relative) {
      $this->assertFileEquals($this->fixture('v1') . '/' . $relative, $this->work . '/' . $relative);
    }
    $this->assertFileEquals($this->fixture('expected') . '/css/overrides.css', $this->work . '/css/overrides.css');
    $this->assertSame(0, $this->runMigration($extensions));
  }

  /**
   * Extensions outside the default set are refused.
   */
  #[DataProvider('providerRefusedExtensions')]
  public function testRefusedExtensions(string $ext): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->migrator()->normalizeExtensions($ext);
  }

  /**
   * Data provider for testRefusedExtensions().
   *
   * @return array<string, array{string}>
   *   Refused --ext values.
   */
  public static function providerRefusedExtensions(): array {
    return [
      'yml' => ['yml'],
      'php' => ['php'],
      'mixed' => ['css,yml'],
      'empty list' => [','],
      'empty value' => [''],
      'blank value' => ['  '],
    ];
  }

  /**
   * No --ext selects the full default set.
   */
  public function testDefaultExtensions(): void {
    $this->assertSame(PbThemeClassMigrator::EXTENSIONS, $this->migrator()->normalizeExtensions(NULL));
    $this->assertSame(['css', 'twig'], $this->migrator()->normalizeExtensions(' css, .Twig ,css'));
  }

  /**
   * Runs the migrator over the scratch copy, writing the results.
   *
   * @param list<string> $extensions
   *   Extensions to process.
   *
   * @return int
   *   Number of changed lines.
   */
  private function runMigration(array $extensions): int {
    $changed = 0;
    $migrator = $this->migrator();
    foreach ($migrator->listFiles($this->work, $extensions) as $path) {
      $result = $migrator->migrate((string) file_get_contents($path), $path);
      if ($result['changes'] !== []) {
        $changed += count($result['changes']);
        file_put_contents($path, $result['content']);
      }
    }
    return $changed;
  }

  /**
   * Migrates one string.
   *
   * @return array{content: string, changes: list<array{line: int, before: string, after: string}>, notes: list<array{line: int, kind: string, token: string, v2: string, source: string}>}
   *   The migrator result.
   */
  private function migrate(string $text, string $filename): array {
    return $this->migrator()->migrate($text, $filename);
  }

  /**
   * Returns a migrator.
   */
  private function migrator(): PbThemeClassMigrator {
    return new PbThemeClassMigrator();
  }

  /**
   * Returns a fixture directory.
   */
  private function fixture(string $name): string {
    return dirname(__DIR__, 3) . '/fixtures/pb-migrate-theme/' . $name;
  }

  /**
   * Lists the regular files under a directory, relative and sorted.
   *
   * @return list<string>
   *   Relative paths.
   */
  private function relativeFiles(string $dir): array {
    $files = [];
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
    foreach ($it as $item) {
      if ($item instanceof \SplFileInfo && $item->isFile() && !$item->isLink()) {
        $files[] = $item->getPathname();
      }
    }
    return $this->relative($files);
  }

  /**
   * Strips the scratch prefix from paths.
   *
   * @param list<string> $paths
   *   Absolute paths.
   *
   * @return list<string>
   *   Sorted relative paths.
   */
  private function relative(array $paths): array {
    $out = array_map(fn (string $p): string => substr($p, strlen($this->work) + 1), $paths);
    sort($out);
    return $out;
  }

  /**
   * Copies a directory tree.
   */
  private function copyTree(string $from, string $to): void {
    mkdir($to, 0777, TRUE);
    $it = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::SELF_FIRST,
    );
    foreach ($it as $item) {
      if (!$item instanceof \SplFileInfo) {
        continue;
      }
      $target = $to . '/' . substr($item->getPathname(), strlen($from) + 1);
      if ($item->isDir()) {
        mkdir($target, 0777, TRUE);
      }
      else {
        copy($item->getPathname(), $target);
      }
    }
  }

  /**
   * Removes a directory tree, without following symlinks.
   */
  private function removeTree(string $dir): void {
    if ($dir === '' || !is_dir($dir)) {
      return;
    }
    $it = new \RecursiveIteratorIterator(
      new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
      \RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($it as $item) {
      if (!$item instanceof \SplFileInfo) {
        continue;
      }
      if ($item->isDir() && !$item->isLink()) {
        rmdir($item->getPathname());
      }
      else {
        unlink($item->getPathname());
      }
    }
    rmdir($dir);
  }

}
