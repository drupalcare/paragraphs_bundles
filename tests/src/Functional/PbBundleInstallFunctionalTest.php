<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The full bundle family installs cleanly on a real site.
 *
 * Exercises a full (BrowserTestBase) install of the content module plus a broad
 * set of bundle modules — hook_install runs and optional config is imported and
 * schema-validated. This is the end-to-end install gate; per-bundle render
 * output is covered deterministically by the Kernel PbBundleRenderKernelTest,
 * which avoids environment-specific HTTP auth/node-access behaviour.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbBundleInstallFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'node',
    'menu_ui',
    'block',
    'block_content',
    'paragraphs_bundles',
    'paragraph_bundle_content',
    'paragraph_bundle_image',
    'paragraph_bundle_hero',
    'paragraph_bundle_layout',
    'paragraph_bundle_card',
    'paragraph_bundle_accordion',
    'paragraph_bundle_alert',
    'paragraph_bundle_tabs',
    'paragraph_bundle_icon',
  ];

  /**
   * Installing the bundle family creates paragraph types.
   */
  public function testInstallProducesParagraphTypes(): void {
    $types = $this->container->get('entity_type.manager')
      ->getStorage('paragraphs_type')
      ->loadMultiple();
    $this->assertNotEmpty($types, 'Install created paragraph types.');
  }

  /**
   * A fresh install with no icon content keeps the bundled font.
   *
   * `paragraph_bundle_icon` repoints its stylesheet at a Google Fonts subset of
   * the icons a site actually uses. A fresh install uses none, so there is
   * nothing to subset and the local `@font-face` must be left exactly as
   * shipped. Getting this wrong would point every new site at a stylesheet for
   * an empty icon list — which the API answers with HTTP 400 — and the visible
   * symptom would be icons rendering as their ligature NAMES in plain text,
   * the same failure that forced the revert in 4ea0ce5.
   *
   * This can only be checked on a real install: `hook_library_info_alter()`
   * fires on the first library-cache build, potentially before the module's
   * optional field config has been imported, so the field tables may not exist
   * yet. The Unit suite covers the guards in isolation
   * (`PbiIconUsageTest`); this covers the ordering.
   */
  public function testFreshInstallKeepsTheBundledIconFont(): void {
    $library = $this->container->get('library.discovery')
      ->getLibraryByName('paragraph_bundle_icon', 'paragraph-bundle-icon-outlined');

    $this->assertIsArray($library, 'The icon library exists after install.');
    $this->assertNotEmpty($library['css'], 'The icon library still declares a stylesheet.');

    foreach ($library['css'] as $asset) {
      $this->assertSame(
        'file',
        $asset['type'],
        'A fresh install uses no icons, so the stylesheet must still be the '
        . 'bundled local file rather than a subset URL.',
      );
      $this->assertStringEndsWith('material-symbols-outlined.css', $asset['data']);
    }
  }

  /**
   * The icon services work immediately after install, with no content.
   */
  public function testFreshInstallRegistersTheIconServices(): void {
    $usage = $this->container->get('paragraph_bundle_icon.icon_usage');
    $registry = $this->container->get('paragraph_bundle_icon.icon_name_registry');

    // Must not throw when no icon paragraph exists yet.
    $this->assertSame([], $usage->iconsByStyle());

    // The shipped name list is readable from the installed module, which is
    // what makes free-text icon names validatable at all.
    $this->assertGreaterThan(4000, count($registry->all()));
    $this->assertTrue($registry->isValid('home'));
    $this->assertFalse($registry->isValid('thisdoesnotexist'));
  }

  /**
   * Installing the content module creates the host node type.
   */
  public function testInstallCreatesHostNodeType(): void {
    $bundle_info = $this->container->get('entity_type.bundle.info')
      ->getBundleInfo('node');
    $field_manager = $this->container->get('entity_field.manager');
    $host = NULL;
    foreach (array_keys($bundle_info) as $node_bundle) {
      foreach ($field_manager->getFieldDefinitions('node', $node_bundle) as $def) {
        if ($def->getType() === 'entity_reference_revisions'
          && $def->getSetting('target_type') === 'paragraph') {
          $host = $node_bundle;
          break 2;
        }
      }
    }
    $this->assertNotNull($host, 'A paragraphs host node type was created.');
  }

}
