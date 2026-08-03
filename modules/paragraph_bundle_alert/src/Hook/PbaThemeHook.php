<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_alert\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers alert bundle paragraph template.
 */
final class PbaThemeHook {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraph__alert_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
