<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraph_bundle_content\Unit\Service\Region;

use Drupal\paragraph_bundle_content\Service\Region\PbcRegionMap;
use Drupal\paragraph_bundle_content\Service\Theme\PbcThemeFamilyResolverInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PbcRegionMap layout-library resolution.
 */
#[CoversClass(PbcRegionMap::class)]
#[Group('paragraphs_bundles')]
final class PbcRegionMapTest extends TestCase {

  /**
   * The region map under test.
   */
  private PbcRegionMap $map;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->map = new PbcRegionMap();
  }

  /**
   * Each theme family resolves to its content layout library.
   */
  #[DataProvider('layoutLibraryProvider')]
  public function testLayoutLibraryName(string $family, string $expected): void {
    $this->assertSame($expected, $this->map->layoutLibraryName($family));
  }

  /**
   * Data provider for layout-library resolution.
   *
   * @return array<string, array{string, string}>
   *   Sets of theme family and the expected content layout library name.
   */
  public static function layoutLibraryProvider(): array {
    return [
      'apex family' => [
        PbcThemeFamilyResolverInterface::FAMILY_APEX,
        'paragraph-bundle-content-apex',
      ],
      'solo family' => [
        PbcThemeFamilyResolverInterface::FAMILY_SOLO,
        'paragraph-bundle-content-solo',
      ],
      'other family maps to neutral' => [
        PbcThemeFamilyResolverInterface::FAMILY_OTHER,
        'paragraph-bundle-content-neutral',
      ],
      'unknown family falls back to neutral' => [
        'a-theme-family-that-does-not-exist',
        'paragraph-bundle-content-neutral',
      ],
    ];
  }

}
