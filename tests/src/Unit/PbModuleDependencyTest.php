<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Every module declares the modules its own shipped config depends on.
 *
 * Drupal skips optional config whose dependencies are unmet **silently**, and
 * `drush en` still reports success. A sub-module that leans on the parent to
 * pull in `field_group` or `options` therefore looks fine right up until
 * somebody installs it on a site where the parent's dependency list has moved
 * on — and then a paragraph type installs with no form display and no error.
 *
 * That is not hypothetical here: it is how the fourth `paragraph_bundle_image`
 * dependency (B10) and the `menu_ui` gap were both found, each after a full
 * install run reported success.
 *
 * The rule this enforces is direct declaration, not transitive reachability: a
 * module names every module its own config entities name, even when a sibling
 * happens to pull that module in today.
 *
 * Over-declaring is allowed. A module may legitimately depend on something its
 * config does not mention — allowed child bundles named only in
 * `handler_settings.target_bundles`, a service its PHP calls, a field type its
 * templates format.
 */
#[Group('paragraphs_bundles')]
final class PbModuleDependencyTest extends TestCase {

  /**
   * Absolute path to the module root.
   */
  private static function root(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * Every PB module, keyed by machine name, valued by absolute directory.
   *
   * @return array<string, string>
   *   Module name => directory.
   */
  private static function modules(): array {
    $root = self::root();
    $modules = ['paragraphs_bundles' => $root];
    foreach (glob($root . '/modules/*', GLOB_ONLYDIR) ?: [] as $dir) {
      $modules[basename($dir)] = $dir;
    }
    return $modules;
  }

  /**
   * Config object name => the PB module that ships it.
   *
   * @return array<string, string>
   *   Config name => owning module.
   */
  private static function configOwners(): array {
    $owners = [];
    foreach (self::modules() as $name => $dir) {
      foreach (glob($dir . '/config/*/*.yml') ?: [] as $file) {
        $owners[basename($file, '.yml')] = $name;
      }
    }
    return $owners;
  }

  /**
   * Modules declared in a module's info.yml, project prefix stripped.
   *
   * @param string $dir
   *   Module directory.
   * @param string $name
   *   Module name.
   *
   * @return list<string>
   *   Declared module names.
   */
  private static function declaredDependencies(string $dir, string $name): array {
    $info = Yaml::parseFile($dir . '/' . $name . '.info.yml') ?: [];
    $declared = [];
    foreach ($info['dependencies'] ?? [] as $dependency) {
      $parts = explode(':', (string) $dependency);
      $declared[] = trim(end($parts));
    }
    return $declared;
  }

  /**
   * Modules a module's own shipped config declares it needs.
   *
   * Config entities carry a dependency list Drupal computed itself, so this
   * reads the requirement off the artifact rather than re-deriving it from
   * field types and plugin ids.
   *
   * @param string $dir
   *   Module directory.
   * @param string $name
   *   Module name.
   *
   * @return array<string, list<string>>
   *   Required module name => the config files that require it.
   */
  private static function configRequirements(string $dir, string $name): array {
    $owners = self::configOwners();
    $required = [];
    foreach (glob($dir . '/config/*/*.yml') ?: [] as $file) {
      $data = Yaml::parseFile($file) ?: [];
      $dependencies = $data['dependencies'] ?? [];
      $needed = array_merge(
        $dependencies['module'] ?? [],
        $dependencies['enforced']['module'] ?? [],
      );
      // A dependency on config another PB module ships is a dependency on that
      // module: uninstall it and the referenced config goes with it.
      foreach ($dependencies['config'] ?? [] as $config) {
        if (isset($owners[$config]) && $owners[$config] !== $name) {
          $needed[] = $owners[$config];
        }
      }
      foreach ($needed as $module) {
        if ($module !== $name) {
          $required[$module][] = basename($file);
        }
      }
    }
    return $required;
  }

  /**
   * Data provider: one case per PB module.
   *
   * @return \Generator<string, array{string, string}>
   *   Module name and directory.
   */
  public static function moduleProvider(): \Generator {
    foreach (self::modules() as $name => $dir) {
      yield $name => [$name, $dir];
    }
  }

  /**
   * A module declares every module its own config depends on.
   *
   * @param string $name
   *   Module name.
   * @param string $dir
   *   Module directory.
   */
  #[DataProvider('moduleProvider')]
  public function testConfigDependenciesAreDeclared(string $name, string $dir): void {
    $declared = self::declaredDependencies($dir, $name);
    $required = self::configRequirements($dir, $name);

    $missing = [];
    foreach ($required as $module => $files) {
      if (!in_array($module, $declared, TRUE)) {
        $missing[] = sprintf(
          '%s (required by %s)',
          $module,
          implode(', ', array_slice(array_unique($files), 0, 3)),
        );
      }
    }

    $this->assertSame([], $missing, sprintf(
      "%s.info.yml does not declare %d module(s) its own config depends on:\n  %s\n"
      . 'Unmet optional-config dependencies are skipped silently at install.',
      $name,
      count($missing),
      implode("\n  ", $missing),
    ));
  }

  /**
   * PB sub-module dependencies carry the project prefix, not the module name.
   *
   * `paragraph_bundle_image:paragraph_bundle_image` reads as a project of that
   * name on drupal.org. The project is `paragraphs_bundles`; the sub-modules
   * ship inside it and have no project of their own.
   *
   * @param string $name
   *   Module name.
   * @param string $dir
   *   Module directory.
   */
  #[DataProvider('moduleProvider')]
  public function testSubModuleDependenciesUseTheProjectPrefix(string $name, string $dir): void {
    $modules = self::modules();
    $info = Yaml::parseFile($dir . '/' . $name . '.info.yml') ?: [];

    $wrong = [];
    foreach ($info['dependencies'] ?? [] as $dependency) {
      $dependency = (string) $dependency;
      if (!str_contains($dependency, ':')) {
        $wrong[] = $dependency . ' (no project prefix)';
        continue;
      }
      [$project, $module] = explode(':', $dependency, 2);
      $module = trim($module);
      if (isset($modules[$module]) && $project !== 'paragraphs_bundles') {
        $wrong[] = $dependency;
      }
    }

    $this->assertSame([], $wrong, sprintf(
      '%s.info.yml uses a project prefix that is not paragraphs_bundles for a '
      . 'module shipped by this project: %s',
      $name,
      implode(', ', $wrong),
    ));
  }

  /**
   * The suite sees every sub-module, so none can be silently skipped.
   */
  public function testEveryShippedModuleIsCovered(): void {
    $modules = self::modules();
    $shipped = glob(self::root() . '/modules/*/*.info.yml') ?: [];

    $this->assertCount(
      count($shipped) + 1,
      $modules,
      'Every modules/* directory with an info.yml is covered, plus the parent.',
    );
    $this->assertArrayHasKey('paragraphs_bundles', $modules);
  }

}
