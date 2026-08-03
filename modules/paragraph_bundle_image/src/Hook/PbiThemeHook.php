<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_image\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers image bundle paragraph templates.
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
      'paragraph__image_bundle' => [
        'base hook' => 'paragraph',
      ],
      'paragraph__image_wide_bundle' => [
        'base hook' => 'paragraph',
      ],
      'paragraph__image_narrow_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
