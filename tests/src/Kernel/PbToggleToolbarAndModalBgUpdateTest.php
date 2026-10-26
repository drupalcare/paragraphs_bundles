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
 * Covers two post_updates: the toggle "toolbar" position and the modal panel.
 *
 * - paragraph_bundle_layout_post_update_toggle_toolbar_position() adds the
 *   `toolbar` value to both icon-position storages, even with data stored.
 * - paragraph_bundle_modal_post_update_panel_bg_optional() makes the modal
 *   Background Color optional with no default and keeps stored colours.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbToggleToolbarAndModalBgUpdateTest extends KernelTestBase {

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

    $root = dirname(__DIR__, 3) . '/modules';
    require_once $root . '/paragraph_bundle_layout/paragraph_bundle_layout.post_update.php';
    require_once $root . '/paragraph_bundle_modal/paragraph_bundle_modal.post_update.php';
  }

  /**
   * The toolbar value is added to both storages; stored values stay.
   */
  public function testToolbarPositionIsAdded(): void {
    $old = ['edge_top' => 'Edge top', 'edge' => 'Edge center', 'inside_center' => 'Inside center'];
    ParagraphsType::create(['id' => 'two_columns_bundle', 'label' => 'Two Columns'])->save();
    foreach (['pb_display_2c_icn_pos', 'pb_display_3c_icn_pos'] as $name) {
      FieldStorageConfig::create([
        'field_name' => $name,
        'entity_type' => 'paragraph',
        'type' => 'list_string',
        'settings' => ['allowed_values' => $old],
      ])->save();
    }
    FieldConfig::create([
      'field_name' => 'pb_display_2c_icn_pos',
      'entity_type' => 'paragraph',
      'bundle' => 'two_columns_bundle',
      'label' => 'Icon position',
    ])->save();
    // Data in the storage: adding a value must still be allowed.
    $stored = Paragraph::create(['type' => 'two_columns_bundle', 'pb_display_2c_icn_pos' => 'edge_top']);
    $stored->save();

    $message = paragraph_bundle_layout_post_update_toggle_toolbar_position();

    self::assertStringContainsString('pb_display_2c_icn_pos, pb_display_3c_icn_pos', $message);
    foreach (['pb_display_2c_icn_pos', 'pb_display_3c_icn_pos'] as $name) {
      $values = FieldStorageConfig::loadByName('paragraph', $name)?->getSetting('allowed_values');
      self::assertSame($old + ['toolbar' => 'Toolbar above the columns'], $values, $name);
    }
    $reloaded = $this->container->get('entity_type.manager')->getStorage('paragraph')->loadUnchanged($stored->id());
    self::assertSame('edge_top', $reloaded?->get('pb_display_2c_icn_pos')->value);

    // The new value saves.
    $toolbar = Paragraph::create(['type' => 'two_columns_bundle', 'pb_display_2c_icn_pos' => 'toolbar']);
    self::assertCount(0, $toolbar->validate());

    // Idempotent.
    self::assertStringContainsString('nothing to do', paragraph_bundle_layout_post_update_toggle_toolbar_position());
  }

  /**
   * The modal colour becomes optional, without a default; stored stays.
   */
  public function testModalPanelBgBecomesOptional(): void {
    ParagraphsType::create(['id' => 'modal_bundle', 'label' => 'Modal'])->save();
    FieldStorageConfig::create([
      'field_name' => 'pb_display_bg',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'pb_display_bg',
      'entity_type' => 'paragraph',
      'bundle' => 'modal_bundle',
      'label' => 'Background Color',
      'required' => TRUE,
      'default_value' => [['value' => '#000000']],
    ])->save();
    $kept = Paragraph::create(['type' => 'modal_bundle']);
    $kept->save();
    self::assertSame('#000000', $kept->get('pb_display_bg')->value, 'The fixture reproduces the old default.');

    $message = paragraph_bundle_modal_post_update_panel_bg_optional();

    self::assertStringContainsString('now optional', $message);
    $field = FieldConfig::loadByName('paragraph', 'modal_bundle', 'pb_display_bg');
    self::assertFalse($field?->isRequired());
    self::assertSame([], $field?->getDefaultValueLiteral());

    $storage = $this->container->get('entity_type.manager')->getStorage('paragraph');
    self::assertSame('#000000', $storage->loadUnchanged($kept->id())?->get('pb_display_bg')->value, 'No content rewrite.');
    $new = Paragraph::create(['type' => 'modal_bundle']);
    self::assertTrue($new->get('pb_display_bg')->isEmpty());
    self::assertCount(0, $new->validate());

    // Idempotent.
    self::assertStringContainsString('nothing to do', paragraph_bundle_modal_post_update_panel_bg_optional());
  }

}
