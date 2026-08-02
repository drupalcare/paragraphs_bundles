<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_content\Service\Region;

/**
 * Maps pb_display_disable_region values to theme page region keys.
 */
interface PbcRegionMapInterface {

  /**
   * Page regions hidden on every pb_content node for a theme family.
   *
   * @param string $family
   *   Theme family from {@see PbcThemeFamilyResolverInterface}.
   *
   * @return list<string>
   *   Keys under `$variables['page']` to unset.
   */
  public function defaultHiddenRegions(string $family): array;

  /**
   * Resolve one editor disable key to page region keys.
   *
   * @param string $family
   *   Theme family from {@see PbcThemeFamilyResolverInterface}.
   * @param string $disableKey
   *   Value stored on pb_display_disable_region.
   *
   * @return list<string>
   *   Keys under `$variables['page']` to unset; empty when unknown.
   */
  public function pageRegionsForDisableKey(string $family, string $disableKey): array;

  /**
   * Extra libraries to attach when a disable key needs CSS fallback.
   *
   * @param string $family
   *   Theme family from {@see PbcThemeFamilyResolverInterface}.
   * @param string $disableKey
   *   Value stored on pb_display_disable_region.
   *
   * @return list<string>
   *   Library ids (module-prefixed), e.g.
   *   `paragraph_bundle_content/hide-footer-menu`.
   */
  public function hideLibrariesForDisableKey(string $family, string $disableKey): array;

  /**
   * Front-end library id for theme-specific pb_content layout CSS.
   *
   * @param string $family
   *   Theme family from {@see PbcThemeFamilyResolverInterface}.
   *
   * @return string|null
   *   Library id without module prefix, or NULL when only shared CSS applies.
   */
  public function layoutLibraryName(string $family): ?string;

}
