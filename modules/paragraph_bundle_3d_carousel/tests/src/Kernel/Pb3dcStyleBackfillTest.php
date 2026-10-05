<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraph_bundle_3d_carousel\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The style backfill fills the value in and leaves the content alone.
 *
 * 🚨 THIS CLASS EXISTS BECAUSE THE FIRST BACKFILL DELETED CONTENT.
 *
 * Update 9003 adds a "Carousel style" field. A field default applies only
 * when an entity is CREATED, so every carousel that predates the field holds
 * an EMPTY value. The field is optional, and an empty value renders as the
 * ring. The backfill still writes 'ring' so the edit
 * form opens showing what the carousel actually renders.
 *
 * The obvious way to write it is the one that caused the damage:
 *
 *   $paragraph->set('pb_display_3d_car_style', 'ring');
 *   $paragraph->save();
 *
 * A paragraph is OWNED BY ITS PARENT'S FIELD. Saving one standalone rewrites
 * its parent. Measured on a test site 2026-09-24, that left a node holding ONE
 * of its THREE carousels: the other two still existed as entities but were
 * dropped out of the field, and a third paragraph came back carrying a value
 * it had never been given. Inside `drush updatedb` that is silent, permanent
 * content loss on every node using this bundle.
 *
 * 📐 SO THE LOAD-BEARING ASSERTION HERE IS NOT "the value is now ring".
 * It is ::assertParentStillHolds() — the parent keeps every child it had.
 * A check that stopped at the field value would have reported the destructive
 * version as a success, which is exactly what happened in the session that
 * wrote it. The value assertions are the easy half; the parent assertion is
 * the reason this file exists.
 *
 * @group paragraphs_bundles
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class Pb3dcStyleBackfillTest extends KernelTestBase {

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
    $this->installConfig(['system', 'field', 'filter', 'node']);

    ParagraphsType::create(['id' => self::BUNDLE, 'label' => '3D carousel'])->save();

    // ⚠ KernelTestBase DOES NOT IMPORT config/optional, so enabling the module
    // would not create this field and every assertion below would be testing
    // an absent field. It is built by hand, to the same shape the shipped YAML
    // declares — list_string, one value, the two allowed keys.
    // ⚠ A FLAT key => label MAP, not a list of ['value' => …, 'label' => …].
    // Those are two representations of the same field: the sequence form is
    // what Drupal WRITES OUT to YAML after normalising, and the flat map is
    // what the PHP API ACCEPTS. Copying the shape out of the shipped config
    // file gives the first one, and KernelTestBase's strict config-schema
    // checking rejects it in setUp with "settings.allowed_values.0.label.0
    // doesn't exist" — which reads like a schema bug rather than a caller
    // mistake. Core's own options tests use this form.
    FieldStorageConfig::create([
      'field_name' => self::FIELD,
      'entity_type' => 'paragraph',
      'type' => 'list_string',
      'cardinality' => 1,
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
      // Optional, matching the shipped field.field YAML.
      'required' => FALSE,
      // NO default_value on purpose. The whole point is the state of data
      // created BEFORE the field existed, which carries no value at all.
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
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => ['target_bundles' => []],
      ],
    ])->save();

    // ⚠ NOT loadInclude(): that only knows INSTALLED modules, and this one is
    // deliberately only discovered — enabling it would drag the whole
    // paragraphs_bundles chain in to prove something it does not own. A
    // loadInclude() here returns FALSE silently and the next line fails with
    // "undefined function", which reads as a missing function rather than as a
    // file that never loaded.
    $path = \Drupal::service('extension.list.module')
      ->getPath('paragraph_bundle_3d_carousel');
    require_once \Drupal::root() . '/' . $path . '/paragraph_bundle_3d_carousel.install';

    self::assertTrue(
      function_exists('paragraph_bundle_3d_carousel_backfill_style'),
      'The install file did not load, so every assertion below would report a missing function rather than a real result.',
    );
  }

  /**
   * 🚨 THE REGRESSION. The value lands AND the parent keeps every child.
   *
   * ⚠ IT DOES NOT CATCH THE CONTENT LOSS IT WAS WRITTEN FOR. Red-proofed by
   * reinstating the `$paragraph->save()` backfill: this test stayed GREEN
   * (recorded in the coverflow design proposal). The
   * loss seen on a test site has not been reproduced here; the untested
   * difference is that the live paragraphs had several revisions each and
   * these have one. It proves the parent keeps its children on the path it
   * runs, and nothing about the destructive version.
   */
  public function testBackfillWritesRingAndKeepsEveryChildOnItsParent(): void {
    $node = $this->pageWithCarousels(3);

    // CONTROL. Without this the test could pass against a backfill that does
    // nothing, on data that already had the value.
    foreach ($this->carouselIdsOf($node) as $id) {
      self::assertNull(
        $this->styleOf($id),
        'A carousel already had a style, so this proves nothing about the backfill.',
      );
    }
    $this->assertParentStillHolds($node, 3);

    $filled = \paragraph_bundle_3d_carousel_backfill_style();

    self::assertSame(3, $filled, 'The backfill did not report filling all three carousels.');

    foreach ($this->carouselIdsOf($node) as $id) {
      self::assertSame('ring', $this->styleOf($id), sprintf('Carousel %d was not given the default style.', $id));
    }

    // THE ASSERTION THIS FILE EXISTS FOR.
    $this->assertParentStillHolds($node, 3);
  }

  /**
   * An editor's own choice is never overwritten.
   *
   * The backfill exists to fill a gap, not to reset anybody's work, and an
   * update hook runs on every deploy.
   */
  public function testAnExistingChoiceIsLeftAlone(): void {
    $node = $this->pageWithCarousels(3);
    $ids = $this->carouselIdsOf($node);

    // One carousel the editor has already set to coverflow.
    $chosen = $ids[1];
    $paragraph = $this->reloadParagraph($chosen);
    $paragraph->set(self::FIELD, 'coverflow');
    $paragraph->save();

    $filled = \paragraph_bundle_3d_carousel_backfill_style();

    self::assertSame(2, $filled, 'The backfill touched a carousel that already had a style.');
    self::assertSame('coverflow', $this->styleOf($chosen), "The editor's chosen style was overwritten.");
    self::assertSame('ring', $this->styleOf($ids[0]));
    self::assertSame('ring', $this->styleOf($ids[2]));
  }

  /**
   * Running it twice changes nothing and creates no duplicate rows.
   *
   * An update hook runs on every deploy, and a backfill that inserted a second
   * row per paragraph would give the field two values and break a single-value
   * field's rendering.
   */
  public function testSecondRunChangesNothing(): void {
    $node = $this->pageWithCarousels(2);

    $first = \paragraph_bundle_3d_carousel_backfill_style();
    self::assertSame(2, $first, 'Nothing was filled on the first run, so idempotence proves nothing.');

    $second = \paragraph_bundle_3d_carousel_backfill_style();
    self::assertSame(0, $second, 'A second run wrote rows again.');

    foreach ($this->carouselIdsOf($node) as $id) {
      $values = $this->reloadParagraph($id)->get(self::FIELD)->getValue();
      self::assertCount(1, $values, sprintf('Carousel %d ended up with more than one value.', $id));
    }

    $this->assertParentStillHolds($node, 2);
  }

  /**
   * 🚨 THE CONTENT-LOSS GUARD: the backfill writes to NOTHING but its own field.
   *
   * The destructive version called `$paragraph->save()`, and the mechanism of
   * the loss it caused on a test site was never isolated. So this does not try
   * to reproduce that mechanism. It proves the stronger property that makes
   * any such mechanism impossible: every table except the style field's own
   * data and revision tables is BYTE-IDENTICAL after the backfill — node
   * references, paragraph revisions, every other field. A backfill that saved
   * an entity, touched a parent or added a revision turns this red.
   *
   * The fixture is the shape the older test lacked: several pages, carousels
   * with SEVERAL REVISIONS each, and the paragraph-492 case — an older
   * revision holding 'coverflow' while the current one is empty.
   *
   * Red-proofed 2026-09-25 by reinstating a save()-based backfill: red.
   */
  public function testBackfillWritesNothingOutsideItsOwnField(): void {
    $first = $this->pageWithCarousels(3);
    $second = $this->pageWithCarousels(2);

    // Every carousel gains revisions through its parent, the way an editor's
    // saves create them.
    $this->resaveAsNewRevision($first);
    $this->resaveAsNewRevision($second);

    // THE 492 CASE. An older revision chose coverflow; the current one holds
    // nothing, so the entity query selects it for backfill.
    $ids = $this->carouselIdsOf($first);
    $case = $ids[1];
    $this->setStyleThroughParent($first, 1, 'coverflow');
    $chosen_revision = (int) $this->reloadParagraph($case)->getRevisionId();
    $this->setStyleThroughParent($first, 1, NULL);
    self::assertNull($this->styleOf($case), 'The 492 fixture did not end with an empty current value.');

    $revision_count = count($this->revisionsOf($case));
    self::assertGreaterThanOrEqual(3, $revision_count, 'The fixture did not build several revisions, so it tests the case the older test already covered.');

    $mapping = $this->container->get('entity_type.manager')->getStorage('paragraph')->getTableMapping();
    $definition = $this->container->get('entity_field.manager')->getFieldStorageDefinitions('paragraph')[self::FIELD];
    $own = [
      $mapping->getDedicatedDataTableName($definition),
      $mapping->getDedicatedRevisionTableName($definition),
    ];

    $before = $this->snapshotExcept($own);
    // CONTROL: the snapshot must actually cover the tables that matter, or an
    // empty snapshot would compare equal and prove nothing.
    self::assertArrayHasKey('node__' . self::HOST_FIELD, $before, 'The snapshot does not include the parent reference table.');
    self::assertArrayHasKey('paragraphs_item_revision', $before, 'The snapshot does not include paragraph revisions.');

    $filled = \paragraph_bundle_3d_carousel_backfill_style();
    self::assertSame(5, $filled, 'The backfill did not report filling all five carousels.');

    $after = $this->snapshotExcept($own);
    foreach ($before as $table => $hash) {
      self::assertSame($hash, $after[$table] ?? NULL, sprintf('The backfill changed table %s. It may only write its own field; anything else is how the content loss happened.', $table));
    }
    self::assertSame(array_keys($before), array_keys($after), 'The backfill created or dropped a table.');

    $this->assertParentStillHolds($first, 3);
    $this->assertParentStillHolds($second, 2);

    // The 492 case: history kept, current revision filled.
    self::assertSame('coverflow', $this->styleAtRevision($chosen_revision), 'The backfill overwrote an older revision that already held a choice.');
    self::assertSame('ring', $this->styleOf($case));
    self::assertCount($revision_count, $this->revisionsOf($case), 'The backfill added or removed a paragraph revision.');
  }

  /**
   * Saves the page as a new revision, which gives each carousel one too.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The host page.
   */
  private function resaveAsNewRevision(NodeInterface $node): void {
    $fresh = $this->reloadNode($node);
    $fresh->setNewRevision(TRUE);
    // A real edit. entity_reference_revisions gives a child a new revision
    // only when the host has translation changes (its
    // EntityReferenceRevisionsItem::preSave()), so an unchanged re-save
    // creates none, and the fixture silently stays at one revision each.
    $fresh->setTitle($fresh->getTitle() . ' (edited)');
    foreach ($fresh->get(self::HOST_FIELD) as $item) {
      $item->entity->setNeedsSave(TRUE);
    }
    $fresh->save();
  }

  /**
   * Sets one carousel's style the way the node edit form does: via its parent.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The host page.
   * @param int $delta
   *   Which carousel.
   * @param string|null $style
   *   The value, or NULL to clear it.
   */
  private function setStyleThroughParent(NodeInterface $node, int $delta, ?string $style): void {
    $fresh = $this->reloadNode($node);
    $fresh->setNewRevision(TRUE);
    // See ::resaveAsNewRevision() for why the title changes.
    $fresh->setTitle($fresh->getTitle() . ' (style)');
    $paragraph = $fresh->get(self::HOST_FIELD)->get($delta)->entity;
    $paragraph->set(self::FIELD, $style);
    $paragraph->setNeedsSave(TRUE);
    $fresh->save();
  }

  /**
   * Every revision id of one paragraph.
   *
   * @param int $id
   *   Paragraph id.
   *
   * @return array<int|string, int|string>
   *   Revision id => entity id.
   */
  private function revisionsOf(int $id): array {
    $rows = \Drupal::entityQuery('paragraph')
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition('id', $id)
      ->execute();

    return is_array($rows) ? $rows : [];
  }

  /**
   * The style stored on one specific revision.
   *
   * @param int $revision_id
   *   Paragraph revision id.
   *
   * @return string|null
   *   The value, or NULL.
   */
  private function styleAtRevision(int $revision_id): ?string {
    $storage = $this->container->get('entity_type.manager')->getStorage('paragraph');
    $storage->resetCache();
    $revision = $storage->loadRevision($revision_id);
    self::assertInstanceOf(Paragraph::class, $revision, sprintf('Revision %d vanished.', $revision_id));
    $value = $revision->get(self::FIELD)->value;

    return $value === NULL ? NULL : (string) $value;
  }

  /**
   * A hash of every row of every table, bar the excluded ones and caches.
   *
   * Cache tables are left out because loading an entity legitimately writes
   * them; they hold no content.
   *
   * @param list<string> $exclude
   *   Tables the backfill is allowed to write.
   *
   * @return array<string, string>
   *   Table name => hash of its sorted rows.
   */
  private function snapshotExcept(array $exclude): array {
    $database = \Drupal::database();
    $tables = $database->schema()->findTables('%');
    sort($tables);

    $snapshot = [];
    foreach ($tables as $table) {
      if (in_array($table, $exclude, TRUE) || str_starts_with($table, 'cache')) {
        continue;
      }
      // Iterated, not fetchAll(\PDO::FETCH_ASSOC): Drupal 12 takes a FetchAs
      // enum there and an int is a TypeError. Casting each row works on both.
      $rows = [];
      foreach ($database->select($table, 't')->fields('t')->execute() as $row) {
        $rows[] = serialize((array) $row);
      }
      sort($rows);
      $snapshot[$table] = hash('sha256', implode("\n", $rows));
    }

    return $snapshot;
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

  /**
   * The parent still references every child it was given.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The host page.
   * @param int $expected
   *   How many carousels it must still hold.
   */
  private function assertParentStillHolds(NodeInterface $node, int $expected): void {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$node->id()]);
    $fresh = $storage->load($node->id());

    self::assertInstanceOf(NodeInterface::class, $fresh, 'The host page itself disappeared.');
    self::assertCount(
      $expected,
      $fresh->get(self::HOST_FIELD)->getValue(),
      sprintf(
        'The page lost carousels. This is the content-loss regression: a paragraph saved on its own rewrites the parent that owns it, dropping its siblings out of the field while they still exist as orphaned entities.',
      ),
    );
  }

  /**
   * A saved page carrying the given number of carousels, none with a style.
   *
   * @param int $count
   *   How many carousels to attach.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved page.
   */
  private function pageWithCarousels(int $count): NodeInterface {
    $items = [];

    for ($i = 0; $i < $count; $i++) {
      $paragraph = Paragraph::create(['type' => self::BUNDLE]);
      $paragraph->save();
      $items[] = [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];
    }

    $node = Node::create([
      'type' => 'page',
      'title' => 'A page of carousels',
      'status' => 1,
      self::HOST_FIELD => $items,
    ]);
    $node->save();

    return $node;
  }

  /**
   * The carousel ids the page references, in order.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The host page.
   *
   * @return list<int>
   *   Paragraph ids.
   */
  private function carouselIdsOf(NodeInterface $node): array {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$node->id()]);
    $fresh = $storage->load($node->id());

    $ids = [];
    foreach ($fresh->get(self::HOST_FIELD)->getValue() as $item) {
      $ids[] = (int) $item['target_id'];
    }

    return $ids;
  }

  /**
   * The stored style of one carousel, read fresh.
   *
   * @param int $id
   *   Paragraph id.
   *
   * @return string|null
   *   The value, or NULL when the field holds nothing.
   */
  private function styleOf(int $id): ?string {
    $value = $this->reloadParagraph($id)->get(self::FIELD)->value;

    return $value === NULL ? NULL : (string) $value;
  }

  /**
   * Loads a paragraph fresh from storage.
   *
   * @param int $id
   *   Paragraph id.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   The paragraph.
   */
  private function reloadParagraph(int $id): Paragraph {
    $storage = $this->container->get('entity_type.manager')->getStorage('paragraph');
    $storage->resetCache([$id]);
    $paragraph = $storage->load($id);
    self::assertInstanceOf(Paragraph::class, $paragraph, sprintf('Paragraph %d vanished.', $id));

    return $paragraph;
  }

}
