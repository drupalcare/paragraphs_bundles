<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The content module creates a node type hosting a paragraphs field.
 *
 * Paragraph_bundle_content ships node.type.pb_content, whose config depends on
 * menu_ui; optional config is only imported when every dependency is present.
 * This reproduces the Functional render host locally so the required module set
 * (menu_ui/block/block_content) is verified without a full browser install.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbNodeHostTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'file',
    'image',
    'link',
    'options',
    'views',
    'media',
    'media_library',
    'block',
    'block_content',
    'menu_ui',
    'node',
    'entity_reference_revisions',
    'paragraphs',
    'field_group',
    'paragraphs_bundles',
    'paragraph_bundle_content',
    'paragraph_bundle_hero',
  ];

  /**
   * Optional config creates a node type with a paragraphs reference field.
   */
  public function testParagraphHostNodeTypeExists(): void {
    $this->container->get('config.installer')->installOptionalConfig();

    // Mirror PbBundleRenderTest::discoverParagraphHost().
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

    $this->assertNotNull(
      $host,
      'A node type with an entity_reference_revisions field to paragraphs exists.',
    );
  }

}
