<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Imports the module's optional config under strict config schema.
 *
 * EnableModules() does not import config/optional, which is why several schema
 * errors only surfaced in a full (Functional) install. This test enables the
 * content-safe bundle family and then explicitly installs optional config, so
 * every shipped field/display config is schema-validated here and all such
 * errors are found at once rather than one Functional run at a time.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbOptionalConfigSchemaTest extends KernelTestBase {

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
    'paragraph_bundle_image',
    'paragraph_bundle_hero',
    'paragraph_bundle_layout',
    'paragraph_bundle_card',
    'paragraph_bundle_accordion',
    'paragraph_bundle_tabs',
    'paragraph_bundle_alert',
    'paragraph_bundle_icon',
    'paragraph_bundle_link',
    'paragraph_bundle_modal',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Optional config creates field storages on these entity types. Creating a
    // field storage before its entity schema exists is deprecated in 11.2 and
    // becomes a LogicException in 12.0, so install the schemas up front.
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('media');
  }

  /**
   * Optional config imports with schema-valid data.
   */
  public function testOptionalConfigSchema(): void {
    // Strict config-schema checking (KernelTestBase default) validates each
    // config entity as it is saved during optional-config install.
    $this->container->get('config.installer')->installOptionalConfig();

    // If we get here, every imported config passed schema. Confirm the install
    // actually produced the field configs it ships.
    $this->assertNotEmpty(
      $this->container->get('entity_type.manager')
        ->getStorage('paragraphs_type')
        ->loadMultiple(),
    );
  }

}
