<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_modal\Hook;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers modal bundle paragraph template.
 */
final class PbmThemeHook {

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
      'paragraph__modal_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
