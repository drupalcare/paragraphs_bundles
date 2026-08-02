<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_tabs\Hook;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers tabs bundle paragraph templates.
 */
final class PbtThemeHook {

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
      'paragraph__tabs_bundle' => [
        'base hook' => 'paragraph',
      ],
      'paragraph__tab_section_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
