<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit\Service\Css;

use Drupal\paragraphs_bundles\Service\Css\PbColorNormalizer;
use Drupal\paragraphs_bundles\Service\Css\PbDynamicUserVarBuilder;
use Drupal\paragraphs_bundles\Service\Field\PbImageFieldUrlResolverInterface;
use Drupal\Tests\paragraphs_bundles\Unit\PbParagraphMockTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PbDynamicUserVarBuilder.
 */
#[CoversClass(PbDynamicUserVarBuilder::class)]
#[Group('paragraphs_bundles')]
final class PbDynamicUserVarBuilderTest extends TestCase {

  use PbParagraphMockTrait;

  /**
   * Builder under test.
   */
  private PbDynamicUserVarBuilder $builder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $images = $this->createMock(PbImageFieldUrlResolverInterface::class);
    $this->builder = new PbDynamicUserVarBuilder(new PbColorNormalizer(), $images);
  }

  /**
   * A paragraph with no display fields emits no CSS.
   */
  public function testEmptyFieldsEmitNoCss(): void {
    $paragraph = $this->mockParagraph('grid_bundle', 42);
    $css = $this->builder->build($paragraph, '#pb__grid-42');
    $this->assertSame('', $css);
  }

  /**
   * A bundle without a var recipe emits no CSS.
   */
  public function testUnknownBundleEmitsNoCss(): void {
    $paragraph = $this->mockParagraph('simple_bundle', 1, [
      'pb_display_grid_gap' => '24',
    ]);
    $css = $this->builder->build($paragraph, '#pb__simple-1');
    $this->assertSame('', $css);
  }

  /**
   * Grid gap is emitted on the grid wrapper selector.
   */
  public function testGridGapOnCorrectSelector(): void {
    $paragraph = $this->mockParagraph('grid_bundle', 42, [
      'pb_display_grid_gap' => '24',
    ]);
    $css = $this->builder->build($paragraph, '#pb__grid-42');
    $this->assertStringContainsString(
      '#pb__grid-42 .pb-grid__wrapper',
      $css,
    );
    $this->assertStringContainsString('gap: 24px;', $css);
  }

  /**
   * Integer and float field values emit, they are not dropped.
   *
   * Every rule reaching `px` / `px_gt_zero` / `ms` / `rem` / `seconds` reads an
   * integer or decimal storage. Such a field holds a real `int` on an entity
   * populated in memory — which is precisely what `hook_paragraph_insert()`
   * builds the stylesheet from — and only becomes a numeric string once it has
   * been through the database. While this method gated on `is_string()`, any
   * paragraph created by a recipe, migration or demo script got a stylesheet
   * with those declarations missing, and kept it until someone re-saved the
   * paragraph through the form.
   *
   * Every other test in this class passes strings, which is why none of them
   * could catch it.
   */
  public function testNumericFieldValuesAreCoercedNotDropped(): void {
    $paragraph = $this->mockParagraph('grid_bundle', 42, [
      'pb_display_grid_gap' => 24,
    ]);
    $css = $this->builder->build($paragraph, '#pb__grid-42');
    $this->assertStringContainsString('gap: 24px;', $css);

    $parallax = $this->mockParagraph('image_background_bundle', 9, [
      'pb_display_bg' => '#fafdff',
      'pb_display_bg_opacity' => 36,
    ]);
    $this->assertStringContainsString(
      'background-color: rgba(250, 253, 255, 0.36);',
      $this->builder->build($parallax, '#pb__image-bg-9'),
    );
  }

  /**
   * A boolean has no CSS spelling and still emits nothing.
   *
   * Coercing numbers must not turn into coercing everything — TRUE would
   * become "1", a value that means nothing as a length. Same fail-closed rule
   * as the colour path and as PbTwigExtension::cssValue().
   */
  public function testBooleanFieldValueEmitsNothing(): void {
    $paragraph = $this->mockParagraph('grid_bundle', 42, [
      'pb_display_grid_gap' => TRUE,
    ]);
    $this->assertSame('', $this->builder->build($paragraph, '#pb__grid-42'));
  }

  /**
   * A zero carousel gap is omitted while slide width still emits.
   */
  public function testCarouselGapZeroIsOmitted(): void {
    $paragraph = $this->mockParagraph('carousel_bundle', 7, [
      'pb_display_slide_gap' => '0',
      'pb_display_custom_slide_width' => '320',
    ]);
    $css = $this->builder->build($paragraph, '#pb__carousel-7');
    $this->assertStringNotContainsString('.pb-carousel__slides', $css);
    $this->assertStringContainsString('max-width: 320px;', $css);
  }

  /**
   * Image background overlay emits an rgba() color on the overlay selector.
   */
  public function testImageBackgroundOverlayRgba(): void {
    $paragraph = $this->mockParagraph('image_background_bundle', 9, [
      'pb_display_bg' => '#fafdff',
      'pb_display_bg_opacity' => '36',
    ]);
    $css = $this->builder->build($paragraph, '#pb__image-bg-9');
    $this->assertStringContainsString(
      '#pb__image-bg-9 .img-color-overlay',
      $css,
    );
    $this->assertStringContainsString(
      'background-color: rgba(250, 253, 255, 0.36);',
      $css,
    );
  }

  /**
   * Two-column layout emits animation and sticky-top custom properties.
   */
  public function testLayoutColumnVars(): void {
    $paragraph = $this->mockParagraph('two_columns_bundle', 3, [
      'pb_display_2c_anim_ms' => '500',
      'pb_display_2c_stk_top' => '80',
    ]);
    $css = $this->builder->build($paragraph, '#pb__two-columns-3');
    $this->assertStringContainsString('.pb-two-columns', $css);
    $this->assertStringContainsString('--pb-col-anim-ms: 500ms;', $css);
    $this->assertStringContainsString('--pb-col-sticky-top: 80px;', $css);
  }

  /**
   * A malicious field value cannot break out of its CSS declaration.
   *
   * Defence in depth: even a value that reached storage past field validation
   * must not inject rules or markup into the generated stylesheet.
   */
  public function testMaliciousValueCannotBreakOutOfDeclaration(): void {
    $paragraph = $this->mockParagraph('grid_bundle', 99, [
      'pb_display_grid_gap' => '1} body{display:none} /*<script>*/',
    ]);
    $css = $this->builder->build($paragraph, '#pb__grid-99');

    // Only the single rule block's own braces may appear — the value's braces
    // are stripped, so no injected rule survives.
    $this->assertSame(1, substr_count($css, '{'));
    $this->assertSame(1, substr_count($css, '}'));
    $this->assertStringNotContainsString('body{', $css);
    $this->assertStringNotContainsString('display:none}', $css);
    $this->assertStringNotContainsString('<', $css);
    $this->assertStringNotContainsString('>', $css);
  }

  /**
   * The 3D carousel perspective is emitted on the inner selector.
   */
  public function test3dCarouselPerspectiveOnInner(): void {
    $paragraph = $this->mockParagraph('3d_carousel_bundle', 12, [
      'pb_display_3d_car_perspective' => '900',
    ]);
    $css = $this->builder->build($paragraph, '#pb-3d-carousel-12');
    $this->assertStringContainsString(
      '#pb-3d-carousel-12 > .pb-3d-carousel__inner',
      $css,
    );
    $this->assertStringContainsString('perspective: 900px;', $css);
  }

}
