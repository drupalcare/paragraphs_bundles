<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit;

use Drupal\Tests\paragraphs_bundles\Traits\ModuleFilesTrait;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Every shipped paragraph bundle says what it is.
 *
 * WHY THIS EXISTS. A bundle's description is not decoration: it is the only
 * sentence that says what a section IS rather than what shape it has, and two
 * different readers depend on it — a site builder choosing from the Add list,
 * and the assistant in paragraphs_bundles_ai, which is handed the description
 * verbatim and writes the section's words from it.
 *
 * MEASURED 2026-09-17, AND THIS IS THE CASE THE TEST EXISTS FOR. Thirty-two of
 * the forty shipped bundles carried a sentence and eight did not — and the
 * eight were exactly the inner section bundles (a slide, a tab, an accordion
 * row), which are the ones the assistant actually writes content into. It was
 * a deliberate decision, recorded in the update hook: a slide is never chosen
 * from a list, so it needs no sentence. That held for the person and not for
 * the model, which was given the bare label "Parallax Section" and filled the
 * body with a description of the layout instead of content.
 *
 * So the defect was never a typo — it was a reasonable assumption that a new
 * consumer invalidated, and nothing could go red when it did. This can.
 *
 * NO DRUPAL, NO DATABASE. The subject is the shipped yml on disk, which is the
 * source the update hook copies onto a site. Reading the files directly keeps
 * this a unit test and keeps it honest: it fails for a bundle added tomorrow
 * with the description left blank, which is the way this recurs.
 */
#[CoversNothing]
final class PbBundleDescriptionContractTest extends TestCase {

  use ModuleFilesTrait;

  /**
   * Bundles allowed to ship without a sentence, each with a reason.
   *
   * EMPTY ON PURPOSE. It is here so that an exception has to be written down
   * and justified rather than simply left blank, which is how the eight got
   * missed the first time.
   *
   * @var array<string, string>
   */
  private const ALLOWED_WITHOUT = [];

  /**
   * The module root.
   */
  private function moduleRoot(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * Every shipped paragraphs type config file, keyed by machine name.
   *
   * @return array<string, array{path: string, label: string, description: string}>
   *   Machine name => what the file says about itself.
   */
  private function shippedBundles(): array {
    $root = $this->moduleRoot();

    $files = $this->moduleFiles($root);

    $found = [];

    foreach ($files as $file) {
      $name = $file->getFilename();

      if (!str_starts_with($name, 'paragraphs.paragraphs_type.') || !str_ends_with($name, '.yml')) {
        continue;
      }

      $data = Yaml::parse((string) file_get_contents($file->getPathname()));

      if (!is_array($data)) {
        continue;
      }

      // `id`, not `type` — a paragraphs type keys its machine name as `id`.
      $id = (string) ($data['id'] ?? '');

      if ($id === '') {
        continue;
      }

      $found[$id] = [
        'path' => str_replace($root . '/', '', $file->getPathname()),
        'label' => (string) ($data['label'] ?? ''),
        'description' => trim((string) ($data['description'] ?? '')),
      ];
    }

    ksort($found);

    return $found;
  }

  /**
   * The scan finds the bundles at all.
   *
   * CHECK THE INSTRUMENT BEFORE THE WORK. A walker that matched nothing would
   * pass every assertion below in silence, which is the failure mode this
   * whole file is written against.
   */
  public function testTheScanFindsTheShippedBundles(): void {
    $found = $this->shippedBundles();

    $this->assertGreaterThan(
      30,
      count($found),
      'Found ' . count($found) . ' paragraphs type files. PB ships around 40; a number this low means the walk is broken, not that the bundles are missing.',
    );

    $this->assertArrayHasKey('parallax_bundle', $found, 'parallax_bundle is shipped by paragraph_bundle_parallax and must be found.');
  }

  /**
   * Every shipped bundle carries a sentence saying what it is.
   */
  public function testEveryShippedBundleIsDescribed(): void {
    $missing = [];

    foreach ($this->shippedBundles() as $id => $facts) {
      if ($facts['description'] !== '' || isset(self::ALLOWED_WITHOUT[$id])) {
        continue;
      }

      $missing[] = sprintf('%s (%s) in %s', $id, $facts['label'], $facts['path']);
    }

    $this->assertSame(
      [],
      $missing,
      "These bundles ship no description. A site builder cannot tell what they are from the Add list, and paragraphs_bundles_ai is handed the bare label and writes filler:\n  " . implode("\n  ", $missing),
    );
  }

  /**
   * A description says something, rather than repeating the label.
   *
   * "Parallax Section — Parallax Section" tells the model nothing it did not
   * already have, so it would pass the test above while changing nothing.
   */
  public function testDescriptionIsNotJustTheLabelAgain(): void {
    $echoed = [];

    foreach ($this->shippedBundles() as $id => $facts) {
      if ($facts['description'] === '') {
        continue;
      }

      if (strcasecmp($facts['description'], $facts['label']) === 0) {
        $echoed[] = sprintf('%s repeats its label "%s"', $id, $facts['label']);
      }
    }

    $this->assertSame([], $echoed, implode("\n", $echoed));
  }

  /**
   * The inner section bundles are described, not only the outer ones.
   *
   * These eight are named explicitly because they are the ones that were
   * missed, and because they are the ones the assistant writes into. A
   * regression here is the exact defect of 2026-09-17 returning.
   */
  public function testTheInnerSectionBundlesAreDescribed(): void {
    $inner = [
      'accordion_section_bundle',
      'card_image_section_bundle',
      'card_text_section_bundle',
      'image_grid_section_bundle',
      'link_section_bundle',
      'parallax_section_bundle',
      'slideshow_section_bundle',
      'tab_section_bundle',
    ];

    $found = $this->shippedBundles();

    foreach ($inner as $id) {
      $this->assertArrayHasKey($id, $found, sprintf('%s is not shipped any more; if it was renamed, rename it here too.', $id));
      $this->assertNotSame('', $found[$id]['description'], sprintf('%s ships no description, so the assistant is given only the label "%s" and writes a description of the layout instead of content.', $id, $found[$id]['label']));
    }
  }

}
