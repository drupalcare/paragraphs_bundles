<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_patterns\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\paragraphs_bundles\Service\Theme\PbThemeBridgeServiceInterface;
use Drupal\paragraphs_bundles_patterns\Hook\PatternLibraryInfoAlterHook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * The color bridge is added for Solo and APEX, and for nothing else.
 */
#[CoversClass(PatternLibraryInfoAlterHook::class)]
#[Group('paragraphs_bundles')]
final class PatternLibraryInfoAlterHookTest extends UnitTestCase {

  /**
   * Each theme family gets exactly its own bridge.
   */
  #[DataProvider('bridges')]
  public function testBridgeDependency(string $id, array $expected): void {
    $libraries = ['base' => []];
    $this->hook($id)->libraryInfoAlter($libraries, 'paragraphs_bundles_patterns');
    $this->assertSame($expected, $libraries['base']['dependencies'] ?? []);
  }

  /**
   * Another extension's libraries are left alone.
   */
  public function testOtherExtensionUntouched(): void {
    $libraries = ['base' => []];
    $this->hook(PbThemeBridgeServiceInterface::BRIDGE_SOLO)->libraryInfoAlter($libraries, 'paragraphs_bundles');
    $this->assertSame(['base' => []], $libraries);
  }

  /**
   * Bridge ids and the dependency each one adds.
   *
   * @return array<string, array{string, list<string>}>
   *   The cases.
   */
  public static function bridges(): array {
    return [
      'solo' => [PbThemeBridgeServiceInterface::BRIDGE_SOLO, ['paragraphs_bundles_patterns/theme-bridge-solo']],
      'apex' => [PbThemeBridgeServiceInterface::BRIDGE_APEX, ['paragraphs_bundles_patterns/theme-bridge-apex']],
      'neutral' => [PbThemeBridgeServiceInterface::BRIDGE_NEUTRAL, []],
    ];
  }

  /**
   * Builds the hook for a bridge id.
   */
  private function hook(string $id): PatternLibraryInfoAlterHook {
    $bridge = $this->createMock(PbThemeBridgeServiceInterface::class);
    $bridge->method('resolveBridgeId')->willReturn($id);
    return new PatternLibraryInfoAlterHook($bridge);
  }

}
