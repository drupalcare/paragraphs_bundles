<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit\Migration;

use Drupal\paragraphs_bundles\Migration\PbThemeClassMigrator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * QA cases: inputs a theme may hold that must never change silently.
 *
 * Each case passes when the line is either left alone or listed for manual
 * review. It fails when the line is rewritten into something wrong and
 * nothing in the report says so.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
class PbThemeClassMigratorQaTest extends TestCase {

  /**
   * SCSS parent-selector nesting on a v1 block name is not silently broken.
   *
   * In v1, `.pb__card { &-image {} }` compiles to `.pb__card-image`. Renaming
   * only the parent gives `.pb-card { &-image {} }`, which compiles to
   * `.pb-card-image`, not the v2 `.pb-card__image`.
   */
  public function testScssParentSuffixNestingIsReported(): void {
    $scss = ".pb__card {\n  &-image { border: 0; }\n}\n";
    $out = (new PbThemeClassMigrator())->migrate($scss, 'nest.scss');
    $lines = explode("\n", $out['content']);
    $broken = $lines[0] === '.pb-card {' && str_starts_with(trim($lines[1]), '&-image');
    $this->assertFalse($broken && !$this->hasNote($out, [1, 2]), "SCSS `&-image` under a renamed parent now compiles to .pb-card-image, and nothing is reported.\n" . $out['content']);
  }

  /**
   * A file name that contains a v1 token is not silently renamed.
   *
   * `url(../images/pb__card.png)` points at a file on disk; the migrator does
   * not rename files, so rewriting the reference breaks the image.
   */
  public function testAssetReferenceIsNotSilentlyRenamed(): void {
    $css = ".x { background: url(../images/pb__card.png); }\n";
    $out = (new PbThemeClassMigrator())->migrate($css, 'a.css');
    $broken = !str_contains($out['content'], 'pb__card.png');
    $this->assertFalse($broken && !$this->hasNote($out, [1]), "A file reference was renamed and nothing is reported.\n" . $out['content']);
  }

  /**
   * Whether any manual-review note sits on one of the given lines.
   *
   * @param array{content: string, changes: list<array{line: int, before: string, after: string}>, notes: list<array{line: int, kind: string, token: string, v2: string, source: string}>} $out
   *   The migrate() result.
   * @param list<int> $lines
   *   Line numbers, counted from 1.
   */
  private function hasNote(array $out, array $lines): bool {
    foreach ($out['notes'] as $note) {
      if (in_array($note['line'], $lines, TRUE)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
