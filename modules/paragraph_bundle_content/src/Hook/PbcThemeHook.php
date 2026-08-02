<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_content\Hook;

use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme hook — registers pb_content node and field templates.
 */
final class PbcThemeHook {

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
    $path = $this->moduleList->getPath('paragraph_bundle_content') . '/templates';
    return [
      'node__pb_content' => [
        'base hook' => 'node',
      ],
      'block__pb_block' => [
        'base hook' => 'block',
        'template' => 'block--pb-block',
        'path' => $path,
      ],
      'block_content__pb_block' => [
        'base hook' => 'block_content',
        'template' => 'block-content--pb-block',
        'path' => $path,
      ],
      'field__pb_content__pb_content_reference_para_block' => [
        'base hook' => 'field',
        'template' => 'field--pb-content-reference-para-block',
        'path' => $path,
      ],
      'field__pb_content__pb_content_type_body' => [
        'base hook' => 'field',
        'template' => 'field--pb-content-type-body',
        'path' => $path,
      ],
    ];
  }

}
