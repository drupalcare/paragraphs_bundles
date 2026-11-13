<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_patterns\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs_bundles\Service\Theme\PbThemeBridgeServiceInterface;

/**
 * Adds the active theme's color bridge to the Patterns base library.
 *
 * Library definitions are cached per active theme, so the bridge chosen here
 * is the one for the theme that renders the page. The neutral bridge adds
 * nothing: other themes keep the base colors.
 */
final class PatternLibraryInfoAlterHook {

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
    if ($extension !== 'paragraphs_bundles_patterns' || !isset($libraries['base'])) {
      return;
    }
    $id = $this->themeBridge->resolveBridgeId();
    if ($id === PbThemeBridgeServiceInterface::BRIDGE_NEUTRAL) {
      return;
    }
    $dependencies = $libraries['base']['dependencies'] ?? [];
    $dependencies = is_array($dependencies) ? $dependencies : [];
    $dependencies[] = 'paragraphs_bundles_patterns/theme-bridge-' . $id;
    $libraries['base']['dependencies'] = $dependencies;
  }

}
