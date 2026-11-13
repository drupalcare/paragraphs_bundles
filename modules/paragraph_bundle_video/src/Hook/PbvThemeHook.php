<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_video\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers the video bundle paragraph template.
 */
final class PbvThemeHook {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraph__video_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
