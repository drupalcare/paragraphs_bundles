<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_image_background\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers image background bundle paragraph template.
 */
final class PbibThemeHook {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraph__image_background_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
