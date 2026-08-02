<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_card\Hook;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers card bundle paragraph templates.
 */
final class PbcardThemeHook {

  public function __construct(
    private readonly ModuleExtensionList $moduleList,
  ) {}

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, mixed>>
   *   Theme hook definitions.
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'paragraph__card_two_columns_bundle' => [
        'base hook' => 'paragraph',
      ],
      'paragraph__card_three_columns_bundle' => [
        'base hook' => 'paragraph',
      ],
      'paragraph__card_text_section_bundle' => [
        'base hook' => 'paragraph',
      ],
      'paragraph__card_image_section_bundle' => [
        'base hook' => 'paragraph',
      ],
    ];
  }

}
