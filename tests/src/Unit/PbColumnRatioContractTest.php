<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Each column ratio option renders the widths its label promises.
 *
 * WHY THIS EXISTS. Found 2026-10-25: the three-column options labelled
 * '1/4 - 1/2 - 1/4' (and its two mirrors) shipped `1fr 3fr 1fr`, which is
 * 1/5 - 3/5 - 1/5. The labels live in the field storage and the widths in
 * five breakpoint CSS files per module, so nothing tied them together. This
 * reads both, as shipped, and compares the fractions.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
final class PbColumnRatioContractTest extends UnitTestCase {

  /**
   * The list fields and the CSS that lays them out.
   *
   * @return array<string, array{string, string, string}>
   *   Storage file, CSS file prefix and the modifier class prefix.
   */
  public static function providerColumnFields(): array {
    return [
      'layout two' => [
        'paragraph_bundle_layout/config/optional/field.storage.paragraph.pb_display_two_columns.yml',
        'paragraph_bundle_layout/css/paragraph-bundle-layout-pb-',
        'pb-two-columns--',
      ],
      'layout three' => [
        'paragraph_bundle_layout/config/optional/field.storage.paragraph.pb_display_three_columns.yml',
        'paragraph_bundle_layout/css/paragraph-bundle-layout-pb-',
        'pb-three-columns--',
      ],
      'card two' => [
        'paragraph_bundle_card/config/optional/field.storage.paragraph.pb_display_card_layout_two.yml',
        'paragraph_bundle_card/css/paragraph-bundle-card-pb-',
        'pb-card-two-columns--',
      ],
      'card three' => [
        'paragraph_bundle_card/config/optional/field.storage.paragraph.pb_display_card_layout_three.yml',
        'paragraph_bundle_card/css/paragraph-bundle-card-pb-',
        'pb-card-three-columns--',
      ],
    ];
  }

  /**
   * Every option at every breakpoint matches its label's fractions.
   */
  #[DataProvider('providerColumnFields')]
  public function testRatiosMatchLabels(string $storageFile, string $cssPrefix, string $classPrefix): void {
    $modules = dirname(__DIR__, 3) . '/modules/';
    $storage = Yaml::parseFile($modules . $storageFile);
    $options = $storage['settings']['allowed_values'] ?? [];
    self::assertNotEmpty($options, "$storageFile has options.");

    $problems = [];
    $checked = 0;

    foreach (['576', '768', '992', '1200', '1400'] as $breakpoint) {
      $css = (string) file_get_contents($modules . $cssPrefix . $breakpoint . '.css');

      foreach ($options as $option) {
        $class = $classPrefix . str_replace('_', '-', (string) $option['value']);
        $pattern = '/' . preg_quote($class, '/') . '\s*\{\s*grid-template-columns:\s*([^;]+);/';

        if (!preg_match($pattern, $css, $match)) {
          $problems[] = "$breakpoint: no grid-template-columns rule for .$class";
          continue;
        }

        $checked++;
        // Expand repeat(3, 1fr) so every column is counted.
        $columns = (string) preg_replace_callback(
          '/repeat\(\s*(\d+)\s*,\s*([^)]+)\)/',
          static fn(array $r): string => implode(' ', array_fill(0, (int) $r[1], trim($r[2]))),
          $match[1],
        );
        preg_match_all('/([\d.]+)fr/', $columns, $fr);
        $actual = self::normalise(array_map('floatval', $fr[1]));
        $expected = self::normalise(array_map(
          static function (string $part): float {
            [$numerator, $denominator] = array_map('floatval', explode('/', trim($part)));
            return $numerator / $denominator;
          },
          explode(' - ', (string) $option['label']),
        ));

        if (count($actual) !== count($expected) || self::distance($actual, $expected) > 0.001) {
          $problems[] = "$breakpoint: .$class is '" . trim($match[1]) . "', label says '{$option['label']}'";
        }
      }
    }

    self::assertSame([], $problems);
    self::assertSame(count($options) * 5, $checked);
  }

  /**
   * Scales a list of widths so they sum to 1.
   *
   * @param float[] $widths
   *   The widths.
   *
   * @return float[]
   *   The widths as fractions of the whole.
   */
  private static function normalise(array $widths): array {
    $sum = array_sum($widths);
    return $sum > 0 ? array_map(static fn(float $w): float => $w / $sum, $widths) : $widths;
  }

  /**
   * The largest difference between two equal-length lists.
   *
   * @param float[] $a
   *   First list.
   * @param float[] $b
   *   Second list.
   */
  private static function distance(array $a, array $b): float {
    $max = 0.0;
    foreach ($a as $i => $value) {
      $max = max($max, abs($value - $b[$i]));
    }
    return $max;
  }

}
