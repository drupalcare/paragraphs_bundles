<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_3d_carousel\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers 3D carousel bundle paragraph template.
 */
final class Pb3dcThemeHook {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraph__3d_carousel_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
