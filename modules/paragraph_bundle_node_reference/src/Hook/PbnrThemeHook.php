<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_node_reference\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers node reference bundle paragraph template.
 */
final class PbnrThemeHook {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraph__node_reference_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
