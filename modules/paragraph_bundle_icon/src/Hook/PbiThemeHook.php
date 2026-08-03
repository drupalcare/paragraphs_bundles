<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_icon\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers icon bundle paragraph template.
 */
final class PbiThemeHook {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraph__icon_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
