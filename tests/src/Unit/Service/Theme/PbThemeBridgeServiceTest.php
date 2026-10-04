<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit\Service\Theme;

use Drupal\Core\Extension\Extension;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\paragraphs_bundles\Service\Theme\PbThemeBridgeService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PbThemeBridgeService.
 */
#[CoversClass(PbThemeBridgeService::class)]
#[Group('paragraphs_bundles')]
final class PbThemeBridgeServiceTest extends TestCase {

  /**
   * Resolves the bridge id and library name from the active theme chain.
   *
   * @param list<string> $chain
   *   Active theme name followed by base theme names.
   * @param string $expectedBridge
   *   Expected bridge id.
   */
  #[DataProvider('themeChainProvider')]
  public function testResolveBridge(array $chain, string $expectedBridge): void {
    $activeTheme = $this->createActiveTheme($chain);
    $themeManager = $this->createStub(ThemeManagerInterface::class);
    $themeManager->method('getActiveTheme')->willReturn($activeTheme);

    $service = new PbThemeBridgeService($themeManager);
    $this->assertSame($expectedBridge, $service->resolveBridgeId());
    $this->assertSame(
      'theme-bridge-' . $expectedBridge,
      $service->resolveLibraryName(),
    );
  }

  /**
   * Theme hierarchy scenarios.
   *
   * @return list<array{list<string>, string}>
   *   Sets of theme base chain and expected bridge id.
   */
  public static function themeChainProvider(): array {
    return [
      [['apex_news', 'apex_theme'], 'apex'],
      [['solo_sub', 'solo'], 'solo'],
      [['solo'], 'solo'],
      [['olivero'], 'neutral'],
      [['custom_starter'], 'neutral'],
    ];
  }

  /**
   * Builds an ActiveTheme mock for a name + base chain.
   *
   * @param list<string> $chain
   *   Active theme first, then bases.
   *
   * @return \Drupal\Core\Theme\ActiveTheme
   *   Mock active theme.
   */
  private function createActiveTheme(array $chain): ActiveTheme {
    $active = array_shift($chain);
    $bases = [];
    foreach ($chain as $name) {
      $extension = $this->createStub(Extension::class);
      $bases[$name] = $extension;
    }

    $theme = $this->createStub(ActiveTheme::class);
    $theme->method('getName')->willReturn($active);
    $theme->method('getBaseThemeExtensions')->willReturn($bases);
    return $theme;
  }

}
