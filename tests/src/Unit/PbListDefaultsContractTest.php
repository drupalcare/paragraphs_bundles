<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Every shipped list field's default value is one of its allowed keys.
 *
 * WHY THIS EXISTS. Found 2026-09-27: three columns shipped the default
 * `l4-l4-l4` while its allowed keys are `l4_l4_l4` and friends. The template
 * turns underscores into hyphens, so the page looked right and nothing went
 * red — but every section created without the form (the AI assistant, an
 * import) stored a value the field does not offer, and its edit form then
 * showed no layout chosen. A default is typed by hand in one file and the
 * options in another, so this reads both, as shipped.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
final class PbListDefaultsContractTest extends UnitTestCase {

  /**
   * No list field ships a default its own options do not contain.
   */
  public function testEveryListDefaultIsAnAllowedKey(): void {
    $root = dirname(__DIR__, 3);
    $storages = [];
    $instances = [];
    $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

    foreach ($walk as $file) {
      $name = $file->getFilename();

      if (!$file->isFile() || !str_contains($file->getPath(), '/config/') || !str_ends_with($name, '.yml')) {
        continue;
      }

      if (str_starts_with($name, 'field.storage.')) {
        $storages[substr($name, 14, -4)] = Yaml::parseFile($file->getPathname()) ?? [];
      }
      elseif (str_starts_with($name, 'field.field.')) {
        $instances[substr($name, 12, -4)] = Yaml::parseFile($file->getPathname()) ?? [];
      }
    }

    $problems = [];
    $checked = 0;

    foreach ($instances as $id => $instance) {
      // entity_type.bundle.field_name -> entity_type.field_name.
      [$entityType, , $field] = explode('.', $id, 3);
      $storage = $storages["$entityType.$field"] ?? NULL;

      if ($storage === NULL || !str_starts_with((string) ($storage['type'] ?? ''), 'list_')) {
        continue;
      }

      $allowed = array_map(
        static fn(array $item): string => (string) $item['value'],
        $storage['settings']['allowed_values'] ?? [],
      );

      foreach ($instance['default_value'] ?? [] as $default) {
        $checked++;
        $value = (string) ($default['value'] ?? '');

        if (!in_array($value, $allowed, TRUE)) {
          $problems[] = "$id: default '$value' is not one of " . implode(', ', $allowed);
        }
      }
    }

    self::assertSame([], $problems);
    self::assertGreaterThan(20, $checked, 'The scan found the list defaults.');
  }

}
