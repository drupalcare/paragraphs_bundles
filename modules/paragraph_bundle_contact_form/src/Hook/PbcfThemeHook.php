<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_contact_form\Hook;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — maps contact_form_bundle to parent paragraph--default template.
 */
final class PbcfThemeHook {

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
      'paragraph__contact_form_bundle' => [
        'base hook' => 'paragraph',
        'template' => 'paragraph--default',
        'path' => $this->moduleList->getPath('paragraphs_bundles') . '/templates',
      ],
    ];
  }

}
