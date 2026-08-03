<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_content\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraph_bundle_content\Service\Region\PbcRegionMapInterface;
use Drupal\paragraph_bundle_content\Service\Theme\PbcThemeFamilyResolverInterface;
use Drupal\paragraphs_bundles\Utility\PbRenderArray;

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

    PbRenderArray::attachLibraries($variables, 'paragraph_bundle_content/paragraph-bundle-content-base');
    $layoutLibrary = $this->regionMap->layoutLibraryName($family);
    if ($layoutLibrary !== NULL) {
      PbRenderArray::attachLibraries($variables, 'paragraph_bundle_content/' . $layoutLibrary);
    }

    $this->hideRegions($variables, $this->regionMap->defaultHiddenRegions($family));

    if (!$node->hasField('pb_display_disable_region') || $node->get('pb_display_disable_region')->isEmpty()) {
      return;
    }

    $stored = $node->get('pb_display_disable_region')->getValue();
    $selectedRegions = is_array($stored) ? array_column($stored, 'value') : [];
    foreach ($selectedRegions as $disableKey) {
      if (!is_string($disableKey)) {
        continue;
      }
      $this->hideRegions($variables, $this->regionMap->pageRegionsForDisableKey($family, $disableKey));
      PbRenderArray::attachLibraries(
        $variables,
        ...$this->regionMap->hideLibrariesForDisableKey($family, $disableKey),
      );
    }
  }

  /**
   * Removes page regions from the page variables.
   *
   * @param array<string, mixed> $variables
   *   Page preprocess variables (passed by reference).
   * @param list<string> $regions
   *   Region machine names to remove.
   */
  private function hideRegions(array &$variables, array $regions): void {
    if ($regions === []) {
      return;
    }
    $page = $variables['page'] ?? NULL;
    if (!is_array($page)) {
      return;
    }
    foreach ($regions as $region) {
      unset($page[$region]);
    }
    $variables['page'] = $page;
  }

}
