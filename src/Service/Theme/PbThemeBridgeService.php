<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Theme;

use Drupal\Core\Theme\ActiveTheme;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Default {@see PbThemeBridgeServiceInterface} implementation.
 */
final class PbThemeBridgeService implements PbThemeBridgeServiceInterface {

  public function __construct(
    private readonly ThemeManagerInterface $themeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function resolveBridgeId(): string {
    return $this->bridgeIdFromTheme($this->themeManager->getActiveTheme());
  }

  /**
   * {@inheritdoc}
   */
  public function resolveLibraryName(): string {
    return 'theme-bridge-' . $this->resolveBridgeId();
  }

  /**
   * Maps an active theme and its bases to a bridge id.
   *
   * @param \Drupal\Core\Theme\ActiveTheme $theme
   *   Active front theme.
   *
   * @return string
   *   Bridge id constant.
   */
  private function bridgeIdFromTheme(ActiveTheme $theme): string {
    foreach ($this->themeChain($theme) as $name) {
      if ($name === 'apex_theme' || str_starts_with($name, 'apex_')) {
        return self::BRIDGE_APEX;
      }
      if ($name === 'solo' || str_starts_with($name, 'solo_')) {
        return self::BRIDGE_SOLO;
      }
    }
    return self::BRIDGE_NEUTRAL;
  }

  /**
   * Active theme name followed by base theme names.
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
