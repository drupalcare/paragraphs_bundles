<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\ParagraphsType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The custom field types' default values pass strict config schema.
 *
 * Paragraphs_bundles_rgb and paragraphs_bundles_range ship fields with a
 * default_value; Drupal validates each default_value item against
 * field.value.{field_type}. This test creates such a field and saves it under
 * KernelTestBase's strict config-schema checker, so a missing/mis-keyed schema
 * fails here rather than only in a full install (which is how it first slipped
 * through — enableModules does not create these fields).
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbFieldSchemaTest extends KernelTestBase {

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
    'options',
    'link',
    'entity_reference_revisions',
    'paragraphs',
    'paragraphs_bundles',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // The test creates paragraph field storages below. Creating a field storage
    // before its entity schema exists is deprecated in 11.2 and becomes a
    // LogicException in 12.0, so install the schema up front.
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    ParagraphsType::create(['id' => 'test_bundle', 'label' => 'Test'])->save();
  }

  /**
   * Saving a custom-type field with a default value validates against schema.
   */
  public function testCustomFieldTypeDefaultValueSchema(): void {
    foreach (['paragraphs_bundles_rgb', 'paragraphs_bundles_range'] as $type) {
      $field_name = 'pb_test_' . substr(md5($type), 0, 8);

      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'paragraph',
        'type' => $type,
      ])->save();

      // Saving with a default_value is what exercises field.value.{type};
      // strict config schema throws here if the schema is missing/mis-keyed.
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'paragraph',
        'bundle' => 'test_bundle',
        'default_value' => [['value' => '100']],
      ])->save();

      $this->assertNotNull(
        FieldConfig::load('paragraph.test_bundle.' . $field_name),
        sprintf('Field of type %s saved with a schema-valid default value.', $type),
      );
    }
  }

}
