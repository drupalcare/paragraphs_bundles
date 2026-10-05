<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit\Service\Css;

use Drupal\paragraphs_bundles\Service\Css\PbColorNormalizer;
use Drupal\paragraphs_bundles\Service\Css\PbDynamicUserVarBuilder;
use Drupal\paragraphs_bundles\Service\Field\PbImageFieldUrlResolverInterface;
use Drupal\Tests\paragraphs_bundles\Unit\PbParagraphMockTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    $images = $this->createStub(PbImageFieldUrlResolverInterface::class);
    $this->builder = new PbDynamicUserVarBuilder(new PbColorNormalizer(), $images);
  }

  /**
   * A paragraph with no display fields emits no CSS.
   */
  public function testEmptyFieldsEmitNoCss(): void {
    $paragraph = $this->mockParagraph('grid_bundle', 42);
    $css = $this->builder->build($paragraph, '#pb-grid-42');
    $this->assertSame('', $css);
  }

  /**
   * A bundle without a var recipe emits no CSS.
   */
  public function testUnknownBundleEmitsNoCss(): void {
    $paragraph = $this->mockParagraph('simple_bundle', 1, [
      'pb_display_grid_gap' => '24',
    ]);
    $css = $this->builder->build($paragraph, '#pb-simple-1');
    $this->assertSame('', $css);
  }

  /**
   * Grid gap is emitted on the grid wrapper selector.
   */
  public function testGridGapOnCorrectSelector(): void {
    $paragraph = $this->mockParagraph('grid_bundle', 42, [
      'pb_display_grid_gap' => '24',
    ]);
    $css = $this->builder->build($paragraph, '#pb-grid-42');
    $this->assertStringContainsString(
      '#pb-grid-42 .pb-grid__wrapper',
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
    $css = $this->builder->build($paragraph, '#pb-grid-42');
    $this->assertStringContainsString('gap: 24px;', $css);

    $parallax = $this->mockParagraph('image_background_bundle', 9, [
      'pb_display_bg' => '#fafdff',
      'pb_display_bg_opacity' => 36,
    ]);
    $this->assertStringContainsString(
      'background-color: rgba(250, 253, 255, 0.36);',
      $this->builder->build($parallax, '#pb-image-background-9'),
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
    $this->assertSame('', $this->builder->build($paragraph, '#pb-grid-42'));
  }

  /**
   * A zero carousel gap is omitted while slide width still emits.
   */
  public function testCarouselGapZeroIsOmitted(): void {
    $paragraph = $this->mockParagraph('carousel_bundle', 7, [
      'pb_display_slide_gap' => '0',
      'pb_display_custom_slide_width' => '320',
    ]);
    $css = $this->builder->build($paragraph, '#pb-carousel-7');
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
    $css = $this->builder->build($paragraph, '#pb-image-background-9');
    $this->assertStringContainsString(
      '#pb-image-background-9 .img-color-overlay',
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
    $css = $this->builder->build($paragraph, '#pb-two-columns-3');
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
    $css = $this->builder->build($paragraph, '#pb-grid-99');

    // ⚠ THIS EXPECTATION GOT STRONGER, 2026-09-24, AND THE OLD ONE IS WHY.
    // It used to assert `substr_count($css, '{') === 1`: the hostile value was
    // EMITTED and merely sanitised, so exactly one rule block survived with the
    // braces stripped out of the value. `gap` is a `px` transform, and
    // ::withUnit() now rejects anything that is not a length, so the
    // declaration is never produced, the block has nothing to hold, and the
    // count is 0. Zero rule blocks is strictly safer than one scrubbed one.
    //
    // 📐 The test changed; the behaviour it guards did not get weaker. Assert
    // the strongest available property — nothing at all is emitted — because
    // this paragraph sets no other display field.
    $this->assertSame('', $css);

    // Kept from the sanitisation-only era deliberately. They are trivially
    // true while the value is rejected upstream, and they are what goes red
    // first if that guard is ever removed and emission resumes.
    $this->assertStringNotContainsString('body{', $css);
    $this->assertStringNotContainsString('display:none', $css);
    $this->assertStringNotContainsString('<', $css);
    $this->assertStringNotContainsString('>', $css);
    $this->assertSame(substr_count($css, '{'), substr_count($css, '}'));
  }

  /**
   * The sanitizer still guards transforms whose values are not numbers.
   *
   * 🚨 WITHOUT THIS, REJECTING BAD LENGTHS WOULD SILENTLY RETIRE THE SANITIZER
   * FROM THE SUITE. ::testMaliciousValueCannotBreakOutOfDeclaration() was the
   * only exercise of ::sanitizeCssValue() on a real build, and it now stops at
   * the length guard — a value that is numeric can never carry a brace, so no
   * remaining length case can reach the sanitizer at all.
   *
   * `background-attachment` uses the `string` transform, which has no shape to
   * check against and therefore still relies entirely on sanitisation. That is
   * the path this pins.
   */
  public function testSanitizerStillGuardsNonNumericTransforms(): void {
    $paragraph = $this->mockParagraph('image_background_bundle', 77, [
      'pb_display_background_attachment' => 'fixed} body{display:none} /*<script>*/',
    ]);
    $css = $this->builder->build($paragraph, '#pb-image-background-77');

    $this->assertStringContainsString('background-attachment:', $css);
    $this->assertSame(1, substr_count($css, '{'));
    $this->assertSame(1, substr_count($css, '}'));
    $this->assertStringNotContainsString('body{', $css);
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

  /**
   * A length is emitted only when the stored value really is a length.
   *
   * 🚨 THE DEFECT THIS PINS, MEASURED ON a test site 2026-09-24. Paragraphs 335
   * and 416 held the literal text `PB fixture pb_display_3d_car_perspective`
   * in `pb_display_3d_car_perspective`, and it reached a served stylesheet as
   * `perspective: PB fixture pb_display_3d_car_perspectivepx;`. Browsers drop
   * the declaration — confirmed in a real CSS engine, where it parses to an
   * empty rule — so nothing rendered wrong and nothing could escape the block.
   * Emitting it was still wrong, and the field is a plain `varchar(255)`: the
   * only length field in the module that is not an integer storage, which is
   * exactly how prose got in.
   *
   * ⚠ The `900px` case is a SEPARATE latent bug this guard also closes. The
   * field's own description invites it — "modify this value in pixels" — and
   * ::withUnit() used to append a second unit, emitting `900pxpx`.
   *
   * @param string $stored
   *   The value held in the perspective field.
   * @param string $expected
   *   The declaration that must appear, or '' when nothing may be emitted.
   * @param string $why
   *   What the case is about, shown on failure.
   */
  #[DataProvider('lengthGuardProvider')]
  public function testLengthValuesAreShapeCheckedBeforeEmission(
    string $stored,
    string $expected,
    string $why,
  ): void {
    $paragraph = $this->mockParagraph('3d_carousel_bundle', 31, [
      'pb_display_3d_car_perspective' => $stored,
    ]);
    $css = $this->builder->build($paragraph, '#pb-3d-carousel-31');

    if ($expected === '') {
      $this->assertSame('', $css, $why);
      return;
    }
    $this->assertStringContainsString($expected, $css, $why);
  }

  /**
   * Values an editor or an API write can put in a length field.
   *
   * @return array<string, array{string, string, string}>
   *   Case name => [stored value, expected declaration, failure message].
   */
  public static function lengthGuardProvider(): array {
    return [
      'bare number' => [
        '900', 'perspective: 900px;',
        'The ordinary case must still be emitted.',
      ],
      'decimal' => [
        '12.5', 'perspective: 12.5px;',
        'A fractional length is valid CSS and must survive.',
      ],
      'unit typed by hand' => [
        '900px', 'perspective: 900px;',
        'The field description invites "px"; it must not become 900pxpx.',
      ],
      'unit with a space' => [
        '900 px', 'perspective: 900px;',
        'Spacing before the unit must normalise, not reject.',
      ],
      'the fixture prose found on a test site' => [
        'PB fixture pb_display_3d_car_perspective', '',
        'Prose in a length field must emit no declaration at all.',
      ],
      'wrong unit for this transform' => [
        '900rem', '',
        'A unit the transform does not own is not silently re-suffixed.',
      ],
      'empty' => [
        '', '',
        'An unset field emits nothing, as it always has.',
      ],
    ];
  }

  /**
   * The flip box scene height is emitted on the scene selector.
   *
   * MOVED OUT OF THE TEMPLATE FOR CSP, 2026-09-20. This was the bundle's last
   * inline `style="height: Npx"`, and a style ATTRIBUTE cannot be permitted by
   * a Content Security Policy except through `'unsafe-inline'`: unlike a
   * `<style>` element it cannot carry a nonce. PbInlineAssetSurfaceTest pins
   * the attribute's absence; this pins that the value still arrives, which is
   * the half that would otherwise fail silently — a scene with no height looks
   * like a styling bug, not a missing rule.
   */
  public function testFlipBoxHeightOnScene(): void {
    $paragraph = $this->mockParagraph('3d_flip_box_bundle', 309, [
      'pb_display_3d_height' => '350',
    ]);
    $css = $this->builder->build($paragraph, '#pb-3d-flip-box-309');
    $this->assertStringContainsString(
      '#pb-3d-flip-box-309 .pb-3d-flip-box__scene',
      $css,
    );
    $this->assertStringContainsString('height: 350px;', $css);
  }

  /**
   * An unset flip box height emits nothing rather than `height: px`.
   *
   * The template guarded this with `{% if pb_box_height %}`. That guard was not
   * lost when the value moved: build() skips any rule whose resolved value is
   * empty. Without this test the relocation of a guard is invisible.
   */
  public function testFlipBoxWithoutHeightEmitsNoCss(): void {
    $paragraph = $this->mockParagraph('3d_flip_box_bundle', 310);
    $this->assertSame(
      '',
      $this->builder->build($paragraph, '#pb-3d-flip-box-310'),
    );
  }

  /**
   * Parallax section height lands on the element the CSS reads it from.
   *
   * `.pb-parallax-section > div` declares
   * `min-height: var(--section-height, 400px)`. THE FALLBACK IS THE HAZARD:
   * every stored height on a test site is 400, so a rule that silently emitted
   * nothing would still render a 400px section and look perfectly correct.
   * Asserting the declaration is the only honest check.
   */
  public function testParallaxSectionHeightOnInner(): void {
    $paragraph = $this->mockParagraph('parallax_section_bundle', 294, [
      'pb_display_parallax_height' => '400',
      'pb_display_para_height_unit' => 'px',
    ]);
    $css = $this->builder->build($paragraph, '#pb-parallax-section-294');
    $this->assertStringContainsString(
      '#pb-parallax-section-294 > .pb-parallax-section__inner',
      $css,
    );
    $this->assertStringContainsString('--section-height: 400px;', $css);
  }

  /**
   * The `pr` unit is written as a percentage, not the stored token.
   */
  public function testParallaxSectionHeightPercentUnit(): void {
    $paragraph = $this->mockParagraph('parallax_section_bundle', 295, [
      'pb_display_parallax_height' => '80',
      'pb_display_para_height_unit' => 'pr',
    ]);
    $this->assertStringContainsString(
      '--section-height: 80%;',
      $this->builder->build($paragraph, '#pb-parallax-section-295'),
    );
  }

  /**
   * 🚨 AN UNSET SCROLL FIELD MEANS LEGACY, so no animation variables.
   *
   * This is the regression this change nearly introduced. The template reads
   * the field as `pb_field_value('pb_display_parallax_scroll', 'legacy')`, so
   * an unset value has always selected the legacy effect. The `when` guard
   * originally spelled this `!== 'legacy'`, which is TRUE for an empty field —
   * and all six parallax sections on a test site have it unset, so every one of
   * them would have silently gained three custom properties it never had.
   */
  public function testParallaxUnsetScrollEmitsNoAnimationVars(): void {
    $paragraph = $this->mockParagraph('parallax_section_bundle', 296, [
      'pb_display_parallax_height' => '400',
      'pb_display_para_height_unit' => 'px',
      'pb_display_para_bg_anim_speed' => '0.3',
      'pb_display_para_anim_easing' => 'ease_in_out',
      'pb_display_parallax_bg_position' => 'center',
    ]);
    $css = $this->builder->build($paragraph, '#pb-parallax-section-296');

    $this->assertStringContainsString('--section-height: 400px;', $css);
    $this->assertStringNotContainsString('--animation-speed', $css);
    $this->assertStringNotContainsString('--animation-easing', $css);
    $this->assertStringNotContainsString('--background-position', $css);
  }

  /**
   * An explicit legacy scroll value likewise emits no animation variables.
   */
  public function testParallaxLegacyScrollEmitsNoAnimationVars(): void {
    $paragraph = $this->mockParagraph('parallax_section_bundle', 297, [
      'pb_display_parallax_scroll' => 'legacy',
      'pb_display_para_bg_anim_speed' => '0.3',
    ]);
    $this->assertStringNotContainsString(
      '--animation-speed',
      $this->builder->build($paragraph, '#pb-parallax-section-297'),
    );
  }

  /**
   * A JS-mode section emits all three animation variables on the bg element.
   *
   * The rules reading them sit behind `.parallax-js .pb-parallax__bg`, so the
   * selector matters as much as the values. `ease_in_out` becomes
   * `ease-in-out`, matching the template's `|replace({_: '-'})`.
   */
  public function testParallaxJsModeEmitsAnimationVarsOnBg(): void {
    $paragraph = $this->mockParagraph('parallax_section_bundle', 298, [
      'pb_display_parallax_scroll' => 'js',
      'pb_display_para_bg_anim_speed' => '0.3',
      'pb_display_para_anim_easing' => 'ease_in_out',
      'pb_display_parallax_bg_position' => 'center',
    ]);
    $css = $this->builder->build($paragraph, '#pb-parallax-section-298');

    $this->assertStringContainsString(
      '#pb-parallax-section-298 .pb-parallax__bg',
      $css,
    );
    $this->assertStringContainsString('--animation-speed: 0.3s;', $css);
    $this->assertStringContainsString('--animation-easing: ease-in-out;', $css);
    $this->assertStringContainsString('--background-position: center;', $css);
  }

  /**
   * Image overlay colours land on the WRAPPER, for the CSS to inherit.
   *
   * The strings asserted here are the ones the removed inline path produced.
   * `PbDynamicCssBuilderTest::testImageOverlayInlineStyle` pinned exactly
   * `--pb-bg: rgba(0,0,0, 0.80);` and `--pb-tx: #ffffff` until 2026-09-24,
   * when it was deleted together with `buildInlineCustomPropertiesStyle()`
   * itself — so THIS test is now the only place those exact strings are
   * pinned. `overlay_rgba` and buildDeclaration() compose an identical
   * sprintf over the same normaliser, so this is a transport change, not a
   * colour change. A raw triplet passes
   * through `normaliseToRgbTriplet` untouched, which is why the spacing here
   * differs from the hex-sourced `rgba(250, 253, 255, 0.36)` elsewhere.
   */
  public function testImageOverlayColoursOnWrapper(): void {
    $paragraph = $this->mockParagraph('image_overlay_bundle', 23, [
      'pb_display_bg' => '0,0,0',
      'pb_display_bg_opacity' => '80',
      'pb_display_text' => '#ffffff',
    ]);
    $css = $this->builder->build($paragraph, '#pb-image-overlay-23');

    $this->assertStringContainsString('--pb-bg: rgba(0,0,0, 0.80);', $css);
    $this->assertStringContainsString('--pb-tx: #ffffff;', $css);
  }

  /**
   * 🚨 The overlay colours must NOT be scoped to a descendant.
   *
   * Scoping to `.pb-overlay__container` would colour the container even in
   * title mode, where the stylesheet forces it transparent — and it would
   * break PbDynamicCssBuilderTest, which asserts that selector never appears
   * in this bundle's generated CSS. The wrapper is the only target that lets
   * BOTH existing mode rules inherit.
   */
  public function testImageOverlayColoursAreNotScopedToDescendant(): void {
    $paragraph = $this->mockParagraph('image_overlay_bundle', 23, [
      'pb_display_bg' => '0,0,0',
      'pb_display_bg_opacity' => '80',
    ]);
    $css = $this->builder->build($paragraph, '#pb-image-overlay-23');

    $this->assertStringContainsString('#pb-image-overlay-23 {', $css);
    $this->assertStringNotContainsString('.pb-overlay__container', $css);
    $this->assertStringNotContainsString('.pb-overlay__title', $css);
  }

  /**
   * Hover colours emit without the opacity treatment.
   *
   * collectColorVariables() applied opacity to `pb_display_bg` only; every
   * other colour took the plain branch. Reproducing that asymmetry is the
   * difference between the same colour and a similar one.
   */
  public function testImageOverlayHoverColoursSkipOpacity(): void {
    $paragraph = $this->mockParagraph('image_overlay_bundle', 24, [
      'pb_display_bg_hover' => '#123456',
      'pb_display_text_hover' => '#abcdef',
      'pb_display_bg_opacity' => '50',
    ]);
    $css = $this->builder->build($paragraph, '#pb-image-overlay-24');

    $this->assertStringContainsString('--pb-bg-h: #123456;', $css);
    $this->assertStringContainsString('--pb-tx-h: #abcdef;', $css);
    $this->assertStringNotContainsString('rgba(', $css);
  }

}
