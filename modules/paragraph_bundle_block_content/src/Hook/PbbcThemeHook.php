<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_block_content\Hook;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — maps block_content_bundle to parent paragraph--default template.
 */
final class PbbcThemeHook {

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
      'paragraph__block_content_bundle' => [
        'base hook' => 'paragraph',
        'template' => 'paragraph--default',
        'path' => $this->moduleList->getPath('paragraphs_bundles') . '/templates',
      ],
    ];
  }

}
