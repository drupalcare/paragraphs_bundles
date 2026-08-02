<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Every installed paragraph bundle renders with the shared PB wrapper.
 *
 * Renders through the entity view builder rather than an HTTP request, so it
 * exercises real template output deterministically without BrowserTestBase
 * session/auth setup. Every PB paragraph template emits the shared
 * `paragraphs-bundles` class, so each rendered bundle must contain it. Enables
 * the full bundle family whose dependencies ship in the test harness (the four
 * bundles needing external contrib — link/views/webform/contact_form — are
 * excluded). Also confirms the content module creates a paragraphs host node.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbBundleRenderKernelTest extends KernelTestBase {

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
    // Bundle family whose deps ship in the harness (excludes link/views/
    // webform/contact_form, which need external contrib modules).
    'paragraph_bundle_content',
    'paragraph_bundle_image',
    'paragraph_bundle_hero',
    'paragraph_bundle_layout',
    'paragraph_bundle_grid',
    'paragraph_bundle_card',
    'paragraph_bundle_accordion',
    'paragraph_bundle_tabs',
    'paragraph_bundle_alert',
    'paragraph_bundle_icon',
    'paragraph_bundle_modal',
    'paragraph_bundle_block',
    'paragraph_bundle_block_content',
    'paragraph_bundle_3d_carousel',
    'paragraph_bundle_3d_flip_box',
    'paragraph_bundle_carousel',
    'paragraph_bundle_image_background',
    'paragraph_bundle_image_grid',
    'paragraph_bundle_image_overlay',
    'paragraph_bundle_node_reference',
    'paragraph_bundle_parallax',
    'paragraph_bundle_slideshow',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('media');
    // Date formats (for timestamp rendering) live in system config.
    $this->installConfig(['system']);
    $this->container->get('config.installer')->installOptionalConfig();
  }

  /**
   * The content module creates a node type that hosts paragraphs.
   */
  public function testParagraphHostNodeTypeExists(): void {
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
    $this->assertNotNull($host, 'A paragraphs host node type exists.');
  }

  /**
   * Every installed bundle renders the shared wrapper class.
   */
  public function testEveryBundleRendersSharedWrapper(): void {
    $type_ids = array_keys(
      $this->container->get('entity_type.manager')
        ->getStorage('paragraphs_type')
        ->loadMultiple(),
    );
    // The enabled family should produce well over a dozen bundles.
    $this->assertGreaterThan(10, count($type_ids));

    $view_builder = $this->container->get('entity_type.manager')
      ->getViewBuilder('paragraph');
    $renderer = $this->container->get('renderer');

    foreach ($type_ids as $type_id) {
      $paragraph = Paragraph::create(['type' => $type_id]);
      $paragraph->save();

      $build = $view_builder->view($paragraph);
      $html = (string) $renderer->renderRoot($build);

      $this->assertStringContainsString(
        'paragraphs-bundles',
        $html,
        sprintf('Bundle %s renders the shared wrapper class.', $type_id),
      );
    }
  }

}
