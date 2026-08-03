<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_accordion\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers accordion bundle paragraph templates.
 */
final class PbacThemeHook {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraph__accordion_bundle' => [
        'base hook' => 'paragraph',
      ],
      'paragraph__accordion_section_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
