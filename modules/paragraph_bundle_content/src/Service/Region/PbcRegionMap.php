<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_content\Service\Region;

use Drupal\paragraph_bundle_content\Service\Theme\PbcThemeFamilyResolverInterface;

/**
 * Default {@see PbcRegionMapInterface} implementation.
 *
 * Editor field values (pb_display_disable_region) stay identical to v1.
 * Each value expands to Solo or APEX page region machine names.
 */
final class PbcRegionMap implements PbcRegionMapInterface {

  /**
   * Solo page region keys always removed on pb_content nodes.
   *
   * @var list<string>
   */
  private const array SOLO_DEFAULT_HIDDEN = [
    'sidebar_first',
    'sidebar_second',
  ];

  /**
   * APEX page region keys always removed on pb_content nodes.
   *
   * @var list<string>
   */
  private const array APEX_DEFAULT_HIDDEN = [
    'sidebar_first',
    'sidebar_second',
  ];

  /**
   * Disable-key → Solo `$page` region keys (and grouped keys).
   *
   * @var array<string, list<string>>
   */
  private const array SOLO_DISABLE_MAP = [
    'popup_login_block' => ['popup_login_block'],
    'fixed_search_block' => ['fixed_search_block'],
    'header' => ['header'],
    'primary_menu' => ['primary_menu'],
    'primary_sidebar_menu' => ['primary_sidebar_menu'],
    'welcome_text' => ['welcome_text'],
    'highlighted' => ['highlighted'],
    'top_regions' => ['top_first', 'top_second', 'top_third'],
    'messages' => ['system_messages'],
    'page_title' => ['page_title'],
    'breadcrumb' => ['breadcrumb'],
    'content_above' => [],
    'content_below' => [],
    'bottom_regions' => [
      'bottom_first',
      'bottom_second',
      'bottom_third',
      'bottom_fourth',
    ],
    'footer_regions' => [
      'footer_first',
      'footer_second',
      'footer_third',
    ],
    'footer_menu' => ['footer_menu'],
    'copyright' => [],
  ];

  /**
   * Disable-key → APEX `$page` region keys (and grouped keys).
   *
   * @var array<string, list<string>>
   */
  private const array APEX_DISABLE_MAP = [
    'popup_login_block' => ['overlay_login'],
    'fixed_search_block' => ['overlay_search'],
    'header' => ['header'],
    'primary_menu' => ['nav_primary'],
    'primary_sidebar_menu' => ['nav_offcanvas'],
    'welcome_text' => ['hero'],
    'highlighted' => ['highlight'],
    'top_regions' => ['top_first', 'top_second', 'top_third'],
    'messages' => ['messages'],
    'page_title' => ['title'],
    'breadcrumb' => ['breadcrumb'],
    'content_above' => ['content_above'],
    'content_below' => ['content_below'],
    'bottom_regions' => [
      'bottom_first',
      'bottom_second',
      'bottom_third',
      'bottom_fourth',
    ],
    'footer_regions' => [
      'footer_first',
      'footer_second',
      'footer_third',
    ],
    'footer_menu' => ['nav_footer'],
    'copyright' => [],
  ];

  /**
   * {@inheritdoc}
   */
  public function defaultHiddenRegions(string $family): array {
    return match ($family) {
      PbcThemeFamilyResolverInterface::FAMILY_APEX => self::APEX_DEFAULT_HIDDEN,
      PbcThemeFamilyResolverInterface::FAMILY_SOLO => self::SOLO_DEFAULT_HIDDEN,
      default => self::SOLO_DEFAULT_HIDDEN,
    };
  }

  /**
   * {@inheritdoc}
   */
  public function pageRegionsForDisableKey(string $family, string $disableKey): array {
    $map = match ($family) {
      PbcThemeFamilyResolverInterface::FAMILY_APEX => self::APEX_DISABLE_MAP,
      default => self::SOLO_DISABLE_MAP,
    };
    return $map[$disableKey] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function hideLibrariesForDisableKey(string $family, string $disableKey): array {
    return match ($disableKey) {
      'footer_menu' => ['paragraph_bundle_content/hide-footer-menu'],
      'copyright' => ['paragraph_bundle_content/hide-copyright'],
      default => [],
    };
  }

  /**
   * {@inheritdoc}
   */
  public function layoutLibraryName(string $family): string {
    return match ($family) {
      PbcThemeFamilyResolverInterface::FAMILY_APEX => 'paragraph-bundle-content-apex',
      PbcThemeFamilyResolverInterface::FAMILY_SOLO => 'paragraph-bundle-content-solo',
      default => 'paragraph-bundle-content-neutral',
    };
  }

}
