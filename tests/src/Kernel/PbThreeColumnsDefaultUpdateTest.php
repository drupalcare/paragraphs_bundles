<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers paragraph_bundle_layout_update_9002: the three-columns default.
 *
 * The field shipped `l4-l4-l4` while its keys are `l4_l4_l4`; a section made
 * without the form stored the bad value. The update corrects the default and
 * the stored values, and a second run changes nothing.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbThreeColumnsDefaultUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'options',
    'file',
    'entity_reference_revisions',
    'paragraphs',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['system', 'field']);

    ParagraphsType::create(['id' => 'three_columns_bundle', 'label' => 'Three Columns'])->save();
    FieldStorageConfig::create([
      'field_name' => 'pb_display_three_columns',
      'entity_type' => 'paragraph',
      'type' => 'list_string',
      'settings' => ['allowed_values' => ['l4_l4_l4' => '1/3 - 1/3 - 1/3', 'l2_l8_l2' => '1/6 - 2/3 - 1/6']],
    ])->save();
    FieldConfig::create([
      'field_name' => 'pb_display_three_columns',
      'entity_type' => 'paragraph',
      'bundle' => 'three_columns_bundle',
      'label' => 'Layout Style',
      'default_value' => [['value' => 'l4-l4-l4']],
    ])->save();

    require_once dirname(__DIR__, 3) . '/modules/paragraph_bundle_layout/paragraph_bundle_layout.install';
  }

  /**
   * The default and the stored value are corrected; a chosen value is not.
   */
  public function testTheBrokenDefaultAndStoredValuesAreCorrected(): void {
    // Created without the form, the way the AI assistant and an import do.
    $broken = Paragraph::create(['type' => 'three_columns_bundle']);
    $broken->save();
    self::assertSame('l4-l4-l4', $broken->get('pb_display_three_columns')->value, 'The fixture reproduces the defect.');
    $chosen = Paragraph::create(['type' => 'three_columns_bundle', 'pb_display_three_columns' => 'l2_l8_l2']);
    $chosen->save();

    $message = paragraph_bundle_layout_update_9002();

    self::assertStringContainsString('corrected to l4_l4_l4', $message);
    self::assertSame(
      'l4_l4_l4',
      FieldConfig::loadByName('paragraph', 'three_columns_bundle', 'pb_display_three_columns')?->getDefaultValueLiteral()[0]['value'],
    );

    $storage = $this->container->get('entity_type.manager')->getStorage('paragraph');
    $storage->resetCache();
    self::assertSame('l4_l4_l4', $storage->load($broken->id())?->get('pb_display_three_columns')->value);
    self::assertSame('l2_l8_l2', $storage->load($chosen->id())?->get('pb_display_three_columns')->value, 'A layout somebody chose is left alone.');

    // Idempotent: nothing left to correct.
    self::assertStringContainsString('already valid. Stored layouts corrected: 0 row(s).', paragraph_bundle_layout_update_9002());
  }

}
