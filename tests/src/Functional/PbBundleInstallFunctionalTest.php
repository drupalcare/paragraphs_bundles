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
