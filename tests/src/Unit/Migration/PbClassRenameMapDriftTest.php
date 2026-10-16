<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit\Migration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Keeps src/Migration/PbClassRenameMap.php in step with the migration doc.
 *
 * Docs/CSS-CLASS-MIGRATION.md is the human record of every v1 -> v2 rename;
 * PbClassRenameMap.php is what `drush pb:migrate-theme` runs on. Two editable
 * copies drift unless something goes red, so this test parses the document's
 * tables and compares every section of the map with them.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
class PbClassRenameMapDriftTest extends TestCase {

  /**
   * Every section of the map matches the document.
   */
  public function testMapMatchesDocument(): void {
    $doc = $this->parseDoc();
    $map = $this->map();
    $valueMap = $this->valueMap();

    // Display-value classes come from PbValueClassMap.php at runtime, so the
    // map only carries the document's value rows that file does not have.
    $values = array_diff_key($doc['values'], $valueMap);
    $structural = array_diff_key($doc['structural'], $valueMap, $values);
    $frozen = array_values(array_diff($doc['frozen'], array_keys($valueMap), array_keys($values)));
    sort($frozen);
    $mapFrozen = $map['frozen'];
    sort($mapFrozen);

    $this->assertSame($this->sorted($structural), $this->sorted($map['structural']), 'structural');
    $this->assertSame($this->sorted($values), $this->sorted($map['values']), 'values');
    $this->assertSame($this->sorted($doc['ids']), $this->sorted($map['ids']), 'ids');
    $this->assertSame($this->sorted($doc['ambiguous_ids']), $this->sorted($map['ambiguous_ids']), 'ambiguous_ids');
    $this->assertSame($this->sorted($doc['generic']), $this->sorted($map['generic']), 'generic');
    $this->assertSame($this->sortedKeys($doc['context']), $this->sortedKeys($map['context']), 'context');
    $this->assertSame($frozen, $mapFrozen, 'frozen');
    $this->assertSame($this->sortedKeys($doc['removed']), $this->sortedKeys($map['removed']), 'removed');
  }

  /**
   * The parser reads the document; a broken parser must not pass as no drift.
   */
  public function testParserFindsRows(): void {
    $doc = $this->parseDoc();
    $this->assertSame('pb-card__image', $doc['structural']['pb__card-image'] ?? NULL);
    $this->assertSame('pb-accordion-', $doc['ids']['pb__accor-wrap-'] ?? NULL);
    $this->assertArrayHasKey('pb__image-', $doc['ambiguous_ids']);
    $this->assertSame('pb-slideshow__nav--dot', $doc['generic']['dot'] ?? NULL);
    $this->assertArrayHasKey('play', $doc['context']);
    $this->assertArrayHasKey('two-l6_l6', $doc['context']);
    $this->assertContains('pb__576', $doc['frozen']);
    $this->assertArrayHasKey('pb__accor-btn', $doc['removed']);
    $this->assertGreaterThan(150, count($doc['structural']));
  }

  /**
   * Parses the document's tables into the map's sections.
   *
   * @return array{structural: array<string, string>, values: array<string, string>, ids: array<string, string>, ambiguous_ids: array<string, string>, generic: array<string, string>, context: array<string, string>, frozen: list<string>, removed: array<string, string>}
   *   The parsed sections.
   */
  private function parseDoc(): array {
    $path = dirname(__DIR__, 4) . '/docs/CSS-CLASS-MIGRATION.md';
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    $this->assertIsArray($lines);

    $out = [
      'structural' => [],
      'values' => [],
      'ids' => [],
      'ambiguous_ids' => [],
      'generic' => [],
      'context' => [],
      'frozen' => [],
      'removed' => [],
    ];
    $idRows = [];
    $h2 = '';
    $h3 = '';
    foreach ($lines as $line) {
      if (str_starts_with($line, '## ')) {
        $h2 = trim(substr($line, 3));
        $h3 = '';
        continue;
      }
      if (str_starts_with($line, '### ')) {
        $h3 = trim(substr($line, 4));
        continue;
      }
      if (!str_starts_with($line, '|')) {
        continue;
      }

      if (str_starts_with($h2, 'Outer element ids')) {
        if (preg_match('/^\|[^|]*\|\s*`#([^`]+)`\s*\|\s*`#([^`]+)`/', $line, $m) === 1) {
          $idRows[] = [$m[1], $m[2]];
        }
        continue;
      }
      if (str_starts_with($h2, 'Display field classes')) {
        if (preg_match('/^\|\s*`([^`]+)`/', $line, $m) === 1) {
          $out['frozen'][] = $m[1];
        }
        continue;
      }
      if (str_starts_with($h3, 'Layout / card ratios')) {
        if (preg_match('/^\|\s*`([^`]+)`\s*\|\s*`/', $line, $m) === 1) {
          $out['context'][$m[1]] = $line;
        }
        continue;
      }

      if (preg_match('/^\|\s*`([^`]+)`\s*\|\s*removed\b/i', $line, $m) === 1) {
        if (!str_contains($m[1], '{')) {
          $out['removed'][$m[1]] = $line;
        }
        continue;
      }
      if (preg_match('/^\|\s*`([^`]+)`\s*\|\s*`([^`]+)`(.*)$/', $line, $m) !== 1) {
        continue;
      }
      [, $old, $new, $rest] = $m;
      if (str_contains($old, '{') || str_contains($old, '*') || str_contains($new, ' ') || str_contains($new, '{')) {
        continue;
      }
      if (str_starts_with($h2, 'Manual-only')) {
        if (str_starts_with($old, 'pb__')) {
          $out['values'][$old] = $new;
        }
        elseif (str_contains($rest, '/')) {
          // Two possible v2 names (`play`): report only.
          $out['context'][$old] = $line;
        }
        else {
          $out['generic'][$old] = $new;
        }
      }
      elseif (str_starts_with($h2, 'Structural renames') || str_starts_with($h2, 'Summary')) {
        $out['structural'][$old] = $new;
      }
    }

    $byOld = [];
    foreach ($idRows as [$old, $new]) {
      $byOld[explode('{id}', $old)[0]][explode('{id}', $new)[0]] = TRUE;
    }
    foreach ($byOld as $oldPrefix => $news) {
      $news = array_keys($news);
      if (count($news) === 1) {
        $out['ids'][$oldPrefix] = $news[0];
        continue;
      }
      // Sort the full "{prefix}{id}" names, as the map lists them.
      $names = array_map(static fn (string $n): string => $n . '{id}', $news);
      sort($names);
      $out['ambiguous_ids'][$oldPrefix] = implode(' or ', $names);
    }
    return $out;
  }

  /**
   * Loads the rename map.
   *
   * @return array{structural: array<string, string>, values: array<string, string>, ids: array<string, string>, ambiguous_ids: array<string, string>, generic: array<string, string>, context: array<string, string>, frozen: list<string>, removed: array<string, string>}
   *   The map.
   */
  private function map(): array {
    $map = require dirname(__DIR__, 4) . '/src/Migration/PbClassRenameMap.php';
    $this->assertIsArray($map);
    foreach (['structural', 'values', 'ids', 'ambiguous_ids', 'generic', 'context', 'frozen', 'removed'] as $section) {
      $this->assertArrayHasKey($section, $map);
      $this->assertIsArray($map[$section]);
    }
    return $map;
  }

  /**
   * Loads the stored-value class map.
   *
   * @return array<string, string>
   *   The map.
   */
  private function valueMap(): array {
    $map = require dirname(__DIR__, 4) . '/src/Twig/PbValueClassMap.php';
    $this->assertIsArray($map);
    return $map;
  }

  /**
   * Sorts a map by key.
   *
   * @param array<string, string> $map
   *   The map.
   *
   * @return array<string, string>
   *   The sorted map.
   */
  private function sorted(array $map): array {
    ksort($map);
    return $map;
  }

  /**
   * Returns a map's keys, sorted.
   *
   * @param array<string, string> $map
   *   The map.
   *
   * @return list<string>
   *   The sorted keys.
   */
  private function sortedKeys(array $map): array {
    $keys = array_map('strval', array_keys($map));
    sort($keys);
    return $keys;
  }

}
