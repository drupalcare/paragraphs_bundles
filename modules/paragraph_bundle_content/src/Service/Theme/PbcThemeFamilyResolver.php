<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_content\Service\Theme;

use Drupal\apex\Service\ThemeDetectionServiceInterface;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Default {@see PbcThemeFamilyResolverInterface} implementation.
 */
final class PbcThemeFamilyResolver implements PbcThemeFamilyResolverInterface {

  /**
   * Constructs the resolver.
   *
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager (used for the Solo/other fallback scan).
   * @param \Drupal\apex\Service\ThemeDetectionServiceInterface|null $apexThemeDetection
   *   The apex theme-detection service when the apex module is installed; NULL
   *   otherwise. When present it is the single source of truth for whether the
   *   active theme is apex_theme or an apex sub-theme.
   */
  public function __construct(
    private readonly ThemeManagerInterface $themeManager,
    private readonly ?ThemeDetectionServiceInterface $apexThemeDetection = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function resolveActiveFamily(): string {
    // Prefer apex's authoritative detection: it walks the base-theme chain, so
    // it correctly identifies apex_theme AND every apex sub-theme (even one
    // not named `apex_*`). Falls through to the name scan when apex is absent.
    if ($this->apexThemeDetection?->isApexTheme() === TRUE) {
      return self::FAMILY_APEX;
    }
    return $this->familyFromTheme($this->themeManager->getActiveTheme());
  }

  /**
   * Walk the active theme and its base themes for a known family.
   *
   * @param \Drupal\Core\Theme\ActiveTheme $theme
   *   Active theme object.
   *
   * @return string
   *   Layout family constant.
   */
  private function familyFromTheme(ActiveTheme $theme): string {
    foreach ($this->themeChain($theme) as $name) {
      if ($name === 'apex_theme' || str_starts_with($name, 'apex_')) {
        return self::FAMILY_APEX;
      }
      if ($name === 'solo' || str_starts_with($name, 'solo_')) {
        return self::FAMILY_SOLO;
      }
    }
    return self::FAMILY_OTHER;
  }

  /**
   * Active theme name followed by base theme names (root last).
   *
   * @param \Drupal\Core\Theme\ActiveTheme $theme
   *   Active theme object.
   *
   * @return list<string>
   *   Theme machine names.
   */
  private function themeChain(ActiveTheme $theme): array {
    $chain = [$theme->getName()];
    foreach (array_keys($theme->getBaseThemeExtensions()) as $base) {
      $chain[] = $base;
    }
    return $chain;
  }

}
