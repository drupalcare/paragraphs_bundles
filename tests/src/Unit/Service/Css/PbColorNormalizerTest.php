<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit\Service\Css;

use Drupal\paragraphs_bundles\Service\Css\PbColorNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PbColorNormalizer.
 */
#[CoversClass(PbColorNormalizer::class)]
#[Group('paragraphs_bundles')]
final class PbColorNormalizerTest extends TestCase {

  /**
   * The normalizer under test.
   */
  private PbColorNormalizer $normalizer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->normalizer = new PbColorNormalizer();
  }

  /**
   * Formats a raw opacity value into a CSS alpha string.
   */
  #[DataProvider('opacityProvider')]
  public function testFormatOpacity(string $raw, string $expected): void {
    $this->assertSame($expected, $this->normalizer->formatOpacity($raw));
  }

  /**
   * Data provider for opacity formatting.
   *
   * @return list<array{string, string}>
   *   Sets of input opacity value and expected formatted string.
   */
  public static function opacityProvider(): array {
    return [
      ['', '1'],
      ['100', '1'],
      ['36', '0.36'],
      ['0', '0'],
    ];
  }

  /**
   * Builds an rgba() overlay string from a hex background color.
   */
  public function testOverlayRgbaFromHex(): void {
    $result = $this->normalizer->overlayRgba('#fafdff', '36');
    $this->assertSame('rgba(250, 253, 255, 0.36)', $result);
  }

  /**
   * Returns an empty string when the background color is empty.
   */
  public function testOverlayRgbaEmptyBg(): void {
    $this->assertSame('', $this->normalizer->overlayRgba('', '36'));
  }

  /**
   * Expands a short hex color into an r, g, b triplet.
   */
  public function testNormaliseShortHex(): void {
    $this->assertSame(
      '0, 0, 0',
      $this->normalizer->normaliseToRgbTriplet('#000'),
    );
  }

}
