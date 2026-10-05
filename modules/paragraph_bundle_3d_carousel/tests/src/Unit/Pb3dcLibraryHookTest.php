<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraph_bundle_3d_carousel\Unit;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\paragraph_bundle_3d_carousel\Hook\Pb3dcLibraryHook;
use Drupal\paragraphs\ParagraphInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The geometry stylesheet is attached in PHP, not only by the template.
 *
 * 🚨 THE REGRESSION THIS GUARDS, AND WHY A RENDERED PAGE CANNOT GUARD IT.
 *
 * v2 split one stylesheet into a shared base plus a geometry layer, and the
 * template attaches the geometry layer with a line v1 never had. A site that
 * overrode this bundle's template under v1 keeps serving ITS copy after the
 * update — it still attaches the base library, because that name did not
 * change, so the carousel renders and looks broadly right. What it cannot
 * attach is the new line, so the ring silently loses its transition, its
 * pointer-events pair and its end-of-run arrow hiding. The single-library v1
 * arrangement could not fail this way: there was nothing for a stale template
 * to be stale ABOUT.
 *
 * 📐 MEASURING THE LIVE PAGE PROVES NOTHING HERE, AND THAT WAS MEASURED.
 * On a current template the library is attached twice — once by Twig, once by
 * the hook — and Drupal de-duplicates. A page carrying the stylesheet exactly
 * once therefore looks identical whether the hook works or is completely
 * inert: node/93 returned exactly one ring stylesheet and zero coverflow rules
 * both before and after this hook existed. Success and failure are
 * indistinguishable at the transport, which is why this calls the hook
 * directly instead.
 *
 * Neuter the attach in ::preprocessParagraph() and every case here goes red.
 *
 * @group paragraphs_bundles
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
final class Pb3dcLibraryHookTest extends TestCase {

  /**
   * The bundle the hook owns.
   */
  private const BUNDLE = '3d_carousel_bundle';

  /**
   * The field holding the chosen geometry.
   */
  private const FIELD = 'pb_display_3d_car_style';

  /**
   * Library id prefix for the geometry stylesheets.
   */
  private const PREFIX = 'paragraph_bundle_3d_carousel/paragraph-bundle-3d-carousel-';

  /**
   * A stored style resolves to its own stylesheet, and to nothing else.
   *
   * @param string|null $stored
   *   Value held in the style field; NULL means the field is empty.
   * @param string $expected
   *   The geometry whose library must be attached.
   * @param string $why
   *   What this case is about, shown on failure.
   */
  #[DataProvider('styleCases')]
  public function testStoredStyleSelectsItsLibrary(?string $stored, string $expected, string $why): void {
    $variables = ['paragraph' => $this->carousel($stored)];

    (new Pb3dcLibraryHook())->preprocessParagraph($variables);

    self::assertSame([self::PREFIX . $expected], $variables['#attached']['library'] ?? [], $why);
  }

  /**
   * Every geometry value that can reach the hook, and what it must produce.
   *
   * @return array<string, array{string|null, string, string}>
   *   Case name => [stored value, expected geometry, failure message].
   */
  public static function styleCases(): array {
    return [
      'ring' => [
        'ring', 'ring',
        'A ring carousel must get the ring stylesheet.',
      ],
      'coverflow' => [
        'coverflow', 'coverflow',
        'A coverflow must get the fan stylesheet.',
      ],
      // 🚨 THE v1 UPGRADE CASE, and the reason this file exists. A field
      // default applies only when an entity is CREATED, so every carousel
      // predating the field holds no value at all. Measured on a test site:
      // all five existing carousels were empty before the backfill ran.
      'empty field (content predating the field)' => [
        NULL, 'ring',
        'A carousel created before the style field existed must still be a ring.',
      ],
      // A stray value must never be interpolated into a library id — asking
      // Drupal for a library that does not exist is a different, louder bug.
      'unrecognised value' => [
        'spinning-drum', 'ring',
        'An unknown geometry must fall back to ring, not build a library id from the raw value.',
      ],
      'whitespace around a real value' => [
        "  coverflow\n", 'coverflow',
        'A padded value must still resolve, not fall through to the default.',
      ],
    ];
  }

  /**
   * A paragraph from before update 9003 has no style field at all.
   */
  public function testMissingFieldFallsBackToRing(): void {
    $paragraph = $this->createMock(ParagraphInterface::class);
    $paragraph->method('bundle')->willReturn(self::BUNDLE);
    $paragraph->method('hasField')->with(self::FIELD)->willReturn(FALSE);
    // ::get() must not be reached — calling it on an absent field throws.
    $paragraph->expects(self::never())->method('get');

    $variables = ['paragraph' => $paragraph];
    (new Pb3dcLibraryHook())->preprocessParagraph($variables);

    self::assertSame(
      [self::PREFIX . 'ring'],
      $variables['#attached']['library'] ?? [],
      'A carousel whose style field is not installed yet must render as a ring.',
    );
  }

  /**
   * A field item carrying no usable value falls back rather than crashing.
   */
  public function testMalformedItemFallsBackToRing(): void {
    $item = $this->createMock(FieldItemInterface::class);
    $item->method('getValue')->willReturn(['not_value' => 'coverflow']);

    $items = $this->createMock(FieldItemListInterface::class);
    $items->method('isEmpty')->willReturn(FALSE);
    $items->method('first')->willReturn($item);

    $paragraph = $this->createMock(ParagraphInterface::class);
    $paragraph->method('bundle')->willReturn(self::BUNDLE);
    $paragraph->method('hasField')->willReturn(TRUE);
    $paragraph->method('get')->willReturn($items);

    $variables = ['paragraph' => $paragraph];
    (new Pb3dcLibraryHook())->preprocessParagraph($variables);

    self::assertSame([self::PREFIX . 'ring'], $variables['#attached']['library'] ?? []);
  }

  /**
   * CONTROL: another bundle's paragraph is left completely alone.
   *
   * Without this, a hook that attached the stylesheet unconditionally would
   * pass every case above while putting carousel CSS on every paragraph on
   * the site.
   */
  public function testOtherBundlesAreUntouched(): void {
    $paragraph = $this->createMock(ParagraphInterface::class);
    $paragraph->method('bundle')->willReturn('simple_bundle');
    $paragraph->expects(self::never())->method('hasField');

    $variables = ['paragraph' => $paragraph];
    (new Pb3dcLibraryHook())->preprocessParagraph($variables);

    self::assertArrayNotHasKey(
      '#attached',
      $variables,
      'A non-carousel paragraph must not receive the carousel stylesheet.',
    );
  }

  /**
   * CONTROL: preprocess also runs for things that are not paragraphs.
   */
  public function testNonParagraphVariablesAreUntouched(): void {
    $variables = ['paragraph' => NULL];
    (new Pb3dcLibraryHook())->preprocessParagraph($variables);
    self::assertArrayNotHasKey('#attached', $variables);

    $empty = [];
    (new Pb3dcLibraryHook())->preprocessParagraph($empty);
    self::assertSame([], $empty, 'Variables with no paragraph must come back unchanged.');
  }

  /**
   * An existing #attached is added to, never replaced.
   */
  public function testExistingAttachmentsSurvive(): void {
    $variables = [
      'paragraph' => $this->carousel('ring'),
      '#attached' => ['library' => ['some_module/already-here']],
    ];

    (new Pb3dcLibraryHook())->preprocessParagraph($variables);

    self::assertSame(
      ['some_module/already-here', self::PREFIX . 'ring'],
      $variables['#attached']['library'],
      'The hook must append to #attached, not overwrite what another hook put there.',
    );
  }

  /**
   * Builds a carousel paragraph whose style field holds the given value.
   *
   * Mocks only isEmpty(), first() and getValue() — the methods the suite's
   * stub interfaces declare, so this works whether or not Drupal core is
   * reachable on the run.
   *
   * @param string|null $style
   *   Stored geometry, or NULL for an empty field.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The mocked paragraph.
   */
  private function carousel(?string $style): ParagraphInterface {
    $items = $this->createMock(FieldItemListInterface::class);

    if ($style === NULL) {
      $items->method('isEmpty')->willReturn(TRUE);
      $items->expects(self::never())->method('first');
    }
    else {
      $item = $this->createMock(FieldItemInterface::class);
      $item->method('getValue')->willReturn(['value' => $style]);
      $items->method('isEmpty')->willReturn(FALSE);
      $items->method('first')->willReturn($item);
    }

    $paragraph = $this->createMock(ParagraphInterface::class);
    $paragraph->method('bundle')->willReturn(self::BUNDLE);
    $paragraph->method('hasField')->with(self::FIELD)->willReturn(TRUE);
    $paragraph->method('get')->with(self::FIELD)->willReturn($items);

    return $paragraph;
  }

}
