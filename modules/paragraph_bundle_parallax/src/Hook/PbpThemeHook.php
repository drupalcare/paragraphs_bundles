<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_parallax\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers parallax bundle paragraph templates.
 */
final class PbpThemeHook {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraph__parallax_bundle' => [
        'base hook' => 'paragraph',
      ],
      'paragraph__parallax_section_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
