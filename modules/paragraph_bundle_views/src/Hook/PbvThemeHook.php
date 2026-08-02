<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_views\Hook;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — maps views_bundle to parent paragraph--default template.
 */
final class PbvThemeHook {

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
      'paragraph__views_bundle' => [
        'base hook' => 'paragraph',
        'template' => 'paragraph--default',
        'path' => $this->moduleList->getPath('paragraphs_bundles') . '/templates',
      ],
    ];
  }

}
