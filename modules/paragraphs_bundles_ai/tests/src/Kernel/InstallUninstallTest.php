<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Kernel;

use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Tests\paragraphs_bundles_ai\Traits\AiCoreSupportTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Installs, uninstalls and reinstalls the module through the real installer.
 *
 * Maintainer, 2026-09-27: *"you must test any config in install will not cause
 * any errors, dont just create it with out testing"*. Until this test the
 * module's own config — the briefs and the presets in config/install — had
 * never been through an install or an uninstall anywhere: every other test
 * writes config into a harness directly.
 *
 * Checked on each pass: the installer throws nothing, every shipped config
 * passes its schema, uninstall removes the module's own config (core deletes
 * `paragraphs_bundles_ai.*`), the AI image bundle stays like every other PB
 * bundle, and a second install does not trip over what the first one left.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class InstallUninstallTest extends KernelTestBase {
  use AiCoreSupportTrait;

  use SchemaCheckTrait;

  /**
   * Only what the installer cannot pull in by itself.
   *
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * The module's own config, as shipped in config/install.
   */
  private const OWN = ['paragraphs_bundles_ai.briefs', 'paragraphs_bundles_ai.presets'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->skipUnlessCoreSupportsAi();
    parent::setUp();
    $this->installEntitySchema('user');
    // Core's uninstall clears user data for the module, so the table must exist.
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system']);
  }

  /**
   * Install, uninstall, reinstall: no error, valid config, nothing stranded.
   */
  public function testInstallUninstallReinstall(): void {
    $installer = $this->container->get('module_installer');

    // Dependencies come with it, as they would on a site.
    self::assertTrue($installer->install(['paragraphs_bundles_ai']));
    $this->assertOwnConfigIsInstalledAndValid();
    self::assertNotNull(ParagraphsType::load('ai_image_bundle'), 'Optional config installed with its dependencies met.');

    $installer = $this->container->get('module_installer');
    $installer->uninstall(['paragraphs_bundles_ai']);

    $config = $this->container->get('config.factory');
    foreach (self::OWN as $name) {
      self::assertTrue($config->get($name)->isNew(), "$name is removed on uninstall.");
    }
    self::assertNotNull(ParagraphsType::load('ai_image_bundle'), 'The bundle stays, like every other PB bundle.');

    // Again: the leftover bundle must not block a second install.
    $installer = $this->container->get('module_installer');
    self::assertTrue($installer->install(['paragraphs_bundles_ai']));
    $this->assertOwnConfigIsInstalledAndValid();
  }

  /**
   * The module's own config exists, has content, and passes its schema.
   */
  private function assertOwnConfigIsInstalledAndValid(): void {
    $storage = $this->container->get('config.storage');
    $typed = $this->container->get('config.typed');

    foreach (self::OWN as $name) {
      $data = $storage->read($name);
      self::assertIsArray($data, "$name is installed.");
      self::assertTrue($this->checkConfigSchema($typed, $name, $data), "$name passes its schema.");
    }

    $briefs = $this->container->get('paragraphs_bundles_ai.briefs');
    self::assertCount(3, $briefs->forBundle('slideshow_bundle')['prompts'] ?? []);
    self::assertNotSame([], $briefs->featuresFor('slideshow_bundle'));
    self::assertNotSame([], $briefs->nestingFor('two_columns_bundle'));
  }

}
