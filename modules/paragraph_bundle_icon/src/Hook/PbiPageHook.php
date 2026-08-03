<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_icon\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\AdminContext;
use Drupal\paragraphs_bundles\Utility\PbRenderArray;

/**
 * Page preprocess — attaches icon admin assets on admin routes.
 */
final class PbiPageHook {

  public function __construct(
    private readonly AdminContext $adminContext,
  ) {}

  /**
   * Implements hook_preprocess_page().
   *
   * @param array<string, mixed> $variables
   *   Preprocess variables (passed by reference).
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    if (!$this->adminContext->isAdminRoute()) {
      return;
    }

    PbRenderArray::attachLibraries($variables, 'paragraph_bundle_icon/paragraph-bundle-icon-admin');
  }

}
