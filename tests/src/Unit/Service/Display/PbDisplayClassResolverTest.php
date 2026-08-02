<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit\Service\Display;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\paragraphs_bundles\Service\Display\PbDisplayClassResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PbDisplayClassResolver.
 *
 * The per-bundle field list is a DOM contract: which display fields land on a
 * bundle's inner wrapper, and in what order. It used to be restated in each of
 * 31 templates, where a divergence was invisible.
 */
#[CoversClass(PbDisplayClassResolver::class)]
#[Group('paragraphs_bundles')]
final class PbDisplayClassResolverTest extends TestCase {

  /**
   * Unmapped values pass through rather than disappearing.
   */
  public function testUnmappedValuePassesThrough(): void {
    $resolver = new PbDisplayClassResolver();
    $this->assertSame('pb-not-in-the-map', $resolver->className('pb-not-in-the-map'));
    $this->assertSame('', $resolver->className(''));
  }

  /**
   * Bundles take their declared display fields, in order.
   *
   * @param string $bundle
   *   Paragraph bundle.
   * @param list<string> $expected
   *   Fields expected to contribute a class, in order.
   */
  #[DataProvider('bundleFieldProvider')]
  public function testInnerClassFieldsPerBundle(string $bundle, array $expected): void {
    $resolver = new PbDisplayClassResolver();
    // Values are echoed back when unmapped, so the returned classes name the
    // fields that were read, in the order they were read.
    $this->assertSame(
      $expected,
      $resolver->innerClasses($this->paragraphWithAllDisplayFields($bundle)),
    );
  }

  /**
   * Bundles whose inner wrapper differs from the shared default.
   *
   * @return array<string, array{string, list<string>}>
   *   Bundle and the display values expected on its inner wrapper.
   */
  public static function bundleFieldProvider(): array {
    $default = [
      'value-pb_display_width',
      'value-pb_display_border',
      'value-pb_display_border_radius',
      'value-pb_display_margin',
      'value-pb_display_padding',
      'value-pb_display_shadow',
    ];
    return [
      'default list' => ['tabs_bundle', $default],
      'another bundle on the default list' => ['simple_bundle', $default],
      // Margin belongs to `.hero-bundle-inner`; the overlay adds its position.
      'hero drops margin, adds position' => [
        'hero_bundle',
        [
          'value-pb_display_width',
          'value-pb_display_border',
          'value-pb_display_border_radius',
          'value-pb_display_padding',
          'value-pb_display_shadow',
          'value-pb_display_hero',
        ],
      ],
      'image background takes width and padding only' => [
        'image_background_bundle',
        ['value-pb_display_width', 'value-pb_display_padding'],
      ],
      'image overlay drops padding' => [
        'image_overlay_bundle',
        [
          'value-pb_display_width',
          'value-pb_display_border',
          'value-pb_display_border_radius',
          'value-pb_display_margin',
          'value-pb_display_shadow',
        ],
      ],
    ];
  }

  /**
   * Fields the bundle does not have, and empty ones, contribute nothing.
   */
  public function testMissingAndEmptyFieldsAreSkipped(): void {
    $paragraph = $this->createMock(FieldableEntityInterface::class);
    $paragraph->method('bundle')->willReturn('tabs_bundle');
    $paragraph->method('hasField')->willReturnCallback(
      static fn (string $field): bool => $field !== 'pb_display_border',
    );
    $paragraph->method('get')->willReturnCallback(
      function (string $field): FieldItemListInterface {
        // Padding exists but holds no value.
        return $field === 'pb_display_padding'
          ? $this->fieldItemList(NULL)
          : $this->fieldItemList('value-' . $field);
      },
    );

    $resolver = new PbDisplayClassResolver();
    $this->assertSame(
      [
        'value-pb_display_width',
        'value-pb_display_border_radius',
        'value-pb_display_margin',
        'value-pb_display_shadow',
      ],
      $resolver->innerClasses($paragraph),
    );
  }

  /**
   * Builds a paragraph whose every display field holds `value-{field}`.
   *
   * @param string $bundle
   *   Paragraph bundle.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   Mock paragraph.
   */
  private function paragraphWithAllDisplayFields(string $bundle): FieldableEntityInterface {
    $paragraph = $this->createMock(FieldableEntityInterface::class);
    $paragraph->method('bundle')->willReturn($bundle);
    $paragraph->method('hasField')->willReturn(TRUE);
    $paragraph->method('get')->willReturnCallback(
      fn (string $field): FieldItemListInterface => $this->fieldItemList('value-' . $field),
    );
    return $paragraph;
  }

  /**
   * Builds a field item list carrying one scalar value.
   *
   * @param string|null $value
   *   Field value, or NULL for an empty list.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface
   *   Mock field item list.
   */
  private function fieldItemList(?string $value): FieldItemListInterface {
    $items = $this->createMock(FieldItemListInterface::class);
    $items->method('isEmpty')->willReturn($value === NULL);
    $items->method('__get')->with('value')->willReturn($value);
    return $items;
  }

}
