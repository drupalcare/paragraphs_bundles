<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraph_bundle_3d_carousel\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Which carousels update 9003's backfill reaches on a multilingual site.
 *
 * Update 9003 is released and is never edited. This class measures what it
 * covers on the two shapes its own test did not build: translated carousels
 * with several revisions, and more carousels than one 50-item chunk.
 *
 * WHAT IT ESTABLISHES (measured 2026-10-07): nothing is missed. The shipped
 * field is UNTRANSLATABLE (`translatable: false` on the field config), and
 * core stores an untranslatable field ONLY under the entity's default
 * langcode — SqlContentEntityStorage::saveToDedicatedTables() picks
 * `[$default_langcode]` when the definition is not translatable — while a
 * translation reads the default language's value. One row per revision in
 * the default langcode is therefore exactly what core itself writes, and it
 * is what 9003 writes. So no follow-up update hook is added.
 *
 * If any assertion here goes red, that conclusion no longer holds and the
 * follow-up is owed after all.
 *
 * @group paragraphs_bundles
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class Pb3dcStyleBackfillCoverageTest extends KernelTestBase {

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
    'node',
    'file',
    'options',
    'language',
    'entity_reference_revisions',
    'paragraphs',
  ];

  /**
   * The bundle the field belongs to.
   */
  private const BUNDLE = '3d_carousel_bundle';

  /**
   * The field under test.
   */
  private const FIELD = 'pb_display_3d_car_style';

  /**
   * The node field the carousels hang from.
   */
  private const HOST_FIELD = 'field_sections';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'field', 'filter', 'node', 'language']);

    ConfigurableLanguage::createFromLangcode('fr')->save();

    ParagraphsType::create(['id' => self::BUNDLE, 'label' => '3D carousel'])->save();

    // Built by hand to the shipped shape — KernelTestBase does not import
    // config/optional. See Pb3dcStyleBackfillTest::setUp() for why the
    // allowed values are a flat map.
    FieldStorageConfig::create([
      'field_name' => self::FIELD,
      'entity_type' => 'paragraph',
      'type' => 'list_string',
      'cardinality' => 1,
      // As shipped: the storage says translatable, the instance does not, and
      // the instance is what decides.
      'translatable' => TRUE,
      'settings' => [
        'allowed_values' => [
          'ring' => 'Rotating ring (3D drum)',
          'coverflow' => 'Coverflow (angled cards)',
        ],
        'allowed_values_function' => '',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => self::FIELD,
      'entity_type' => 'paragraph',
      'bundle' => self::BUNDLE,
      'label' => 'Carousel style',
      'required' => FALSE,
      // As shipped in field.field.paragraph.3d_carousel_bundle.*.yml.
      'translatable' => FALSE,
    ])->save();

    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();

    FieldStorageConfig::create([
      'field_name' => self::HOST_FIELD,
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'cardinality' => -1,
      'settings' => ['target_type' => 'paragraph'],
    ])->save();

    FieldConfig::create([
      'field_name' => self::HOST_FIELD,
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Sections',
      // The Paragraphs-recommended multilingual shape: the reference field is
      // shared, and each paragraph carries its own translations.
      'translatable' => FALSE,
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => ['target_bundles' => []],
      ],
    ])->save();

    // Required directly: the module is discovered, not installed. See
    // Pb3dcStyleBackfillTest::setUp().
    $path = \Drupal::service('extension.list.module')
      ->getPath('paragraph_bundle_3d_carousel');
    require_once \Drupal::root() . '/' . $path . '/paragraph_bundle_3d_carousel.install';

    self::assertTrue(
      function_exists('paragraph_bundle_3d_carousel_backfill_style'),
      'The install file did not load.',
    );
  }

  /**
   * A translated carousel reads 'ring' on every revision, in both languages.
   */
  public function testTranslatedCarouselIsCoveredOnEveryRevision(): void {
    $paragraph = Paragraph::create(['type' => self::BUNDLE, 'langcode' => 'en']);
    $paragraph->save();
    $paragraph->addTranslation('fr')->save();

    $node = Node::create([
      'type' => 'page',
      'title' => 'English page',
      'langcode' => 'en',
      'status' => 1,
      self::HOST_FIELD => [
        [
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ],
      ],
    ]);
    $node->save();
    $node->addTranslation('fr', ['title' => 'Page française'])->save();

    // Two more revisions, one edited from each language.
    foreach (['en', 'fr'] as $langcode) {
      $fresh = $this->reloadNode($node)->getTranslation($langcode);
      $fresh->setNewRevision(TRUE);
      $fresh->setTitle($fresh->getTitle() . ' (edited)');
      $child = $fresh->get(self::HOST_FIELD)->entity;
      self::assertInstanceOf(Paragraph::class, $child);
      $child->setNeedsSave(TRUE);
      $fresh->save();
    }

    $id = (int) $paragraph->id();
    $revisions = $this->revisionIdsOf($id);

    // CONTROLS: the fixture has the shape this class exists to measure, and
    // nothing holds a value yet.
    self::assertGreaterThanOrEqual(3, count($revisions), 'The fixture did not build several paragraph revisions.');
    self::assertTrue($this->loadParagraphRevision(max($revisions))->hasTranslation('fr'), 'The carousel has no French translation, so this measures nothing about translations.');
    foreach ($revisions as $revision_id) {
      self::assertNull($this->styleAt($revision_id, 'en'), 'A revision already held a style before the backfill.');
    }

    self::assertSame(1, \paragraph_bundle_3d_carousel_backfill_style());

    foreach ($revisions as $revision_id) {
      $revision = $this->loadParagraphRevision($revision_id);
      foreach (array_keys($revision->getTranslationLanguages()) as $langcode) {
        self::assertSame('ring', $this->styleAt($revision_id, (string) $langcode), sprintf('Revision %d in %s was missed by the backfill.', $revision_id, $langcode));
      }
    }

    // The entity query joins one data row per translation. If a French row
    // were owed, it would re-select the carousel here.
    self::assertSame(0, \paragraph_bundle_3d_carousel_backfill_style(), 'The entity query still sees the translated carousel as having no style.');
  }

  /**
   * The rows 9003 writes are the rows core writes for this field.
   *
   * Compared against a translated carousel saved through the entity API with
   * a value, which is the authority on what this field's rows look like.
   */
  public function testBackfilledRowsMatchWhatCoreWrites(): void {
    $backfilled = Paragraph::create(['type' => self::BUNDLE, 'langcode' => 'en']);
    $backfilled->save();
    $backfilled->addTranslation('fr')->save();

    $saved = Paragraph::create(['type' => self::BUNDLE, 'langcode' => 'en', self::FIELD => 'ring']);
    $saved->save();
    $saved->addTranslation('fr')->save();

    self::assertSame(1, \paragraph_bundle_3d_carousel_backfill_style());

    $by_core = $this->rowShape((int) $saved->id());
    // CONTROL: core wrote something, or comparing to it proves nothing.
    self::assertNotSame([], $by_core['data'], 'Core wrote no rows, so the comparison proves nothing.');

    self::assertSame($by_core, $this->rowShape((int) $backfilled->id()), 'The backfill wrote a different set of rows from the one core writes for the same entity.');
  }

  /**
   * More carousels than one chunk: every one is filled, none twice.
   */
  public function testEveryCarouselAcrossChunksIsFilled(): void {
    $count = 120;
    for ($i = 0; $i < $count; $i++) {
      Paragraph::create(['type' => self::BUNDLE])->save();
    }

    self::assertSame($count, \paragraph_bundle_3d_carousel_backfill_style());

    $missing = \Drupal::entityQuery('paragraph')
      ->accessCheck(FALSE)
      ->condition('type', self::BUNDLE)
      ->notExists(self::FIELD)
      ->count()
      ->execute();
    self::assertSame(0, $missing, 'Carousels beyond the first chunk were missed.');
    self::assertSame(0, \paragraph_bundle_3d_carousel_backfill_style());
  }

  /**
   * The style field's rows for one paragraph, with ids left out.
   *
   * @param int $id
   *   Paragraph id.
   *
   * @return array{data: list<string>, revision: list<string>}
   *   Data rows and revision rows, each as "langcode:value", sorted.
   */
  private function rowShape(int $id): array {
    $storage = $this->container->get('entity_type.manager')->getStorage('paragraph');
    $mapping = $storage->getTableMapping();
    $definition = $this->container->get('entity_field.manager')->getFieldStorageDefinitions('paragraph')[self::FIELD];
    $column = $mapping->getColumnNames(self::FIELD)['value'];
    $database = \Drupal::database();

    $shape = [];
    foreach ([
      'data' => $mapping->getDedicatedDataTableName($definition),
      'revision' => $mapping->getDedicatedRevisionTableName($definition),
    ] as $key => $table) {
      $rows = [];
      foreach ($database->select($table, 't')->fields('t')->condition('entity_id', $id)->execute() as $row) {
        $row = (array) $row;
        $rows[] = $row['langcode'] . ':' . $row[$column];
      }
      sort($rows);
      $shape[$key] = $rows;
    }

    return $shape;
  }

  /**
   * Every revision id of one paragraph.
   *
   * @param int $id
   *   Paragraph id.
   *
   * @return list<int>
   *   Revision ids.
   */
  private function revisionIdsOf(int $id): array {
    $rows = \Drupal::entityQuery('paragraph')
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition('id', $id)
      ->execute();

    return array_values(array_map('intval', array_keys(is_array($rows) ? $rows : [])));
  }

  /**
   * Loads one paragraph revision fresh from storage.
   *
   * @param int $revision_id
   *   Paragraph revision id.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   The revision.
   */
  private function loadParagraphRevision(int $revision_id): Paragraph {
    $storage = $this->container->get('entity_type.manager')->getStorage('paragraph');
    $storage->resetCache();
    $revision = $storage->loadRevision($revision_id);
    self::assertInstanceOf(Paragraph::class, $revision, sprintf('Revision %d vanished.', $revision_id));

    return $revision;
  }

  /**
   * The style one revision shows in one language, read fresh.
   *
   * @param int $revision_id
   *   Paragraph revision id.
   * @param string $langcode
   *   The translation to read.
   *
   * @return string|null
   *   The value, or NULL.
   */
  private function styleAt(int $revision_id, string $langcode): ?string {
    $value = $this->loadParagraphRevision($revision_id)->getTranslation($langcode)->get(self::FIELD)->value;

    return $value === NULL ? NULL : (string) $value;
  }

  /**
   * Loads a page fresh from storage.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The page.
   *
   * @return \Drupal\node\NodeInterface
   *   The page, uncached.
   */
  private function reloadNode(NodeInterface $node): NodeInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$node->id()]);
    $fresh = $storage->load($node->id());
    self::assertInstanceOf(NodeInterface::class, $fresh, 'The host page vanished.');

    return $fresh;
  }

}
