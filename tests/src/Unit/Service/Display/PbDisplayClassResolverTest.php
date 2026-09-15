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
    $paragraph = $this->createStub(FieldableEntityInterface::class);
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
   * Author-supplied classes: what is accepted, and what is refused.
   *
   * The value reaches a class attribute, so this is the security boundary as
   * well as the usability one. Tailwind utilities must survive intact - they
   * are why the allowlist is not `[A-Za-z0-9_-]` - while anything that could
   * end the attribute or open a tag must not.
   *
   * @param string $stored
   *   Raw field value as an author typed it.
   * @param list<string> $expected
   *   Classes expected on the wrapper.
   */
  #[DataProvider('customClassProvider')]
  public function testCustomClasses(string $stored, array $expected): void {
    $resolver = new PbDisplayClassResolver();
    $this->assertSame($expected, $resolver->customClasses($this->paragraphWithCustomClasses($stored)));
  }

  /**
   * Values an author might store, and what must come back.
   *
   * @return array<string, array{string, list<string>}>
   *   Stored value and expected classes.
   */
  public static function customClassProvider(): array {
    return [
      // The feature's whole purpose: one shared class, styled once.
      'a single class' => ['my-brand-box', ['my-brand-box']],
      'several classes' => ['a b c', ['a', 'b', 'c']],
      'camel case survives' => ['myBrandBox', ['myBrandBox']],

      // Tailwind. Html::getClass() would destroy every one of these, which is
      // why this resolver rejects rather than rewrites.
      'tailwind responsive' => ['md:w-full', ['md:w-full']],
      'tailwind fraction' => ['top-1/2', ['top-1/2']],
      'tailwind arbitrary hex' => ['bg-[#1da1f2]', ['bg-[#1da1f2]']],
      'tailwind arbitrary calc' => ['w-[calc(100%-2rem)]', ['w-[calc(100%-2rem)]']],
      'tailwind important' => ['!font-bold', ['!font-bold']],
      'tailwind stacked variants' => ['lg:hover:bg-blue-700', ['lg:hover:bg-blue-700']],
      'container query' => ['@container', ['@container']],

      // Whitespace handling.
      'runs of spaces collapse' => ['a   b', ['a', 'b']],
      'leading and trailing space' => ['  a  ', ['a']],
      'newlines and tabs split' => ["a\nb\tc", ['a', 'b', 'c']],
      'empty string' => ['', []],
      'whitespace only' => ["   \n ", []],

      // Refusals. Each of these is what an attribute injection needs.
      'angle brackets refused' => ['a<b', []],
      'closing angle refused' => ['div>p', []],
      'double quote refused' => ['a"b', []],
      'single quote refused' => ["a'b", []],
      'ampersand refused' => ['a&b', []],
      'equals refused' => ['onclick=x', []],
      'backtick refused' => ['a`b', []],
      'backslash refused' => ['a\\b', []],
      'braces refused' => ['a{b}', []],
      'semicolon refused' => ['a;b', []],
      // Tailwind's arbitrary VARIANT syntax carries & and >, and is refused
      // on purpose - documented on the pattern constant.
      'arbitrary variant refused' => ['[&>*]:mt-0', []],

      // A bad token does not take its neighbours with it.
      'good tokens survive a bad one' => ['ok-one a<b ok-two', ['ok-one', 'ok-two']],

      // Housekeeping.
      'duplicates collapse' => ['dup dup other', ['dup', 'other']],
      'reserved base classes are dropped' => [
        'paragraph paragraphs-bundles mine',
        ['mine'],
      ],
    ];
  }

  /**
   * The rejected bucket names every token that will not be emitted.
   *
   * The validator turns this list into the message an author reads, so an
   * empty or partial bucket is a silent failure wearing a warning's clothes.
   */
  public function testFilterReportsRejectedTokens(): void {
    $resolver = new PbDisplayClassResolver();
    $out = $resolver->filterClassTokens('ok-one a<b bad"quote md:w-full on=x ok-two');

    $this->assertSame(['ok-one', 'md:w-full', 'ok-two'], $out['accepted']);
    $this->assertSame(['a<b', 'bad"quote', 'on=x'], $out['rejected']);
    $this->assertSame([], $out['over_cap']);
  }

  /**
   * Valid tokens past the cap are reported separately, not as "invalid".
   */
  public function testFilterSeparatesOverCapFromRejected(): void {
    $resolver = new PbDisplayClassResolver();
    $many = array_map(static fn (int $i): string => 'c' . $i, range(1, 40));
    $out = $resolver->filterClassTokens(implode(' ', $many) . ' bad<token');

    $this->assertCount(32, $out['accepted']);
    $this->assertSame(['bad<token'], $out['rejected']);
    // c33..c40 are perfectly valid; they just did not fit.
    $this->assertSame(['c33', 'c34', 'c35', 'c36', 'c37', 'c38', 'c39', 'c40'], $out['over_cap']);
  }

  /**
   * An empty or blank value produces three empty buckets, never a violation.
   */
  public function testFilterOnEmptyValue(): void {
    $resolver = new PbDisplayClassResolver();
    foreach (['', '   ', "\n\t"] as $value) {
      $this->assertSame(
        ['accepted' => [], 'rejected' => [], 'over_cap' => []],
        $resolver->filterClassTokens($value),
      );
    }
  }

  /**
   * The cap holds when an author pastes far too many classes.
   */
  public function testCustomClassesAreCapped(): void {
    $stored = implode(' ', array_map(static fn (int $i): string => 'c' . $i, range(1, 100)));
    $resolver = new PbDisplayClassResolver();
    $classes = $resolver->customClasses($this->paragraphWithCustomClasses($stored));
    $this->assertCount(32, $classes);
    $this->assertSame('c1', $classes[0]);
  }

  /**
   * A bundle without the field contributes nothing, and does not error.
   */
  public function testCustomClassesWithoutTheField(): void {
    $paragraph = $this->createMock(FieldableEntityInterface::class);
    $paragraph->method('bundle')->willReturn('accordion_section_bundle');
    $paragraph->method('hasField')->willReturn(FALSE);
    $paragraph->expects($this->never())->method('get');

    $resolver = new PbDisplayClassResolver();
    $this->assertSame([], $resolver->customClasses($paragraph));
  }

  /**
   * Builds a paragraph carrying one `pb_display_css_classes` value.
   *
   * @param string $stored
   *   Raw stored value.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   Mock paragraph.
   */
  private function paragraphWithCustomClasses(string $stored): FieldableEntityInterface {
    $paragraph = $this->createStub(FieldableEntityInterface::class);
    $paragraph->method('bundle')->willReturn('simple_bundle');
    $paragraph->method('hasField')->willReturn(TRUE);
    $paragraph->method('get')->willReturnCallback(
      fn (string $field): FieldItemListInterface => $this->fieldItemList(
        $stored === '' ? NULL : $stored,
      ),
    );
    return $paragraph;
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
    $paragraph = $this->createStub(FieldableEntityInterface::class);
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
    $items = $this->createStub(FieldItemListInterface::class);
    $items->method('isEmpty')->willReturn($value === NULL);
    // with() has no effect on a stub and PHPUnit 13 forbids it; a callback
    // keeps the same intent - only `value` yields the stored string.
    $items->method('__get')->willReturnCallback(
      static fn (string $name): ?string => $name === 'value' ? $value : NULL,
    );
    return $items;
  }

}
