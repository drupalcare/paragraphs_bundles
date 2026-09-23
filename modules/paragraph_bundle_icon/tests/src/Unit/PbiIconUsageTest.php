<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraph_bundle_icon\Unit;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Database\Schema;
use Drupal\paragraph_bundle_icon\Service\PbiIconNameRegistry;
use Drupal\paragraph_bundle_icon\Service\PbiIconUsage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The install-time paths, which a site with content never exercises.
 *
 * WHY THESE AND NOT THE HAPPY PATH. The happy path was verified in a browser on
 * a site that already had icon content. A FRESH INSTALL is a different path and
 * hits this service first: `hook_library_info_alter()` runs on the first cache
 * rebuild, which can happen while the module's optional field config has not
 * been imported yet — so the field tables may simply not exist. If the guard
 * were wrong, the symptom would be a fatal during install rather than a
 * fallback, and no amount of testing on a populated site would reveal it.
 *
 * `PbBundleInstallFunctionalTest` installs this submodule for real and is the
 * proper end-to-end gate, but it is a BrowserTestBase test and this project's
 * `phpunit.xml.dist` runs `tests/src/Unit` only. These cases put the same
 * guards in the suite that actually runs on every change.
 *
 * @group paragraphs_bundles
 */
#[Group('paragraphs_bundles')]
final class PbiIconUsageTest extends TestCase {

  /**
   * A registry backed by a small fixture rather than the shipped 4,284 names.
   */
  private function registry(): PbiIconNameRegistry {
    $file = tempnam(sys_get_temp_dir(), 'pbi');
    file_put_contents($file, "home\nsearch\nstar\ngrain\nsettings\n");

    return new PbiIconNameRegistry($file);
  }

  /**
   * Builds the service over a faked database.
   *
   * @param bool $nameTable
   *   Whether the icon-name field table exists.
   * @param bool $styleTable
   *   Whether the style field table exists.
   * @param list<object> $rows
   *   Rows the query returns.
   *
   * @return \Drupal\paragraph_bundle_icon\Service\PbiIconUsage
   *   The service under test.
   */
  private function usage(bool $nameTable, bool $styleTable, array $rows = []): PbiIconUsage {
    $schema = $this->createMock(Schema::class);
    $schema->method('tableExists')->willReturnCallback(
      static fn (string $table): bool => str_contains($table, 'gms_style') ? $styleTable : $nameTable,
    );

    $select = $this->createMock(SelectInterface::class);
    $select->method('addField')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('distinct')->willReturnSelf();
    $select->method('leftJoin')->willReturn('s');
    $select->method('execute')->willReturn(new \ArrayIterator($rows));

    $database = $this->createMock(Connection::class);
    $database->method('schema')->willReturn($schema);
    $database->method('select')->willReturn($select);

    return new PbiIconUsage($database, $this->registry());
  }

  /**
   * One query row.
   *
   * @param string $icon
   *   Icon name.
   * @param string|null $style
   *   Style, or NULL when the style table is absent.
   *
   * @return object
   *   Row object as the database layer returns it.
   */
  private function row(string $icon, ?string $style = NULL): object {
    $row = new \stdClass();
    $row->icon = $icon;
    if ($style !== NULL) {
      $row->style = $style;
    }

    return $row;
  }

  /**
   * A fresh install has no field table, so there is nothing to subset.
   *
   * The caller treats an empty result as "change nothing", which leaves the
   * bundled font in place. This must not throw — a fatal here would break the
   * install itself.
   */
  public function testMissingFieldTableYieldsNoIcons(): void {
    self::assertSame([], $this->usage(FALSE, FALSE)->iconsByStyle());
  }

  /**
   * A site with the field but no icon content yet also yields nothing.
   */
  public function testNoContentYieldsNoIcons(): void {
    self::assertSame([], $this->usage(TRUE, TRUE, [])->iconsByStyle());
  }

  /**
   * Without the style table every icon still collects, under the default.
   */
  public function testMissingStyleTableFallsBackToTheDefaultStyle(): void {
    $icons = $this->usage(TRUE, FALSE, [$this->row('home'), $this->row('star')])
      ->iconsByStyle();

    self::assertSame(['outlined' => ['home', 'star']], $icons);
  }

  /**
   * Rows group by style, and each group is filtered, de-duplicated and sorted.
   */
  public function testRowsAreGroupedFilteredAndSorted(): void {
    $icons = $this->usage(TRUE, TRUE, [
      $this->row('star', 'outlined'),
      $this->row('grain', 'outlined'),
      $this->row('star', 'outlined'),
      $this->row('settings', 'sharp'),
      $this->row('PB fixture pb_content_gms_icon_name', 'sharp'),
    ])->iconsByStyle();

    self::assertSame(
      ['outlined' => ['grain', 'star'], 'sharp' => ['settings']],
      $icons,
    );
  }

  /**
   * A style that is not one of the three known keys falls back to the default.
   *
   * Storage is not trusted: widget validation does not run for config import,
   * migration or JSON:API writes, and this value picks a font family.
   */
  public function testUnknownStyleFallsBackToTheDefaultStyle(): void {
    $icons = $this->usage(TRUE, TRUE, [$this->row('home', 'squiggly')])
      ->iconsByStyle();

    self::assertSame(['outlined' => ['home']], $icons);
  }

  /**
   * A style whose every icon name is rubbish is omitted, not left empty.
   *
   * An empty list would build a URL with an empty `icon_names`, which the API
   * answers with HTTP 400.
   */
  public function testStyleWithOnlyInvalidNamesIsOmitted(): void {
    $icons = $this->usage(TRUE, TRUE, [$this->row('thisdoesnotexist', 'sharp')])
      ->iconsByStyle();

    self::assertSame([], $icons);
  }

}
