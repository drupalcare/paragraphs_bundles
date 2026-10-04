<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit\Twig;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\paragraphs_bundles\Service\Css\PbColorNormalizer;
use Drupal\paragraphs_bundles\Service\Display\PbDisplayClassResolver;
use Drupal\paragraphs_bundles\Service\Field\PbImageFieldUrlResolverInterface;
use Drupal\paragraphs_bundles\Twig\PbTwigExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests PbTwigExtension filters.
 */
#[CoversClass(PbTwigExtension::class)]
#[Group('paragraphs_bundles')]
final class PbTwigExtensionTest extends TestCase {

  /**
   * The extension under test.
   */
  private PbTwigExtension $extension;

  /**
   * Image field URL resolver stub.
   */
  private PbImageFieldUrlResolverInterface $imageFieldUrls;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->imageFieldUrls = $this->createStub(PbImageFieldUrlResolverInterface::class);
    // The normalizer and class resolver are the real thing, not mocks:
    // `pb_css_value` and `pb_class` exist to apply shared logic, so stubbing
    // them would assert only that a delegation happened and prove nothing
    // about what reaches the style or class attribute.
    $this->extension = new PbTwigExtension(
      $this->imageFieldUrls,
      new PbColorNormalizer(),
      new PbDisplayClassResolver(),
    );
  }

  /**
   * Non-entity input returns the default string.
   */
  public function testFieldValueNonEntity(): void {
    $this->assertSame(
      'fallback',
      $this->extension->fieldValue(NULL, 'pb_display_bg', 'fallback')
    );
  }

  /**
   * Nested array field values are unwrapped to scalars.
   */
  public function testFieldValueNestedArray(): void {
    $entity = $this->entityWithValue(['value' => '800']);
    $this->assertSame(
      '800',
      $this->extension->fieldValue($entity, 'pb_display_3d_car_perspective')
    );
  }

  /**
   * Scalar strings pass through unchanged.
   */
  public function testFieldValueScalarString(): void {
    $entity = $this->entityWithValue('wide');
    $this->assertSame(
      'wide',
      $this->extension->fieldValue($entity, 'pb_display_width')
    );
  }

  /**
   * Boolean TRUE becomes "1".
   */
  public function testFieldValueBooleanTrue(): void {
    $entity = $this->entityWithValue(TRUE);
    $this->assertSame(
      '1',
      $this->extension->fieldValue($entity, 'pb_display_2c_l_stk')
    );
  }

  /**
   * Decode_entities ignores non-string input.
   */
  public function testDecodeEntitiesNonString(): void {
    $this->assertSame('', $this->extension->decodeEntities(['x']));
  }

  /**
   * Image field URL delegates to the resolver service.
   */
  public function testImageFieldUrl(): void {
    $entity = $this->createStub(FieldableEntityInterface::class);
    // This is the one test that sets an expectation, so it needs a real mock;
    // the setUp() resolver is a stub for every test that only needs a double.
    $resolver = $this->createMock(PbImageFieldUrlResolverInterface::class);
    $resolver->expects($this->once())
      ->method('absoluteUrl')
      ->with($entity, 'pb_display_background_image')
      ->willReturn('https://example.test/bg.jpg');
    $extension = new PbTwigExtension(
      $resolver,
      new PbColorNormalizer(),
      new PbDisplayClassResolver(),
    );
    $this->assertSame(
      'https://example.test/bg.jpg',
      $extension->imageFieldUrl($entity, 'pb_display_background_image'),
    );
  }

  /**
   * Strips from `pb_css_value` every character that can escape a style attr.
   *
   * Twig's HTML autoescape does not help inside `style="..."`: `(`, `)`, `;`
   * and `:` pass straight through. This filter is what templates rely on when
   * interpolating a field value there.
   *
   * @param string $value
   *   Raw value as it would arrive from a field.
   * @param string $expected
   *   Sanitized result.
   */
  #[DataProvider('cssValueProvider')]
  public function testCssValueSanitizes(string $value, string $expected): void {
    $this->assertSame($expected, $this->extension->cssValue($value));
  }

  /**
   * Style-attribute payloads and the sanitized output each must produce.
   *
   * @return array<string, list<string>>
   *   Test case name => [raw value, expected output].
   */
  public static function cssValueProvider(): array {
    return [
      'legitimate url passes through' => [
        'url("/sites/default/files/bg.jpg")',
        'url("/sites/default/files/bg.jpg")',
      ],
      'declaration terminator stripped' => [
        'red; background: url(javascript:alert(1))',
        'red background: url(javascript:alert(1))',
      ],
      'rule braces stripped' => [
        '0} body{display:none',
        '0 bodydisplay:none',
      ],
      'markup delimiters stripped' => [
        '</style><script>alert(1)</script>',
        '/stylescriptalert(1)/script',
      ],
      'comment delimiters stripped' => [
        '10px /* x */',
        '10px  x ',
      ],
      'newlines stripped' => [
        "10px\n  color: red",
        '10px  color: red',
      ],
    ];
  }

  /**
   * Yields an empty string from `pb_css_value` for non-scalar input.
   */
  public function testCssValueRejectsNonScalar(): void {
    $this->assertSame('', $this->extension->cssValue(NULL));
    $this->assertSame('', $this->extension->cssValue(TRUE));
    $this->assertSame('', $this->extension->cssValue(['x']));
  }

  /**
   * Rejects an object with no __toString() instead of fatalling on the cast.
   */
  public function testCssValueRejectsNonStringableObject(): void {
    $this->assertSame('', $this->extension->cssValue(new \stdClass()));
  }

  /**
   * Sanitizes a Stringable, which is how render output reaches a template.
   */
  public function testCssValueSanitizesStringable(): void {
    $markup = new class() implements \Stringable {

      /**
       * {@inheritdoc}
       */
      public function __toString(): string {
        return 'red; } html {';
      }

    };
    $this->assertSame('red  html ', $this->extension->cssValue($markup));
  }

  /**
   * Coerces numeric input, which Twig yields for unquoted numbers.
   */
  public function testCssValueAcceptsNumbers(): void {
    $this->assertSame('10', $this->extension->cssValue(10));
    $this->assertSame('0.5', $this->extension->cssValue(0.5));
  }

  /**
   * Builds a fieldable entity mock with one field value.
   *
   * @param mixed $value
   *   Field list value property.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   Entity mock.
   */
  private function entityWithValue(mixed $value): FieldableEntityInterface {
    // createMock() implements every interface method; onlyMethods() on an
    // all-abstract interface leaves the other ~52 unimplemented and fatals
    // under PHPUnit 11.5 / PHP 8.4. The service reads $items->value through the
    // interface's magic __get(), so stub that instead of a real property (which
    // the stubbed __set() would swallow).
    $items = $this->createStub(FieldItemListInterface::class);
    $items->method('isEmpty')->willReturn(FALSE);
    $items->method('__get')->willReturnCallback(
      static fn (string $name): mixed => $name === 'value' ? $value : NULL,
    );

    $entity = $this->createStub(FieldableEntityInterface::class);
    $entity->method('hasField')->willReturn(TRUE);
    $entity->method('get')->willReturn($items);
    return $entity;
  }

}
