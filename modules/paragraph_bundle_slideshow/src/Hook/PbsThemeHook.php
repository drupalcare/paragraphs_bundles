<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_slideshow\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers slideshow bundle paragraph templates.
 */
final class PbsThemeHook {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraph__slideshow_bundle' => [
        'base hook' => 'paragraph',
      ],
      'paragraph__slideshow_section_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
