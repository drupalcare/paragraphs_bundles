<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\ParagraphsType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers the 2.0.1 updates that bring v2's words to sites upgraded from v1.
 *
 * Config/optional reaches a site once, at install, so an upgraded site kept
 * the v1 labels "Image Grid" and "Image Grid Section" and six empty field
 * descriptions. paragraph_bundle_image_grid_update_9002,
 * paragraphs_bundles_update_9012 and paragraph_bundle_image_background_update_9003
 * copy the shipped words, and keep anything the site wrote itself.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbShippedTextUpdateTest extends KernelTestBase {

  /**
   * The five Simple section fields v1 shipped without a description.
   */
  private const SIMPLE_FIELDS = [
    'pb_display_border',
    'pb_display_border_radius',
    'pb_display_margin',
    'pb_display_padding',
    'pb_display_shadow',
  ];

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
    'node',
    'entity_reference_revisions',
    'paragraphs',
    'field_group',
    'paragraphs_bundles',
    'paragraph_bundle_image',
    'paragraph_bundle_image_background',
    'paragraph_bundle_image_grid',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    // A PB hook creates media field storages when a bundle appears.
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installConfig(['system', 'field']);

    // The v1 state an upgraded site still has.
    ParagraphsType::create(['id' => 'image_grid_bundle', 'label' => 'Image Grid'])->save();
    ParagraphsType::create(['id' => 'image_grid_section_bundle', 'label' => 'Image Grid Section'])->save();
    ParagraphsType::create(['id' => 'simple_bundle', 'label' => 'Simple'])->save();
    ParagraphsType::create(['id' => 'image_background_bundle', 'label' => 'Image Background'])->save();
    foreach (self::SIMPLE_FIELDS as $fieldName) {
      $this->createV1Field('simple_bundle', $fieldName);
    }
    $this->createV1Field('image_background_bundle', 'pb_display_padding');

    $moduleList = $this->container->get('extension.list.module');
    require_once $this->root . '/' . $moduleList->getPath('paragraphs_bundles') . '/paragraphs_bundles.install';
    require_once $this->root . '/' . $moduleList->getPath('paragraph_bundle_image_grid') . '/paragraph_bundle_image_grid.install';
    require_once $this->root . '/' . $moduleList->getPath('paragraph_bundle_image_background') . '/paragraph_bundle_image_background.install';
  }

  /**
   * The v1 labels become the v2 ones; a site's own name is kept.
   */
  public function testV1LabelsBecomeTheShippedOnes(): void {
    // The site renamed one section itself.
    ParagraphsType::load('image_grid_section_bundle')?->set('label', 'Our photo box')->save();

    $message = paragraph_bundle_image_grid_update_9002();

    self::assertStringContainsString('1 updated', $message);
    self::assertStringContainsString('1 kept', $message);
    self::assertSame($this->shipped('paragraph_bundle_image_grid', 'paragraphs.paragraphs_type.image_grid_bundle', 'label'), ParagraphsType::load('image_grid_bundle')?->label());
    self::assertSame('Lightbox Gallery', ParagraphsType::load('image_grid_bundle')?->label());
    self::assertSame('Our photo box', ParagraphsType::load('image_grid_section_bundle')?->label(), 'A name the site chose is kept.');

    // Idempotent: nothing left to change.
    self::assertStringContainsString('0 updated to the shipped text, 1 already current, 1 kept', paragraph_bundle_image_grid_update_9002());
  }

  /**
   * Empty v1 descriptions get the v2 help text; a site's own text is kept.
   */
  public function testEmptyV1DescriptionsGetTheShippedText(): void {
    // The site wrote its own help for one field.
    FieldConfig::loadByName('paragraph', 'simple_bundle', 'pb_display_shadow')?->setDescription('Our own words.')->save();

    self::assertStringContainsString('4 updated', paragraphs_bundles_update_9012());
    self::assertStringContainsString('1 updated', paragraph_bundle_image_background_update_9003());

    foreach (['pb_display_border', 'pb_display_border_radius', 'pb_display_margin', 'pb_display_padding'] as $fieldName) {
      $expected = $this->shipped('paragraphs_bundles', "field.field.paragraph.simple_bundle.$fieldName", 'description');
      self::assertNotSame('', $expected, "The shipped file for $fieldName carries a description.");
      self::assertSame($expected, FieldConfig::loadByName('paragraph', 'simple_bundle', $fieldName)?->getDescription());
    }
    self::assertSame('Our own words.', FieldConfig::loadByName('paragraph', 'simple_bundle', 'pb_display_shadow')?->getDescription(), 'Help text the site wrote is kept.');
    self::assertSame(
      $this->shipped('paragraph_bundle_image_background', 'field.field.paragraph.image_background_bundle.pb_display_padding', 'description'),
      FieldConfig::loadByName('paragraph', 'image_background_bundle', 'pb_display_padding')?->getDescription(),
    );

    // Idempotent.
    self::assertStringContainsString('0 updated to the shipped text, 4 already current, 1 kept', paragraphs_bundles_update_9012());
    self::assertStringContainsString('0 updated to the shipped text, 1 already current', paragraph_bundle_image_background_update_9003());
  }

  /**
   * Creates a field the way v1 shipped it: no description.
   */
  private function createV1Field(string $bundle, string $fieldName): void {
    if (FieldStorageConfig::loadByName('paragraph', $fieldName) === NULL) {
      FieldStorageConfig::create([
        'field_name' => $fieldName,
        'entity_type' => 'paragraph',
        'type' => 'string',
      ])->save();
    }
    FieldConfig::create([
      'field_name' => $fieldName,
      'entity_type' => 'paragraph',
      'bundle' => $bundle,
      'label' => $fieldName,
      'description' => '',
    ])->save();
  }

  /**
   * Reads one property from the file a module ships in config/optional.
   */
  private function shipped(string $module, string $configName, string $property): string {
    $path = $this->root . '/' . $this->container->get('extension.list.module')->getPath($module) . "/config/optional/$configName.yml";
    $data = Yaml::decode((string) file_get_contents($path));
    self::assertIsArray($data);
    $value = $data[$property] ?? NULL;
    self::assertIsString($value);
    return $value;
  }

}
