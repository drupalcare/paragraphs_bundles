<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_apex\Unit;

use Drupal\Component\FileCache\FileCacheFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ConfigInstallerInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleExtensionList;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The install backfill reports what it created, and never what it did not.
 *
 * 🚨 THE DEFECT THIS GUARDS SHIPPED ONCE, AND IT IS INVISIBLE.
 * The function originally called installOptionalConfig($source, ['config' =>
 * $name]) once per file. That second argument is a FILTER meaning "install
 * optional config that DEPENDS ON this thing", not "install this thing".
 * Nothing depends on those six files, so the call created exactly ZERO config
 * and returned normally — and the update hook then told the site operator it
 * had succeeded. A backfill that creates nothing and reports success is the
 * precise failure this module exists to prevent.
 *
 * WHY A UNIT TEST WHEN A KERNEL TEST ALREADY COVERS THIS. The Kernel test is
 * the better test and it is not the one that runs. This project's
 * phpunit.xml.dist sweeps the Unit directories ONLY — the project root's, and
 * each sub-module's — so the Kernel suite executes in CI and never on a
 * developer's machine. That gap is exactly how the icon-font work nearly
 * shipped a hook that did nothing. This class puts the honesty contract in the
 * suite that runs on every change.
 *
 * ⚠ DO NOT write that sweep pattern as a glob in a docblock. The star-slash
 * inside it CLOSES the comment, the rest of the line becomes PHP, and the file
 * fails to parse — which takes the whole suite down at build time, not just
 * this test. That is exactly what happened when this class was first written.
 *
 * ⚠ IT USES A CONTAINER, WHICH IS NOT THIS PROJECT'S UNIT CONVENTION. The
 * function under test is procedural and reaches the container through
 * \Drupal::service() and \Drupal::config(). There is no seam to inject, so the
 * choice was a container or no coverage. Flagged rather than slipped in.
 *
 * The FileStorage inside the function reads this module's REAL config/optional
 * directory, so the six names under test are the six that actually ship. A
 * file added or renamed there changes what this test sees, deliberately.
 *
 * @group paragraphs_bundles_apex
 */
#[Group('paragraphs_bundles_apex')]
final class PbApexEnsureDisplaysTest extends TestCase {

  /**
   * Config names that "exist" on the imaginary site, as a mutable set.
   *
   * @var array<string, true>
   */
  private array $present = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // ensure_displays() builds a Core FileStorage, whose constructor asks
    // FileCacheFactory for a cache and throws "Required prefix configuration
    // is missing" when none is set. A Kernel test inherits that prefix from
    // the test bootstrap; a pure Unit test does not, so it is set here.
    // Without this the failure surfaces from deep inside Core and reads as a
    // defect in the install file rather than a missing test fixture.
    FileCacheFactory::setPrefix('pb_apex_unit_test');

    // dirname(__DIR__, 3) — __DIR__ is tests/src/Unit, so three levels up is
    // the module root. Four levels lands in modules/ and the require fails
    // with "no such file", which reads as a missing install file rather than
    // a wrong path.
    require_once self::moduleRoot() . '/paragraphs_bundles_apex.install';
  }

  /**
   * Absolute path to this module's root directory.
   *
   * @return string
   *   The module root.
   */
  private static function moduleRoot(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * Builds a container whose config state this test controls.
   *
   * @param bool $installerWorks
   *   TRUE for an installer that genuinely creates the missing config; FALSE
   *   for one that silently creates nothing — the shipped defect.
   */
  private function buildContainer(bool $installerWorks): void {
    $moduleRoot = self::moduleRoot();

    $extensionList = $this->createMock(ModuleExtensionList::class);
    $extensionList->method('getPath')->willReturn($moduleRoot);

    $configFactory = $this->createMock(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturnCallback(
      function (string $name): object {
        $isNew = !isset($this->present[$name]);
        return new class($isNew) {

          /**
           * Minimal stand-in for a config object.
           *
           * @param bool $isNew
           *   Whether this config name is absent from the imaginary site.
           */
          public function __construct(private readonly bool $isNew) {}

          /**
           * Whether the config does not exist yet.
           *
           * @return bool
           *   TRUE when absent.
           */
          public function isNew(): bool {
            return $this->isNew;
          }

        };
      },
    );

    $installer = $this->createMock(ConfigInstallerInterface::class);
    $installer->method('installOptionalConfig')->willReturnCallback(
      function () use ($installerWorks, $moduleRoot): void {
        if (!$installerWorks) {
          return;
        }
        foreach (glob($moduleRoot . '/config/optional/*.yml') ?: [] as $file) {
          $this->present[basename($file, '.yml')] = TRUE;
        }
      },
    );

    $container = new ContainerBuilder();
    $container->set('extension.list.module', $extensionList);
    $container->set('config.factory', $configFactory);
    $container->set('config.installer', $installer);
    \Drupal::setContainer($container);
  }

  /**
   * On a site missing everything, every shipped name is reported created.
   */
  public function testFreshSiteReportsEveryShippedConfig(): void {
    $this->present = [];
    $this->buildContainer(TRUE);

    $created = \paragraphs_bundles_apex_ensure_displays();

    self::assertCount(
      6,
      $created,
      'The six shipped config entities were not all created and reported.',
    );
  }

  /**
   * An installer that creates nothing must report nothing.
   *
   * 🚨 This is the shipped defect, reproduced. Before the fix the function
   * returned the names it had *intended* to create; now it re-reads state and
   * reports only what genuinely arrived. If this assertion ever fails, the
   * update hook is lying to whoever ran `drush updatedb`.
   */
  public function testInstallerCreatingNothingReportsNothing(): void {
    $this->present = [];
    $this->buildContainer(FALSE);

    $created = \paragraphs_bundles_apex_ensure_displays();

    self::assertSame(
      [],
      $created,
      'ensure_displays() claimed to create config while the installer created none.',
    );
  }

  /**
   * A complete site short-circuits: nothing missing, nothing reported.
   *
   * The backfill is reachable from an update hook, and update hooks run on
   * every deploy. One that reinstalled its config each time would silently
   * revert a display the site had customised.
   */
  public function testCompleteSiteCreatesNothing(): void {
    $moduleRoot = self::moduleRoot();
    foreach (glob($moduleRoot . '/config/optional/*.yml') ?: [] as $file) {
      $this->present[basename($file, '.yml')] = TRUE;
    }
    $this->buildContainer(TRUE);

    self::assertSame([], \paragraphs_bundles_apex_ensure_displays());
  }

  /**
   * A partial site reports only the names that were actually missing.
   *
   * The realistic case: a site enabled APEX later and has some of the config
   * already. Reporting the complete set would overstate what the deploy did.
   */
  public function testPartialSiteReportsOnlyWhatWasMissing(): void {
    $moduleRoot = self::moduleRoot();
    $all = array_map(
      static fn (string $f): string => basename($f, '.yml'),
      glob($moduleRoot . '/config/optional/*.yml') ?: [],
    );
    sort($all);

    // Everything except the first two already exists.
    foreach (array_slice($all, 2) as $name) {
      $this->present[$name] = TRUE;
    }
    $this->buildContainer(TRUE);

    $created = \paragraphs_bundles_apex_ensure_displays();

    self::assertCount(2, $created, 'Reported a different number of names than were missing.');
    self::assertSame(array_slice($all, 0, 2), array_values($created));
  }

}
