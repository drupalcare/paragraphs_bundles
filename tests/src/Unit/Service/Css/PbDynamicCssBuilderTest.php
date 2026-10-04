<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit\Service\Css;

use Drupal\paragraphs_bundles\Service\Bundle\BundleSlugger;
use Drupal\paragraphs_bundles\Service\Css\PbColorNormalizer;
use Drupal\paragraphs_bundles\Service\Css\PbDynamicCssBuilder;
use Drupal\paragraphs_bundles\Service\Css\PbDynamicUserVarBuilder;
use Drupal\Tests\paragraphs_bundles\Unit\PbParagraphMockTrait;
use Drupal\paragraphs_bundles\Service\Field\PbImageFieldUrlResolverInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PbDynamicCssBuilder.
 */
#[CoversClass(PbDynamicCssBuilder::class)]
#[Group('paragraphs_bundles')]
final class PbDynamicCssBuilderTest extends TestCase {

  use PbParagraphMockTrait;

  /**
   * Builder under test.
   */
  private PbDynamicCssBuilder $builder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $images = $this->createStub(PbImageFieldUrlResolverInterface::class);
    $userVarBuilder = new PbDynamicUserVarBuilder(
      new PbColorNormalizer(),
      $images,
    );
    $this->builder = new PbDynamicCssBuilder(
      new BundleSlugger(),
      new PbColorNormalizer(),
      $userVarBuilder,
    );
  }

  /**
   * A paragraph with no display fields emits no CSS.
   */
  public function testEmptyParagraphEmitsNoCss(): void {
    $paragraph = $this->mockParagraph('simple_bundle', 42);
    $this->assertSame('', $this->builder->build($paragraph));
  }

  /**
   * Background color emits an rgba() var on the inner selector.
   */
  public function testBackgroundColourOnInnerSelector(): void {
    $paragraph = $this->mockParagraph('simple_bundle', 42, [
      'pb_display_bg' => '#fafdff',
      'pb_display_bg_opacity' => '90',
    ]);
    $css = $this->builder->build($paragraph);
    $this->assertStringContainsString(
      '#pb-simple-42 > .pb-ui-inner',
      $css,
    );
    $this->assertStringContainsString(
      '--pb-bg: rgba(250, 253, 255, 0.90);',
      $css,
    );
    $this->assertStringContainsString(
      'background-color: var(--pb-bg);',
      $css,
    );
  }

  /**
   * An integer opacity emits, exactly as the equivalent string does.
   *
   * `pb_display_bg_opacity` is a range storage and the three `*_active_item`
   * fields are list_integer, so all four hold real `int`s on an entity
   * populated in memory — which is what hook_paragraph_insert() builds from.
   * They only become numeric strings after a round trip through the database.
   * While fieldStringValue() gated on `is_string()`, a paragraph created by a
   * recipe, migration or demo script silently got a stylesheet with those
   * declarations missing, and kept it until someone re-saved the paragraph
   * through the form. Every other test in this class passes strings, which is
   * why none of them could catch it.
   */
  public function testIntegerFieldValuesAreCoercedNotDropped(): void {
    $paragraph = $this->mockParagraph('simple_bundle', 42, [
      'pb_display_bg' => '#fafdff',
      'pb_display_bg_opacity' => 90,
    ]);
    $this->assertStringContainsString(
      '--pb-bg: rgba(250, 253, 255, 0.90);',
      $this->builder->build($paragraph),
    );
  }

  /**
   * Setting only the text color leaves other color vars unset.
   */
  public function testPartialColourFieldsDoNotZeroOthers(): void {
    $paragraph = $this->mockParagraph('simple_bundle', 5, [
      'pb_display_text' => '#333333',
    ]);
    $css = $this->builder->build($paragraph);
    $this->assertStringContainsString('--pb-tx: #333333;', $css);
    $this->assertStringNotContainsString('--pb-bg:', $css);
    $this->assertStringNotContainsString('background-color:', $css);
    $this->assertStringContainsString('color: var(--pb-tx);', $css);
  }

  /**
   * The hero bundle scopes colors to the text-inner override selector.
   */
  public function testHeroUsesInnerOverrideSelector(): void {
    $paragraph = $this->mockParagraph('hero_bundle', 12, [
      'pb_display_bg' => '#000000',
      'pb_display_bg_opacity' => '100',
    ]);
    $css = $this->builder->build($paragraph);
    $this->assertStringContainsString(
      '#pb-hero-12 .pb-hero__text-inner',
      $css,
    );
    $this->assertStringNotContainsString('> .pb-ui-inner', $css);
  }

  /**
   * The image overlay bundle scopes vars to the pb-ui-inner selector.
   */
  public function testImageOverlayInnerScopeUsesPbUiInner(): void {
    $paragraph = $this->mockParagraph('image_overlay_bundle', 23, [
      'pb_display_border_color' => '#cccccc',
      'pb_display_bg' => '0,0,0',
      'pb_display_bg_opacity' => '80',
      'pb_display_text' => '#ffffff',
    ]);
    $css = $this->builder->build($paragraph);
    $this->assertStringContainsString(
      '#pb-image-overlay-23 > .pb-ui-inner',
      $css,
    );
    $this->assertStringContainsString('--pb-br:', $css);
    $this->assertStringNotContainsString('.pb-overlay__container', $css);
  }

  /**
   * The image background bundle omits inner background vars.
   */
  public function testImageBackgroundExcludesInnerBgVars(): void {
    $paragraph = $this->mockParagraph('image_background_bundle', 9, [
      'pb_display_bg' => '#fafdff',
      'pb_display_bg_opacity' => '36',
    ]);
    $css = $this->builder->build($paragraph);
    $this->assertStringNotContainsString('--pb-bg:', $css);
    $this->assertStringContainsString('.img-color-overlay', $css);
  }

  /**
   * Link colors emit scoped anchor rules for default and hover states.
   */
  public function testLinkColoursEmitScopedRules(): void {
    $paragraph = $this->mockParagraph('simple_bundle', 1, [
      'pb_display_link' => '#0066cc',
      'pb_display_link_hover' => '#004499',
    ]);
    $css = $this->builder->build($paragraph);
    $this->assertStringContainsString(':where(a)', $css);
    $this->assertStringContainsString('color: var(--pb-lk);', $css);
    $this->assertStringContainsString('color: var(--pb-lk-h);', $css);
  }

  /**
   * The 3D carousel outer id uses the pb-3d-carousel prefix.
   */
  public function test3dCarouselOuterIdUsesPb3dCarouselPrefix(): void {
    $paragraph = $this->mockParagraph('3d_carousel_bundle', 7, [
      'pb_display_bg' => '#112233',
    ]);
    $css = $this->builder->build($paragraph);
    $this->assertStringContainsString('#pb-3d-carousel-7 > .pb-ui-inner', $css);
  }

  /**
   * Parallax section colors are scoped to the text layer selector.
   *
   * The section's id is its own — `pb-parallax-section-{id}`. Until 2.0.0 it
   * shared `pb-parallax-{id}` with `parallax_bundle`, so the id did not say
   * which of the two bundles it was on.
   */
  public function testParallaxSectionColorsScopeTextLayer(): void {
    $paragraph = $this->mockParagraph('parallax_section_bundle', 42, [
      'pb_display_bg' => '#112233',
      'pb_display_text' => '#ffffff',
    ]);
    $css = $this->builder->build($paragraph);
    $this->assertStringContainsString(
      '#pb-parallax-section-42 .pb-parallax__text',
      $css,
    );
    $this->assertStringContainsString('--pb-bg:', $css);
    $this->assertStringContainsString('background-color: var(--pb-bg);', $css);
  }

  /**
   * A pb_content node emits a scoped background color rule.
   */
  public function testPbContentNodeBackground(): void {
    $node = $this->mockNode('pb_content', 99, [
      'pb_display_content_bg' => '#fafdff',
      'pb_display_content_bg_opacity' => '50',
    ]);
    $css = $this->builder->buildForNode($node);
    $this->assertStringContainsString('#node-99', $css);
    $this->assertStringContainsString(
      'background-color: rgba(250, 253, 255, 0.50);',
      $css,
    );
  }

  /**
   * A pb_content node with no background emits nothing.
   */
  public function testPbContentNodeEmptyBgEmitsNothing(): void {
    $node = $this->mockNode('pb_content', 99);
    $this->assertSame('', $this->builder->buildForNode($node));
  }

  /**
   * A colour value that is not well-formed produces no declaration at all.
   *
   * Defence in depth. The widget's `#element_validate` and the `ColorItem`
   * field constraint both run before storage, but config import, migration and
   * REST/JSON:API writes can still land an arbitrary string in the field. A
   * malformed colour has no valid rendering, so the builder fails closed and
   * emits nothing rather than a scrubbed remnant.
   *
   * @param string $value
   *   Stored field value that must be rejected.
   */
  #[DataProvider('rejectedColourProvider')]
  public function testRejectedColourEmitsNoCss(string $value): void {
    $paragraph = $this->mockParagraph('simple_bundle', 42, [
      'pb_display_text' => $value,
    ]);
    $this->assertSame('', $this->builder->build($paragraph));
  }

  /**
   * Values the colour emitter must reject.
   *
   * @return array<string, list<string>>
   *   Test case name => [stored value].
   */
  public static function rejectedColourProvider(): array {
    return [
      'declaration break-out into markup' => ['#000; } </style><script>alert(1)</script>'],
      'rule break-out' => ['red } html{display:none} .x{'],
      'channel above 255' => ['256,0,0'],
      'hex without leading hash' => ['fff'],
      'five-digit hex' => ['#12345'],
    ];
  }

  /**
   * A well-formed colour value still emits its declaration.
   *
   * Guards the other side of the fail-closed rule: tightening validation must
   * not silently drop legitimate editor input, including the legacy raw-triplet
   * form that v1 widgets wrote.
   *
   * @param string $value
   *   Stored field value that must be accepted.
   * @param string $expected
   *   Declaration the builder must emit for it.
   */
  #[DataProvider('acceptedColourProvider')]
  public function testAcceptedColourEmitsDeclaration(string $value, string $expected): void {
    $paragraph = $this->mockParagraph('simple_bundle', 42, [
      'pb_display_text' => $value,
    ]);
    $this->assertStringContainsString($expected, $this->builder->build($paragraph));
  }

  /**
   * Values the colour emitter must accept.
   *
   * @return array<string, list<string>>
   *   Test case name => [stored value, expected declaration].
   */
  public static function acceptedColourProvider(): array {
    return [
      'six-digit hex' => ['#1a2b3c', '--pb-tx: #1a2b3c;'],
      'three-digit hex' => ['#abc', '--pb-tx: #abc;'],
      'spaced legacy triplet' => ['0, 0, 0', '--pb-tx: rgb(0, 0, 0);'],
      'maximum legacy triplet' => ['255,255,255', '--pb-tx: rgb(255,255,255);'],
    ];
  }

  /**
   * A rejected colour leaves no dangling var() reference behind.
   *
   * The property declarations (`background-color: var(--pb-bg);`) are emitted
   * from the same map as the custom properties, so dropping a rejected value
   * must drop its property rule too — otherwise the rule would resolve against
   * an undefined custom property and blank out the inherited colour.
   */
  public function testRejectedColourDropsItsPropertyRuleButKeepsSiblings(): void {
    $paragraph = $this->mockParagraph('simple_bundle', 7, [
      'pb_display_bg' => 'red } html{display:none} .x{',
      'pb_display_bg_opacity' => '100',
      'pb_display_text' => '#333333',
    ]);
    $css = $this->builder->build($paragraph);
    $this->assertStringNotContainsString('--pb-bg', $css);
    $this->assertStringNotContainsString('background-color:', $css);
    $this->assertStringContainsString('--pb-tx: #333333;', $css);
    $this->assertStringContainsString('color: var(--pb-tx);', $css);
  }

  /**
   * A malicious colour cannot escape the generated rule block.
   */
  public function testMaliciousColourNeverReachesGeneratedCss(): void {
    $paragraph = $this->mockParagraph('simple_bundle', 3, [
      'pb_display_bg' => '#000; } </style><script>alert(1)</script>',
      'pb_display_bg_opacity' => '100',
      'pb_display_text' => '#333333',
    ]);
    $css = $this->builder->build($paragraph);

    // Only the single rule block's own braces may appear.
    $this->assertSame(1, substr_count($css, '{'));
    $this->assertSame(1, substr_count($css, '}'));
    $this->assertStringNotContainsString('<', $css);
    $this->assertStringNotContainsString('alert(1)', $css);
  }

  /**
   * A pb_content node with a malformed background emits nothing.
   */
  public function testPbContentNodeRejectsMalformedBackground(): void {
    $node = $this->mockNode('pb_content', 99, [
      'pb_display_content_bg' => '#000; } </style><script>alert(1)</script>',
      'pb_display_content_bg_opacity' => '50',
    ]);
    $this->assertSame('', $this->builder->buildForNode($node));
  }

}
