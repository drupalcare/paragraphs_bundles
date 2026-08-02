<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_3d_flip_box\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers 3D flip box bundle paragraph template.
 */
final class Pb3dfbThemeHook {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraph__3d_flip_box_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
