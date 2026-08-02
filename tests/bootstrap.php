<?php

/**
 * @file
 * PHPUnit bootstrap for paragraphs_bundles pure-Unit tests.
 *
 * Loads Composer autoload plus the lightweight Drupal entity/field interface
 * stubs, so mock-based Unit tests can typehint core interfaces
 * (NodeInterface, ParagraphInterface, FieldItemListInterface, ...) without a
 * full Drupal kernel.
 *
 * IMPORTANT: these stubs must be loaded HERE (per-suite), never via the root
 * composer.json `autoload-dev.files`. A global `files` entry loads the stub on
 * every autoload — before Drupal registers its real module namespaces — so the
 * stub's guarded `interface NodeInterface {}` wins the race and shadows core's
 * real NodeInterface for the whole repo. That made every node-based Kernel and
 * Functional test (VVJ's and Drupal core's own) fail with
 * "node does not implement \Drupal\user\EntityOwnerInterface".
 *
 * The autoloader is located rather than computed from a fixed number of parent
 * directories. A hard-coded `dirname(__DIR__, N)` cannot work here: `__DIR__`
 * resolves symlinks, and this module is normally developed as a symlink into
 * `web/modules/custom/`, so the real path is nowhere near the Drupal root.
 * Composer-based Drupal projects also disagree on where `vendor/` lives
 * (project root vs. docroot). Discovery handles every layout; set
 * `PB_COMPOSER_AUTOLOAD` to override it explicitly.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */

declare(strict_types=1);

use Composer\Autoload\ClassLoader;

/**
 * Locates the active Composer class loader.
 *
 * @return \Composer\Autoload\ClassLoader
 *   The class loader to register this module's namespaces on.
 */
function paragraphs_bundles_test_loader(): ClassLoader {
  // 1. Running through `vendor/bin/phpunit` means Composer's bin proxy has
  // already required autoload.php. Reuse that instance — it is the only option
  // that is correct regardless of where the module directory physically lives.
  foreach (spl_autoload_functions() ?: [] as $callback) {
    if (is_array($callback) && ($callback[0] ?? NULL) instanceof ClassLoader) {
      return $callback[0];
    }
  }

  // 2. Explicit override, for runners that bootstrap PHPUnit differently.
  $candidates = [];
  $override = getenv('PB_COMPOSER_AUTOLOAD');
  if (is_string($override) && $override !== '') {
    $candidates[] = $override;
  }

  // 3. Walk up from this file, then from the module directory as it was
  // *addressed* (which preserves the symlink path when one is in play).
  $starts = [__DIR__];
  $cwd = getcwd();
  if (is_string($cwd)) {
    $starts[] = $cwd;
  }
  foreach ($starts as $start) {
    $dir = $start;
    while (TRUE) {
      $candidates[] = $dir . '/vendor/autoload.php';
      $parent = dirname($dir);
      if ($parent === $dir) {
        break;
      }
      $dir = $parent;
    }
  }

  foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
      $loader = require $candidate;
      if ($loader instanceof ClassLoader) {
        return $loader;
      }
    }
  }

  fwrite(STDERR, sprintf(
    "paragraphs_bundles: could not locate vendor/autoload.php.\n"
    . "Searched upward from %s and %s.\n"
    . "Set PB_COMPOSER_AUTOLOAD to the absolute path of your Composer autoloader.\n",
    __DIR__,
    is_string($cwd) ? $cwd : '(unknown cwd)',
  ));
  exit(1);
}

$loader = paragraphs_bundles_test_loader();

// Register the suite's own namespaces. Drupal registers module namespaces from
// its kernel, which these pure-Unit tests deliberately do not boot, and
// Composer knows nothing about modules dropped into a docroot — so without
// this the classes under test are simply not autoloadable.
$module_root = dirname(__DIR__);
$loader->addPsr4('Drupal\\paragraphs_bundles\\', $module_root . '/src');
$loader->addPsr4('Drupal\\Tests\\paragraphs_bundles\\', $module_root . '/tests/src');

foreach (glob($module_root . '/modules/*', GLOB_ONLYDIR) ?: [] as $submodule) {
  $name = basename($submodule);
  $loader->addPsr4('Drupal\\' . $name . '\\', $submodule . '/src');
  $loader->addPsr4('Drupal\\Tests\\' . $name . '\\', $submodule . '/tests/src');
}

require __DIR__ . '/stubs/drupal-entity-stubs.php';
