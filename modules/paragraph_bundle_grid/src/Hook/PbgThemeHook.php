<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_grid\Hook;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers grid bundle paragraph template.
 */
final class PbgThemeHook {

  public function __construct(
    private readonly ModuleExtensionList $moduleList,
  ) {}

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraph__grid_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
