<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers PB-owned templates.
 *
 * Mirror of v1's procedural `paragraphs_bundles_theme()`.
 */
final class PbThemeHook {

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraph__default' => [
        'base hook' => 'paragraph',
      ],
      'paragraph__simple_bundle' => [
        'base hook' => 'paragraph',
      ],
      'field__entity_reference_revisions' => [
        'base hook' => 'field',
      ],
    ];
  }

}
