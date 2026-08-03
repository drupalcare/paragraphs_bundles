<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_hero\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers hero bundle paragraph template.
 */
final class PbhThemeHook {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraph__hero_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
