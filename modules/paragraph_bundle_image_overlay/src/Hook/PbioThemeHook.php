<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_image_overlay\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers image overlay bundle paragraph template.
 */
final class PbioThemeHook {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraph__image_overlay_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
