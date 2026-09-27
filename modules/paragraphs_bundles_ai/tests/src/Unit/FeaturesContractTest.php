<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Every feature in the briefs names a real field and a value it can hold.
 *
 * The features list is typed by hand, and a mistyped key is dropped in
 * silence at runtime — the section is created without it and the note simply
 * does not mention it. So this reads the SHIPPED config files, the same way
 * PbCssClassesContractTest does, and fails on the typo instead.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
final class FeaturesContractTest extends UnitTestCase {

  /**
   * Each feature's field exists on its bundle, and its value is allowed.
   */
  public function testEveryFeatureIsRealSetting(): void {
    $root = dirname(__DIR__, 5);
    $briefs = Yaml::parseFile(dirname(__DIR__, 3) . '/config/install/paragraphs_bundles_ai.briefs.yml');
    $files = $this->shippedConfig($root);
    $problems = [];
    $checked = 0;

    foreach ($briefs['briefs'] as $bundle => $brief) {
      foreach ($brief['features'] ?? [] as $field => $value) {
        $checked++;
        $instance = $files["field.field.paragraph.$bundle.$field"] ?? NULL;
        $storage = $files["field.storage.paragraph.$field"] ?? NULL;

        if ($instance === NULL || $storage === NULL) {
          $problems[] = "$bundle.$field: no such field is shipped on that bundle";
          continue;
        }

        $type = (string) $storage['type'];
        $value = (string) $value;

        if (str_starts_with($type, 'list_')) {
          $allowed = array_map(static fn(array $item): string => (string) $item['value'], $storage['settings']['allowed_values'] ?? []);

          if (!in_array($value, $allowed, TRUE)) {
            $problems[] = "$bundle.$field: '$value' is not one of " . implode(', ', $allowed);
          }
        }
        elseif ($type === 'boolean') {
          if (!in_array($value, ['0', '1'], TRUE)) {
            $problems[] = "$bundle.$field: a boolean takes '0' or '1', not '$value'";
          }
        }
        elseif ($type === 'integer') {
          if (preg_match('/^-?\d+$/', $value) !== 1) {
            $problems[] = "$bundle.$field: '$value' is not a whole number";
          }
        }
        else {
          $problems[] = "$bundle.$field: a $type field is not a feature (colours, dates and text are a person's choice)";
        }
      }
    }

    self::assertSame([], $problems);
    self::assertGreaterThan(10, $checked, 'The scan found the features.');
  }

  /**
   * Every shipped field config file in the project, by config name.
   *
   * @param string $root
   *   The project root.
   *
   * @return array<string, array<string, mixed>>
   *   Config name => decoded file.
   */
  private function shippedConfig(string $root): array {
    $found = [];
    $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

    foreach ($walk as $file) {
      $name = $file->getFilename();

      if ($file->isFile() && str_starts_with($name, 'field.') && str_ends_with($name, '.yml') && str_contains($file->getPath(), '/config/')) {
        $found[substr($name, 0, -4)] = Yaml::parseFile($file->getPathname()) ?? [];
      }
    }

    return $found;
  }

}
