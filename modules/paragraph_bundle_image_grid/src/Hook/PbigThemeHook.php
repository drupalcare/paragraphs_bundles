<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_image_grid\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers image grid bundle paragraph templates.
 */
final class PbigThemeHook {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraph__image_grid_bundle' => [
        'base hook' => 'paragraph',
      ],
      'paragraph__image_grid_section_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
