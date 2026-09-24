<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_apex\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Keeps this module's README honest about how PB loads its images.
 *
 * 🚨 THE CLAIM THIS GUARDS WAS SHIPPED AND WAS FALSE.
 * The README told readers that "PB's `pb_media_responsive_wide` sets
 * `image_loading: eager`", and built a whole "the eager/lazy decision moves"
 * section on it. Measured 2026-09-23 against the shipped config AND a real
 * site: every PB media display that carries the setting is `lazy`, that one
 * included. The sentence had never been true.
 *
 * WHY IT NEEDED A TEST AND NOT JUST A CORRECTION. Nothing in the repository
 * could have gone red for it. A README describing a sibling module's config is
 * two files that must agree with no mechanism keeping them in step, which is
 * exactly the shape that rots: the config is owned by paragraph_bundle_image,
 * the sentence is owned here, and neither knows about the other.
 *
 * So this pins the PAIR:
 * - every shipped PB media display that sets image_loading sets it to lazy;
 * - and the README does not claim otherwise.
 *
 * If someone deliberately makes a display eager, this goes red and the README
 * has to be updated in the same change. That is the point, not an obstacle.
 *
 * ⚠ THE README MUST NOT QUOTE THE LITERAL SETTING STRING, EVEN TO DISOWN IT.
 * ::testTheReadmeDoesNotClaimPbLoadsEagerly is a plain substring check, so a
 * sentence saying "this used to claim image_loading: eager, and that was
 * false" trips it just as surely as the original claim did. That is exactly
 * what happened the first time this pair was written — the guard fired on its
 * own correction. The README says "loads its image eagerly" in prose instead.
 * A cleverer check that tried to read the surrounding sentence would be a
 * parser for English, and would fail in a quieter way.
 *
 * @group paragraphs_bundles_apex
 */
#[Group('paragraphs_bundles_apex')]
final class PbApexLoadingClaimTest extends TestCase {

  /**
   * The Paragraphs Bundles project root.
   *
   * __DIR__ is tests/src/Unit inside paragraphs_bundles_apex, so five levels
   * up is the project root: Unit -> src -> tests -> paragraphs_bundles_apex ->
   * modules -> paragraphs_bundles.
   */
  private static function projectRoot(): string {
    return dirname(__DIR__, 5);
  }

  /**
   * Every shipped media view display for the PB image media type.
   *
   * @return list<string>
   *   Absolute paths, sorted so a failure names files in a stable order.
   */
  private static function shippedDisplays(): array {
    $found = glob(
      self::projectRoot() . '/modules/*/config/optional/core.entity_view_display.media.pb_media_image.*.yml'
    ) ?: [];
    sort($found);

    return $found;
  }

  /**
   * The glob finds the real files, so an empty sweep cannot read as a pass.
   *
   * Without this control, a wrong path would make every assertion below
   * iterate nothing and the class would go green while measuring air.
   */
  public function testTheShippedDisplaysAreActuallyFound(): void {
    $displays = self::shippedDisplays();

    self::assertGreaterThanOrEqual(
      8,
      count($displays),
      'Found too few shipped media displays — the glob is probably wrong, so every other assertion here is measuring nothing.',
    );

    $names = array_map('basename', $displays);
    self::assertContains(
      'core.entity_view_display.media.pb_media_image.pb_media_responsive_wide.yml',
      $names,
      'The display the README names by hand was not found.',
    );
  }

  /**
   * No shipped PB media display requests eager loading.
   */
  public function testEveryShippedMediaDisplayLoadsLazily(): void {
    $offenders = [];

    foreach (self::shippedDisplays() as $path) {
      $parsed = Yaml::parseFile($path);
      if (!is_array($parsed)) {
        continue;
      }

      foreach ($parsed['content'] ?? [] as $field => $component) {
        $attribute = $component['settings']['image_loading']['attribute'] ?? NULL;
        if ($attribute !== NULL && $attribute !== 'lazy') {
          $offenders[] = sprintf('%s [%s] = %s', basename($path), $field, (string) $attribute);
        }
      }
    }

    self::assertSame(
      [],
      $offenders,
      "A shipped display no longer loads lazily, so this module's README is now wrong about it:\n" . implode("\n", $offenders),
    );
  }

  /**
   * The README does not reinstate the claim that PB loads eagerly.
   */
  public function testTheReadmeDoesNotClaimPbLoadsEagerly(): void {
    $readme = (string) file_get_contents(dirname(__DIR__, 3) . '/README.md');

    self::assertNotSame('', $readme, 'The README could not be read, so this assertion proves nothing.');

    self::assertStringNotContainsString(
      'image_loading: eager',
      $readme,
      'The README claims Paragraphs Bundles sets image_loading to eager. Measured 2026-09-23: every shipped PB media display is lazy.',
    );
  }

}
