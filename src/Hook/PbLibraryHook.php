<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs_bundles\Service\Css\PbDynamicLibraryRegistrarInterface;

/**
 * Surfaces every per-paragraph CSS file as a Drupal library entry.
 *
 * `hook_library_info_build()` is invoked once per library-cache build.
 * The dispatcher then merges the returned array into the canonical
 * library registry, so by the time `attach_library` runs in Twig the
 * dynamic libraries are addressable as
 * `paragraphs_bundles/dynamic--{slug}-{id}`.
 */
final class PbLibraryHook {

  public function __construct(
    private readonly PbDynamicLibraryRegistrarInterface $registrar,
  ) {}

  /**
   * Implements hook_library_info_build().
   *
   * @return array<string, array{
   *   header: bool,
   *   css: array<string, array<string, array<string, mixed>>>
   *   }>
   *   Library definitions keyed by library name.
   */
  #[Hook('library_info_build')]
  public function libraryInfoBuild(): array {
    return $this->registrar->buildLibraries();
  }

}
