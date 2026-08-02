<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_content\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraph_bundle_content\Service\Region\PbcRegionMapInterface;
use Drupal\paragraph_bundle_content\Service\Theme\PbcThemeFamilyResolverInterface;

/**
 * Page preprocess — admin assets and pb_content region/layout behaviour.
 */
final class PbcPageHook {

  public function __construct(
    private readonly AdminContext $adminContext,
    private readonly RouteMatchInterface $routeMatch,
    private readonly PbcThemeFamilyResolverInterface $themeFamily,
    private readonly PbcRegionMapInterface $regionMap,
  ) {}

  /**
   * Implements hook_preprocess_page().
   *
   * @param array<string, mixed> $variables
   *   Preprocess variables (passed by reference).
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(array &$variables): void {
    if ($this->adminContext->isAdminRoute()) {
      return;
    }

    $node = $this->routeMatch->getParameter('node')
      ?? $this->routeMatch->getParameter('node_preview');

    if (!$node instanceof NodeInterface || $node->bundle() !== 'pb_content') {
      return;
    }

    $family = $this->themeFamily->resolveActiveFamily();

    $variables['#attached']['library'][] = 'paragraph_bundle_content/paragraph-bundle-content-base';
    $layoutLibrary = $this->regionMap->layoutLibraryName($family);
    if ($layoutLibrary !== NULL) {
      $variables['#attached']['library'][] = 'paragraph_bundle_content/' . $layoutLibrary;
    }

    foreach ($this->regionMap->defaultHiddenRegions($family) as $region) {
      unset($variables['page'][$region]);
    }

    if (!$node->hasField('pb_display_disable_region') || $node->get('pb_display_disable_region')->isEmpty()) {
      return;
    }

    $selectedRegions = array_column($node->get('pb_display_disable_region')->getValue(), 'value');
    foreach ($selectedRegions as $disableKey) {
      if (!is_string($disableKey)) {
        continue;
      }
      foreach ($this->regionMap->pageRegionsForDisableKey($family, $disableKey) as $region) {
        unset($variables['page'][$region]);
      }
      foreach ($this->regionMap->hideLibrariesForDisableKey($family, $disableKey) as $library) {
        $variables['#attached']['library'][] = $library;
      }
    }
  }

}
