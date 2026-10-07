<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers paragraph_bundle_layout_update_9003: issue #3629036.
 *
 * A column section made before 1.0.17 has no toggle animation effect, and the
 * field is required, so its edit form will not save. The update writes
 * `slide` — what an empty value already renders as — on every revision that
 * lacks a value, in batches, without touching a value somebody chose.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbAnimBackfillUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'options',
    'file',
    'language',
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
    $this->installConfig(['system', 'field', 'language']);
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $fields = ['two_columns_bundle' => 'pb_display_2c_anim', 'three_columns_bundle' => 'pb_display_3c_anim'];
    foreach ($fields as $bundle => $fieldName) {
      ParagraphsType::create(['id' => $bundle, 'label' => $bundle])->save();
      FieldStorageConfig::create([
        'field_name' => $fieldName,
        'entity_type' => 'paragraph',
        'type' => 'list_string',
        'settings' => ['allowed_values' => ['slide' => 'Slide', 'fade' => 'Fade']],
      ])->save();
      // As shipped since 1.0.17: required, untranslatable, default `slide`.
      FieldConfig::create([
        'field_name' => $fieldName,
        'entity_type' => 'paragraph',
        'bundle' => $bundle,
        'label' => 'Toggle Animation Effect',
        'required' => TRUE,
        'translatable' => FALSE,
        'default_value' => [['value' => 'slide']],
      ])->save();
    }

    require_once dirname(__DIR__, 3) . '/modules/paragraph_bundle_layout/paragraph_bundle_layout.install';
  }

  /**
   * Every revision and translation gets `slide`; a chosen value is kept.
   */
  public function testMissingValuesAreFilledOnEveryRevision(): void {
    // Three revisions, then a French translation, then the rows deleted: the
    // shape of a section made before 1.0.17 added the field.
    $old = $this->createSection('two_columns_bundle', 'fade', 3);
    $old->addTranslation('fr');
    $old->save();
    $this->forgetValues('pb_display_2c_anim', (int) $old->id());
    $oldThree = $this->createSection('three_columns_bundle', 'fade', 2);
    $this->forgetValues('pb_display_3c_anim', (int) $oldThree->id());
    // Made after 1.0.17, with an effect somebody chose.
    $chosen = $this->createSection('two_columns_bundle', 'fade', 2);

    $storage = $this->paragraphStorage();
    $storage->resetCache();
    $broken = $storage->load($old->id());
    self::assertInstanceOf(ParagraphInterface::class, $broken);
    self::assertTrue($broken->get('pb_display_2c_anim')->isEmpty(), 'The fixture reproduces a pre-1.0.17 section.');
    self::assertGreaterThan(0, $broken->validate()->count(), 'The fixture reproduces the edit form that will not save.');

    $sandbox = ['batch_size' => 1];
    $passes = 0;
    do {
      $message = paragraph_bundle_layout_update_9003($sandbox);
      $passes++;
    } while ($sandbox['#finished'] < 1 && $passes < 10);

    self::assertGreaterThan(1, $passes, 'One section per pass needs more than one pass: the batch is real.');
    self::assertStringContainsString('two_columns_bundle: 4 row(s) set to slide.', $message, 'Three revisions plus the current row.');
    self::assertStringContainsString('three_columns_bundle: 3 row(s) set to slide.', $message);

    $storage->resetCache();
    $fixed = $storage->load($old->id());
    self::assertInstanceOf(ParagraphInterface::class, $fixed);
    self::assertSame('slide', $fixed->get('pb_display_2c_anim')->value);
    self::assertSame('slide', $fixed->getTranslation('fr')->get('pb_display_2c_anim')->value, 'The translation reads the one untranslatable value.');
    self::assertCount(0, $fixed->validate(), 'The section saves again.');
    $revisionIds = $this->container->get('database')->select('paragraphs_item_revision', 'r')
      ->fields('r', ['revision_id'])
      ->condition('r.id', $old->id())
      ->execute()?->fetchCol() ?? [];
    self::assertCount(3, $revisionIds);
    foreach ($revisionIds as $revisionId) {
      $revision = $storage->loadRevision($revisionId);
      self::assertInstanceOf(ParagraphInterface::class, $revision);
      self::assertSame('slide', $revision->get('pb_display_2c_anim')->value, "Revision $revisionId is filled, so a revert stays valid.");
    }
    $chosenNow = $storage->load($chosen->id());
    self::assertInstanceOf(ParagraphInterface::class, $chosenNow);
    self::assertSame('fade', $chosenNow->get('pb_display_2c_anim')->value, 'An effect somebody chose is left alone.');
    self::assertSame(2, $this->countRows('paragraph_revision__pb_display_2c_anim', (int) $chosen->id()), 'No row is added beside a chosen one.');

    // Idempotent: a second run finds nothing missing.
    $again = [];
    self::assertStringContainsString('two_columns_bundle: 0 row(s) set to slide. three_columns_bundle: 0 row(s)', paragraph_bundle_layout_update_9003($again));
  }

  /**
   * A site that made the field translatable gets a row per language.
   */
  public function testTranslatableFieldIsFilledPerLanguage(): void {
    $field = FieldConfig::loadByName('paragraph', 'two_columns_bundle', 'pb_display_2c_anim');
    self::assertInstanceOf(FieldConfig::class, $field);
    $field->setTranslatable(TRUE)->save();
    $section = $this->createSection('two_columns_bundle', 'fade', 1);
    $section->addTranslation('fr', ['pb_display_2c_anim' => 'fade']);
    $section->save();
    $this->forgetValues('pb_display_2c_anim', (int) $section->id());

    $sandbox = [];
    $message = paragraph_bundle_layout_update_9003($sandbox);

    // English and French, in the data table and the revision table.
    self::assertStringContainsString('two_columns_bundle: 4 row(s) set to slide.', $message);
    $storage = $this->paragraphStorage();
    $storage->resetCache();
    $fixed = $storage->load($section->id());
    self::assertInstanceOf(ParagraphInterface::class, $fixed);
    self::assertSame('slide', $fixed->get('pb_display_2c_anim')->value);
    self::assertSame('slide', $fixed->getTranslation('fr')->get('pb_display_2c_anim')->value);
  }

  /**
   * A site that took `slide` out of the options gets nothing written.
   */
  public function testSkipsSiteWithoutSlideOption(): void {
    $storage = FieldStorageConfig::loadByName('paragraph', 'pb_display_3c_anim');
    self::assertInstanceOf(FieldStorageConfig::class, $storage);
    $storage->setSetting('allowed_values', ['fade' => 'Fade'])->save();
    $section = $this->createSection('three_columns_bundle', 'fade', 1);
    $this->forgetValues('pb_display_3c_anim', (int) $section->id());

    $sandbox = [];
    $message = paragraph_bundle_layout_update_9003($sandbox);

    self::assertStringContainsString('three_columns_bundle: skipped', $message);
    self::assertSame(0, $this->countRows('paragraph_revision__pb_display_3c_anim', (int) $section->id()));
  }

  /**
   * Creates a section and gives it extra revisions.
   */
  private function createSection(string $bundle, string $effect, int $revisions): ParagraphInterface {
    $field = $bundle === 'two_columns_bundle' ? 'pb_display_2c_anim' : 'pb_display_3c_anim';
    $section = Paragraph::create(['type' => $bundle, 'langcode' => 'en', $field => $effect]);
    $section->save();
    for ($i = 1; $i < $revisions; $i++) {
      $section->setNewRevision(TRUE);
      $section->save();
    }
    return $section;
  }

  /**
   * Deletes every stored value of a field for one section, all revisions.
   */
  private function forgetValues(string $field, int $id): void {
    $database = $this->container->get('database');
    $database->delete('paragraph__' . $field)->condition('entity_id', $id)->execute();
    $database->delete('paragraph_revision__' . $field)->condition('entity_id', $id)->execute();
  }

  /**
   * Counts the stored rows of one section in a field table.
   */
  private function countRows(string $table, int $id): int {
    return (int) $this->container->get('database')->select($table, 't')
      ->condition('t.entity_id', $id)
      ->countQuery()->execute()?->fetchField();
  }

  /**
   * The paragraph storage, narrowed to the revisionable storage it is.
   */
  private function paragraphStorage(): RevisionableStorageInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('paragraph');
    self::assertInstanceOf(RevisionableStorageInterface::class, $storage);
    return $storage;
  }

}
