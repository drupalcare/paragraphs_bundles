<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Traits;

/**
 * Walks the files this module SHIPS, and nothing the host site put around them.
 *
 * WHY THIS EXISTS. The contract tests that scan "every shipped config file" or
 * "every shipped stylesheet" start from `dirname(__DIR__, 3)`. On a developer's
 * site that is the module directory. On drupal.org CI it is the whole site:
 * the project is checked out at /builds/project/paragraphs_bundles and Drupal is
 * built INSIDE it (`web/`, `vendor/`), with the module symlinked into
 * `web/modules/custom`. PHP resolves __DIR__ through the symlink, so the "module
 * root" contained core, every contrib module and phpunit's own bootstrap.css —
 * and the pipeline of 2026-09-28 reported paragraphs' library bundle, webform's
 * test bundles and bootstrap.min.css as defects of THIS module.
 *
 * Only a top-level `web`, `vendor` or `node_modules` is pruned. The module has
 * no such directory of its own, and anything deeper is the module's to scan.
 */
trait ModuleFilesTrait {

  /**
   * Returns an iterator over the files under a module root, host site pruned.
   *
   * @param string $root
   *   The module root, without a trailing slash.
   *
   * @return \RecursiveIteratorIterator<\RecursiveCallbackFilterIterator<string, \SplFileInfo, \RecursiveDirectoryIterator>>
   *   Every file and directory the module owns, depth first.
   */
  private function moduleFiles(string $root): \RecursiveIteratorIterator {
    $hostDirs = [$root . '/web', $root . '/vendor', $root . '/node_modules'];
    return new \RecursiveIteratorIterator(
      new \RecursiveCallbackFilterIterator(
        new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        static fn (\SplFileInfo $current): bool => !in_array($current->getPathname(), $hostDirs, TRUE),
      ),
    );
  }

}
