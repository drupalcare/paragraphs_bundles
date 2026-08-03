<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit\Utility;

use Drupal\Core\Template\Attribute;
use Drupal\paragraphs_bundles\Utility\PbRenderArray;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests the render-array append helpers.
 */
#[CoversClass(PbRenderArray::class)]
final class PbRenderArrayTest extends TestCase {

  /**
   * A missing list is created rather than fataling.
   */
  public function testAppendCreatesTheList(): void {
    $build = [];
    PbRenderArray::attachLibraries($build, 'pb/one');

    $this->assertSame(['#attached' => ['library' => ['pb/one']]], $build);
  }

  /**
   * Existing libraries are kept and appended to, in order.
   */
  public function testAppendPreservesExistingValues(): void {
    $build = ['#attached' => ['library' => ['other/first']]];
    PbRenderArray::attachLibraries($build, 'pb/two', 'pb/three');

    $this->assertSame(
      ['other/first', 'pb/two', 'pb/three'],
      $build['#attached']['library'],
    );
  }

  /**
   * Sibling keys under the same parent survive the append.
   */
  public function testAppendKeepsSiblingKeys(): void {
    $build = [
      '#attached' => [
        'drupalSettings' => ['pb' => ['x' => 1]],
        'library' => ['other/first'],
      ],
    ];
    PbRenderArray::attachLibraries($build, 'pb/two');

    $this->assertSame(['pb' => ['x' => 1]], $build['#attached']['drupalSettings']);
    $this->assertSame(['other/first', 'pb/two'], $build['#attached']['library']);
  }

  /**
   * A scalar sitting where the list belongs is replaced, not appended to.
   *
   * This is the case the helper exists for: `$build['#attached']['library'][]`
   * on a string `#attached` is a fatal, and no render array can use the
   * scalar anyway.
   */
  public function testAppendReplacesNonArrayIntermediate(): void {
    $build = ['#attached' => 'not-an-array'];
    PbRenderArray::attachLibraries($build, 'pb/one');

    $this->assertSame(['#attached' => ['library' => ['pb/one']]], $build);
  }

  /**
   * A scalar sitting at the list itself is replaced too.
   */
  public function testAppendReplacesNonArrayList(): void {
    $build = ['#attached' => ['library' => 'pb/legacy']];
    PbRenderArray::attachLibraries($build, 'pb/one');

    $this->assertSame(['pb/one'], $build['#attached']['library']);
  }

  /**
   * Appending nothing leaves the build untouched.
   */
  public function testAppendWithNoValuesDoesNothing(): void {
    $build = ['#attached' => ['library' => ['other/first']]];
    PbRenderArray::attachLibraries($build);

    $this->assertSame(['#attached' => ['library' => ['other/first']]], $build);
  }

  /**
   * Nested non-string values (html_head entries) append unchanged.
   */
  public function testAppendAcceptsArrayValues(): void {
    $build = [];
    $head = [['#type' => 'html_tag', '#tag' => 'style'], 'pb-dynamic-css-1'];
    PbRenderArray::append($build, ['#attached', 'html_head'], $head);

    $this->assertSame([$head], $build['#attached']['html_head']);
  }

  /**
   * Classes append to a plain attributes array.
   */
  public function testAddClassOnArrayAttributes(): void {
    $variables = ['attributes' => ['class' => ['existing']]];
    PbRenderArray::addClass($variables, 'pb-one', 'pb-two');

    $this->assertSame(['existing', 'pb-one', 'pb-two'], $variables['attributes']['class']);
  }

  /**
   * An Attribute object is added to, not flattened into an array.
   */
  public function testAddClassOnAttributeObject(): void {
    $variables = ['attributes' => new Attribute(['class' => ['existing']])];
    PbRenderArray::addClass($variables, 'pb-one');

    $this->assertInstanceOf(Attribute::class, $variables['attributes']);
    $this->assertSame(
      ['existing', 'pb-one'],
      $variables['attributes']->offsetGet('class')->value(),
    );
  }

  /**
   * Absent attributes are created.
   */
  public function testAddClassWithoutExistingAttributes(): void {
    $variables = [];
    PbRenderArray::addClass($variables, 'pb-one');

    $this->assertSame(['attributes' => ['class' => ['pb-one']]], $variables);
  }

}
