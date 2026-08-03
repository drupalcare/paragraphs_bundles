<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_carousel\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers carousel bundle paragraph template.
 */
final class PbcaThemeHook {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraph__carousel_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
