<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraph_bundle_icon\Unit;

use Drupal\paragraph_bundle_icon\Service\PbiFontUrlBuilder;
use Drupal\paragraph_bundle_icon\Service\PbiFontUrlBuilderInterface;
use Drupal\paragraph_bundle_icon\Service\PbiIconNameRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Pins the rules that keep the icon font small and the page correct.
 *
 * WHY EACH RULE EXISTS. Every one below was measured against the live Google
 * Fonts API on 2026-09-22, and each guards a failure that is silent in the
 * browser — the page still renders, so nothing tells you it regressed:
 *
 * - `&icon_names=` with an EMPTY value returns HTTP 400 and an HTML error
 *   page instead of CSS. The stylesheet simply never loads.
 * - Omitting `icon_names` returns the COMPLETE font: 344,712 bytes instead of
 *   ~1,700. The icons look perfect and the page is 200x heavier.
 * - A name Google does not know is dropped silently, so that one icon does not
 *   appear and nothing is logged.
 *
 * So `buildUrl()` returning an empty string is not an error path, it is the
 * designed answer for "there is nothing valid to ask for" — and it means the
 * caller attaches no stylesheet, leaving the bundled font on disk as the
 * fallback. That fallback is the difference between this change and the one
 * reverted in 4ea0ce5, which removed the fonts and turned every icon into its
 * ligature name in plain text on upgrade.
 *
 * Measured end to end against the maintainer's own site: the four distinct
 * icon names stored there reduce to `grain,star` and return 3,156 bytes,
 * against 3,411,436 bundled today.
 *
 * @group paragraphs_bundles
 */
#[Group('paragraphs_bundles')]
final class PbiFontSubsettingTest extends TestCase {

  /**
   * A registry backed by a small fixture, not the shipped 4,284-name list.
   *
   * The shipped list is exercised separately by ::testTheShippedNameListIsUsable
   * so that a change to the real data cannot quietly rewrite what every other
   * assertion here means.
   */
  private function registry(): PbiIconNameRegistry {
    $file = tempnam(sys_get_temp_dir(), 'pbi');
    file_put_contents($file, "home\nsearch\nstar\ngrain\nsupport_agent\n");

    return new PbiIconNameRegistry($file);
  }

  /**
   * Rubbish is removed, and real names survive.
   */
  public function testInvalidNamesAreFiltered(): void {
    $valid = $this->registry()->filter([
      'star',
      'grain',
      'PB fixture pb_content_gms_icon_name',
      'thisdoesnotexist',
    ]);

    self::assertSame(['grain', 'star'], $valid);
  }

  /**
   * Duplicates collapse, whitespace is trimmed, blanks vanish, order is sorted.
   */
  public function testNamesAreTrimmedDeduplicatedAndSorted(): void {
    self::assertSame(
      ['home', 'star'],
      $this->registry()->filter([' home ', 'home', 'star', '', 'home']),
    );
  }

  /**
   * A valid request names its icons and blocks the ligature flash.
   */
  public function testUrlCarriesIconNamesAndBlocksTheLigatureFlash(): void {
    $url = (new PbiFontUrlBuilder())->buildUrl('outlined', ['home', 'search']);

    self::assertStringContainsString('icon_names=home,search', $url);
    self::assertStringContainsString('family=Material+Symbols+Outlined', $url);
    self::assertStringContainsString(PbiFontUrlBuilderInterface::AXES, $url);

    // `display=block` hides the fallback while loading. With `swap` the browser
    // paints the fallback face, and for a ligature icon font that paints the
    // LIGATURE TEXT — a paragraph set to `home` flashes the word "home".
    self::assertStringContainsString('display=block', $url);
  }

  /**
   * No icons means no stylesheet — never an empty parameter, never a full font.
   */
  public function testNoIconsProducesNoUrl(): void {
    self::assertSame('', (new PbiFontUrlBuilder())->buildUrl('outlined', []));
  }

  /**
   * An unknown style produces no stylesheet rather than a broken family name.
   */
  public function testUnknownStyleProducesNoUrl(): void {
    self::assertSame('', (new PbiFontUrlBuilder())->buildUrl('squiggly', ['home']));
  }

  /**
   * Every style this module offers maps to a real Material Symbols family.
   *
   * The family name must match the `font-family` in the shipped stylesheets
   * and in the `.material-symbols-*` classes, or the CSS resolves to nothing
   * and every icon falls back to its ligature name in plain text.
   */
  public function testEveryStyleMapsToItsFamily(): void {
    $builder = new PbiFontUrlBuilder();

    self::assertSame('Material Symbols Outlined', $builder->familyName('outlined'));
    self::assertSame('Material Symbols Rounded', $builder->familyName('rounded'));
    self::assertSame('Material Symbols Sharp', $builder->familyName('sharp'));
    self::assertSame('', $builder->familyName('squiggly'));
  }

  /**
   * A missing name list degrades to "attach nothing", never to a fatal.
   *
   * A packaging mistake must not turn a content page into a white screen. With
   * no names, every caller attaches no stylesheet and the bundled font renders.
   */
  public function testMissingNameListIsNotFatal(): void {
    $registry = new PbiIconNameRegistry('/nonexistent/material-symbols-names.txt');

    self::assertSame([], $registry->all());
    self::assertFalse($registry->isValid('home'));
    self::assertSame([], $registry->filter(['home']));
  }

  /**
   * The list that actually ships is present, large, and contains real icons.
   *
   * Guards the data file rather than the logic: an empty or truncated list
   * would make every icon invalid, and the symptom would be icons silently
   * disappearing from pages rather than any error.
   */
  public function testTheShippedNameListIsUsable(): void {
    $registry = new PbiIconNameRegistry();

    self::assertGreaterThan(4000, count($registry->all()));
    self::assertTrue($registry->isValid('home'));
    self::assertTrue($registry->isValid('star'));
    self::assertTrue($registry->isValid('support_agent'));
    self::assertFalse($registry->isValid('thisdoesnotexist'));
  }

  /**
   * The CSP sources cover both hosts a subsetted request touches.
   *
   * The stylesheet is fetched from fonts.googleapis.com and the font file it
   * references from fonts.gstatic.com. Declaring both per render element lets
   * the Content-Security-Policy module allow them automatically, so a site
   * running a strict policy needs no configuration. Miss one and the icons
   * vanish on exactly the sites that care most.
   */
  public function testCspSourcesCoverBothHosts(): void {
    $sources = PbiFontUrlBuilderInterface::CSP_SOURCES;

    self::assertSame(['https://fonts.googleapis.com'], $sources['style-src']);
    self::assertSame(['https://fonts.gstatic.com'], $sources['font-src']);
  }

}
