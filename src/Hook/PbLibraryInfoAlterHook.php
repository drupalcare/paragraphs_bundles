<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs_bundles\Service\Theme\PbThemeBridgeServiceInterface;

/**
 * Adds the active theme-bridge library as a dependency of foundation CSS.
 */
final class PbLibraryInfoAlterHook {

  public function __construct(
    private readonly PbThemeBridgeServiceInterface $themeBridge,
  ) {}

  /**
   * Implements hook_library_info_alter().
   *
   * @param array<string, array<string, mixed>> $libraries
   *   Library definitions for the extension being altered.
   * @param string $extension
   *   Module or theme extension name.
   */
  #[Hook('library_info_alter')]
  public function libraryInfoAlter(array &$libraries, string $extension): void {
    if ($extension !== 'paragraphs_bundles') {
      return;
    }
    if (!isset($libraries['paragraphs-bundles'])) {
      return;
    }
    $bridge = 'paragraphs_bundles/' . $this->themeBridge->resolveLibraryName();
    $libraries['paragraphs-bundles']['dependencies'] ??= [];
    if (!in_array($bridge, $libraries['paragraphs-bundles']['dependencies'], TRUE)) {
      $libraries['paragraphs-bundles']['dependencies'][] = $bridge;
    }
  }

}
