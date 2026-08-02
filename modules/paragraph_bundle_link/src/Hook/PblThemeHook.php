<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_link\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — link_bundle and link_section_bundle local Twig templates.
 */
final class PblThemeHook {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraph__link_bundle' => [
        'base hook' => 'paragraph',
      ],
      'paragraph__link_section_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
