<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_layout\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers one/two/three column bundle paragraph templates.
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
      'paragraph__one_column_bundle' => [
        'base hook' => 'paragraph',
      ],
      'paragraph__two_columns_bundle' => [
        'base hook' => 'paragraph',
      ],
      'paragraph__three_columns_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
