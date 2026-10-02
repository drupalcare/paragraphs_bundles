<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\Core\Database\Database;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs_bundles\Hook\PbListIndexHook;
use Drupal\paragraphs_bundles\Plugin\QueueWorker\ListIndexRebuildWorker;
use Drupal\paragraphs_bundles\Service\ListIndex\ListIndexerInterface;
use Drupal\Tests\paragraphs_bundles\Traits\ListIndexHostTrait;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Keeps the Paragraphs List index true, trigger by trigger.
 *
 * The first test of the index, the save order of a brand-new node, lives in
 * ListIndexSaveOrderTest; this class covers every other way the index moves.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class ListIndexTest extends KernelTestBase {

  use ListIndexHostTrait;

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
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('file');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('media');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('paragraphs_bundles', ['paragraphs_bundles_list_index']);
    $this->createListIndexHosts();
  }

  /**
   * The indexer service.
   */
  private function indexer(): ListIndexerInterface {
    $indexer = $this->container->get(ListIndexerInterface::class);
    assert($indexer instanceof ListIndexerInterface);
    return $indexer;
  }

  /**
   * Asserts the table equals a fresh derivation of every paragraph.
   */
  private function assertIndexIsTrue(): void {
    $result = $this->indexer()->verify(NULL);
    $this->assertSame([], $result['mismatches'], implode("\n", $result['mismatches']));
  }

  /**
   * Adding a child on edit indexes it; removing it never leaves a bad row.
   */
  public function testEditingHostAddsAndRemovesRows(): void {
    $top = $this->buildChain();
    $node = $this->saveNode('Edited page', [$top]);
    $this->assertCount(3, $this->indexRows());

    $extra = Paragraph::create(['type' => 'leaf']);
    $node->set('field_paragraphs', [$top, $extra]);
    $node->setNewRevision(TRUE);
    $node->save();
    $rows = $this->indexRows();
    $this->assertCount(4, $rows);
    $this->assertSame(1, (int) $rows[(int) $extra->id()]['depth']);
    $this->assertSame((int) $node->id(), (int) $rows[(int) $extra->id()]['root_id']);
    $this->assertIndexIsTrue();

    // Removed from the field: the row either goes with the paragraph or says
    // it is a previous revision, exactly as Paragraph::label() does.
    $node->set('field_paragraphs', [$top]);
    $node->setNewRevision(TRUE);
    $node->save();
    $rows = $this->indexRows();
    $removed_id = (int) $extra->id();
    if (isset($rows[$removed_id])) {
      $this->assertStringEndsWith('(previous revision)', (string) $rows[$removed_id]['label']);
    }
    $this->assertIndexIsTrue();
  }

  /**
   * Runs entity_reference_revisions' orphan purger until its queue is empty.
   */
  private function runOrphanPurger(): void {
    $queue = $this->container->get('queue')->get('entity_reference_revisions_orphan_purger');
    $worker = $this->container->get('plugin.manager.queue_worker')
      ->createInstance('entity_reference_revisions_orphan_purger');
    $guard = 0;
    while (($item = $queue->claimItem()) !== FALSE) {
      $worker->processItem($item->data);
      $queue->deleteItem($item);
      $this->assertLessThan(50, ++$guard);
    }
  }

  /**
   * Deleting the node orphans its rows at once and removes them on purge.
   *
   * The entity_reference_revisions module deletes a host's paragraphs from a
   * queue, so between the delete and the purge they exist as orphans, and the index must
   * say so rather than keep pointing at a node that is gone.
   */
  public function testNodeDeleteOrphansThenPurgeRemovesTheRows(): void {
    $node = $this->saveNode('Doomed page', [$this->buildChain()]);
    $this->assertCount(3, $this->indexRows());
    $node->delete();

    $rows = $this->indexRows();
    $this->assertCount(3, $rows, 'The paragraphs outlive the node until the purger runs.');
    foreach ($rows as $row) {
      $this->assertSame('', $row['root_type']);
      $this->assertSame(0, (int) $row['root_id']);
      $this->assertStringStartsWith('Orphaned ', (string) $row['label']);
    }
    $this->assertIndexIsTrue();

    $this->runOrphanPurger();
    $this->assertSame([], $this->indexRows());
  }

  /**
   * Deleting one paragraph removes its row; its children become orphans.
   */
  public function testParagraphDeleteRemovesItsRowAndOrphansItsChildren(): void {
    $top = $this->buildChain();
    $this->saveNode('Partly deleted', [$top]);
    $this->assertCount(3, $this->indexRows());
    $top_id = (int) $top->id();
    $top->delete();

    $rows = $this->indexRows();
    $this->assertArrayNotHasKey($top_id, $rows);
    $this->assertCount(2, $rows);
    foreach ($rows as $row) {
      $this->assertSame('', $row['root_type']);
      $this->assertStringStartsWith('Orphaned ', (string) $row['label']);
    }
    $this->assertIndexIsTrue();

    $this->runOrphanPurger();
    $this->assertSame([], $this->indexRows());
  }

  /**
   * A block_content root is recorded with its own type and id.
   */
  public function testBlockContentRoot(): void {
    $block = $this->saveBlock('A block', [$this->buildChain()]);
    $rows = $this->indexRows();
    $this->assertCount(3, $rows);
    foreach ($rows as $row) {
      $this->assertSame('block_content', $row['root_type']);
      $this->assertSame((int) $block->id(), (int) $row['root_id']);
      $this->assertStringStartsWith('A block > ', (string) $row['label']);
    }
    $this->assertIndexIsTrue();
  }

  /**
   * A paragraph nothing uses is an orphan: no root, depth 0, orphan label.
   */
  public function testOrphan(): void {
    $orphan = Paragraph::create(['type' => 'leaf']);
    $orphan->save();
    $rows = $this->indexRows();
    $row = $rows[(int) $orphan->id()];
    $this->assertSame('', $row['root_type']);
    $this->assertSame(0, (int) $row['root_id']);
    $this->assertSame(0, (int) $row['depth']);
    $this->assertStringStartsWith('Orphaned Leaf', (string) $row['label']);
    $this->assertIndexIsTrue();
  }

  /**
   * Changing the node title rewrites the label of every row beneath it.
   */
  public function testNodeTitleChangeRewritesLabels(): void {
    $node = $this->saveNode('Old title', [$this->buildChain()]);
    $node->setTitle('New title')->save();
    foreach ($this->indexRows() as $row) {
      $this->assertStringStartsWith('New title > ', (string) $row['label']);
    }
    $this->assertIndexIsTrue();
  }

  /**
   * Renaming a node changes root_title on every row of its tree.
   */
  public function testRootTitleFollowsTheRootEntity(): void {
    $node = $this->saveNode('First title', [$this->buildChain()]);
    $block = $this->saveBlock('Block title', [$this->buildChain()]);
    $orphan = Paragraph::create(['type' => 'leaf']);
    $orphan->save();

    foreach ($this->indexRows() as $row) {
      if ($row['root_type'] === 'node') {
        $this->assertSame('First title', $row['root_title']);
      }
      elseif ($row['root_type'] === 'block_content') {
        $this->assertSame('Block title', $row['root_title'], 'Other root types use their own label.');
      }
      else {
        $this->assertSame('', $row['root_title'], 'An orphan has no root title.');
      }
    }

    $node->setTitle('Renamed title')->save();
    $renamed = 0;
    foreach ($this->indexRows() as $row) {
      if ($row['root_type'] === 'node') {
        $this->assertSame('Renamed title', $row['root_title'], 'Every row of the tree follows the rename.');
        $renamed++;
      }
    }
    $this->assertSame(3, $renamed);
    $this->assertIndexIsTrue();
  }

  /**
   * A paragraph moved to another host leaves no row behind the old one.
   */
  public function testMovingParagraphToAnotherHost(): void {
    $leaf = Paragraph::create(['type' => 'leaf']);
    $first = $this->saveNode('First', [$leaf]);
    $second = $this->saveNode('Second', []);
    $first->set('field_paragraphs', []);
    $first->setNewRevision(TRUE);
    $first->save();
    $second->set('field_paragraphs', [$leaf]);
    $second->setNewRevision(TRUE);
    $second->save();
    $rows = $this->indexRows();
    foreach ($rows as $row) {
      $this->assertNotSame((int) $first->id(), (int) $row['root_id'], 'Nothing is still rooted at the first node.');
    }
    $this->assertIndexIsTrue();
  }

  /**
   * A field label change is picked up by the queued, chunked rebuild.
   */
  public function testFieldLabelChangeQueuesRebuildThatFixesLabels(): void {
    $this->saveNode('Labelled', [$this->buildChain()]);
    $field = FieldConfig::loadByName('node', 'page', 'field_paragraphs');
    $this->assertNotNull($field);
    $field->setLabel('Renamed field')->save();
    // The worker runs in a later request in real life; here the entities the
    // save loaded are still in the static cache holding the old definition.
    $this->container->get('entity_type.manager')->getStorage('node')->resetCache();
    $this->container->get('entity_type.manager')->getStorage('paragraph')->resetCache();

    $queue = $this->container->get('queue')->get(PbListIndexHook::REBUILD_QUEUE);
    $this->assertSame(1, $queue->numberOfItems(), 'A label change queues exactly one rebuild.');

    // Run the worker until the queue drains; each item queues the next chunk.
    $worker = $this->container->get('plugin.manager.queue_worker')
      ->createInstance(PbListIndexHook::REBUILD_QUEUE);
    $runs = 0;
    while (($item = $queue->claimItem()) !== FALSE) {
      $worker->processItem($item->data);
      $queue->deleteItem($item);
      $runs++;
      $this->assertLessThan(10, $runs);
    }
    $this->assertGreaterThan(1, $runs, 'The worker re-queued a follow-up chunk until none were left.');
    $labels = array_column($this->indexRows(), 'label');
    $this->assertContains('Labelled > Renamed field', $labels);
    $this->assertIndexIsTrue();
  }

  /**
   * A failing index write never rolls back the editor's save.
   *
   * The index is derived and repairable, and the hooks run inside the save's
   * transaction, so a failure is logged and a rebuild is queued instead.
   */
  public function testIndexFailureDoesNotBlockSavingAndQueuesRebuild(): void {
    $logger = new class () implements LoggerInterface {

      use RfcLoggerTrait;

      /**
       * Messages seen.
       *
       * @var list<string>
       */
      public array $messages = [];

      /**
       * {@inheritdoc}
       */
      public function log($level, string|\Stringable $message, array $context = []): void {
        $this->messages[] = strtr((string) $message, array_map('strval', $context));
      }

    };
    $this->container->get('logger.factory')->addLogger($logger);

    // One good save first, so the indexer has seen the table exist; then break
    // the table so every index write fails.
    $this->saveNode('Before', [$this->buildChain()]);
    $this->container->get('database')->schema()->dropTable(ListIndexerInterface::TABLE);

    $node = $this->saveNode('After the table broke', [$this->buildChain()]);
    $this->assertNotNull($node->id(), 'The editor\'s save went through.');
    $this->assertNotNull($this->container->get('entity_type.manager')->getStorage('node')->loadUnchanged($node->id()));

    $failures = array_filter($logger->messages, static fn(string $m): bool => str_contains($m, 'Paragraphs List index update failed for'));
    $this->assertNotSame([], $failures, 'The failure was logged with the entity type and id.');
    $this->assertStringContainsString('node ' . $node->id(), implode("\n", $failures));
    $queue = $this->container->get('queue')->get(PbListIndexHook::REBUILD_QUEUE);
    $this->assertSame(1, $queue->numberOfItems(), 'A rebuild was queued.');

    // Deleting survives it too.
    $node->delete();
  }

  /**
   * Only a paragraphs field's label queues a rebuild.
   */
  public function testNonParagraphFieldLabelQueuesNothing(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_note',
      'entity_type' => 'node',
      'type' => 'string',
    ])->save();
    $note = FieldConfig::create([
      'field_name' => 'field_note',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Note',
    ]);
    $note->save();
    $queue = $this->container->get('queue')->get(PbListIndexHook::REBUILD_QUEUE);
    $this->assertSame(0, $queue->numberOfItems(), 'Creating a field queues nothing.');

    $note->setLabel('Renamed note')->save();
    $this->assertSame(0, $queue->numberOfItems(), 'A non-paragraph field label change queues nothing.');

    $paragraphs = FieldConfig::loadByName('node', 'page', 'field_paragraphs');
    $this->assertNotNull($paragraphs);
    $paragraphs->setLabel('Renamed paragraphs')->save();
    $this->assertSame(1, $queue->numberOfItems(), 'A paragraph field label change queues a rebuild.');
  }

  /**
   * A change made while a rebuild runs makes it go round again from the start.
   */
  public function testChangeDuringRunningRebuildRestartsFromTheBeginning(): void {
    $this->saveNode('Busy', [$this->buildChain()]);
    $queue = $this->container->get('queue')->get(PbListIndexHook::REBUILD_QUEUE);
    $hook = $this->container->get(PbListIndexHook::class);
    $worker = $this->container->get('plugin.manager.queue_worker')->createInstance(PbListIndexHook::REBUILD_QUEUE);

    $hook->queueRebuild();
    $this->assertSame(1, $queue->numberOfItems());
    // A second request while the chain exists sets the flag, no second item.
    $hook->queueRebuild();
    $this->assertSame(1, $queue->numberOfItems());
    $this->assertTrue($this->container->get('state')->get(PbListIndexHook::DIRTY_STATE, FALSE));

    // Drain: chunk(s), then the chain ends, sees the flag and restarts once.
    $restarted = FALSE;
    $runs = 0;
    while (($item = $queue->claimItem()) !== FALSE) {
      if ($runs > 0 && ($item->data['after'] ?? NULL) === 0) {
        $restarted = TRUE;
      }
      $worker->processItem($item->data);
      $queue->deleteItem($item);
      $this->assertLessThan(20, ++$runs);
    }
    $this->assertTrue($restarted, 'The chain restarted from id 0.');
    $this->assertFalse($this->container->get('state')->get(PbListIndexHook::DIRTY_STATE, FALSE), 'The flag was cleared, so the second pass ends.');
  }

  /**
   * An unchanged label does not queue anything.
   */
  public function testFieldSaveWithSameLabelQueuesNothing(): void {
    $field = FieldConfig::loadByName('node', 'page', 'field_paragraphs');
    $this->assertNotNull($field);
    $field->setDescription('Only the description changed.')->save();
    $queue = $this->container->get('queue')->get(PbListIndexHook::REBUILD_QUEUE);
    $this->assertSame(0, $queue->numberOfItems());
  }

  /**
   * The chunked backfill fills an empty table, in order, and can resume.
   */
  public function testBackfillInChunks(): void {
    $this->saveNode('One', [$this->buildChain()]);
    $this->saveNode('Two', [$this->buildChain()]);
    $expected = $this->indexRows();
    $this->assertCount(6, $expected);

    $this->container->get('database')->truncate(ListIndexerInterface::TABLE)->execute();
    $this->assertSame([], $this->indexRows());

    $after = 0;
    $chunks = 0;
    while (($last = $this->indexer()->rebuildChunk($after, 4)) !== NULL) {
      $this->assertGreaterThan($after, $last, 'Each chunk moves forward.');
      $after = $last;
      $chunks++;
    }
    $this->assertSame(2, $chunks, 'Six paragraphs at four per chunk is two chunks.');
    $this->assertSame($expected, $this->indexRows(), 'The backfill derives the same rows the save hooks wrote.');
  }

  /**
   * The backfill walks paragraph ids by a plain primary-key range.
   *
   * An entity query here joins the data table and groups, and on a million rows
   * MySQL then scans everything after the cursor on every chunk (measured
   * 10.7 s per 200 paragraphs). The id-walk must be one select on the base
   * table: no JOIN, no GROUP BY.
   */
  public function testBackfillIdWalkIsPlainPrimaryKeyRange(): void {
    $this->saveNode('One', [$this->buildChain()]);
    Database::startLog('pb_idwalk');
    $this->indexer()->rebuildChunk(0, 10);
    $this->indexer()->verify(NULL);
    $this->indexer()->pruneMissing();
    $log = Database::getLog('pb_idwalk');

    $walks = [];
    foreach ($log as $entry) {
      $sql = (string) $entry['query'];
      if (stripos($sql, 'select') === 0 && stripos($sql, 'paragraphs_item') !== FALSE && stripos($sql, 'LIMIT') !== FALSE
        && stripos($sql, 'parent_type') === FALSE && stripos($sql, ListIndexerInterface::TABLE) === FALSE) {
        $walks[] = $sql;
      }
    }
    $this->assertNotSame([], $walks, 'The id-walk query was captured.');
    foreach ($walks as $sql) {
      $this->assertStringNotContainsStringIgnoringCase('GROUP BY', $sql, $sql);
      $this->assertStringNotContainsStringIgnoringCase('JOIN', $sql, $sql);
      $this->assertStringNotContainsStringIgnoringCase('paragraphs_item_field_data', $sql, $sql);
    }
  }

  /**
   * The dynamic-CSS post-update walks paragraph ids by a plain primary-key range.
   *
   * It used an entity query (JOIN + GROUP BY), which on a big site scans every
   * row after the cursor on every batch. Same rule as the index backfill.
   */
  public function testDynamicCssPostUpdateIdWalkIsPlainPrimaryKeyRange(): void {
    $module_path = $this->container->get('extension.list.module')->getPath('paragraphs_bundles');
    require_once $this->root . '/' . $module_path . '/paragraphs_bundles.post_update.php';
    $this->saveNode('Styled', [$this->buildChain()]);

    Database::startLog('pb_css_walk');
    $sandbox = [];
    paragraphs_bundles_post_update_regenerate_dynamic_css($sandbox);
    $log = Database::getLog('pb_css_walk');

    $walks = [];
    foreach ($log as $entry) {
      $sql = (string) $entry['query'];
      if (stripos($sql, 'select') === 0 && stripos($sql, 'paragraphs_item') !== FALSE && stripos($sql, 'LIMIT') !== FALSE) {
        $walks[] = $sql;
      }
    }
    $this->assertNotSame([], $walks, 'The id-walk query was captured.');
    foreach ($walks as $sql) {
      $this->assertStringNotContainsStringIgnoringCase('GROUP BY', $sql, $sql);
      $this->assertStringNotContainsStringIgnoringCase('JOIN', $sql, $sql);
      $this->assertStringNotContainsStringIgnoringCase('paragraphs_item_field_data', $sql, $sql);
    }
    $this->assertSame('paragraph', $sandbox['phase'], 'Behaviour unchanged: the first batch stays in the paragraph phase.');
    $this->assertGreaterThan(0, $sandbox['progress']);
  }

  /**
   * Verify catches a planted mismatch and an extra row.
   */
  public function testVerifyCatchesPlantedMismatches(): void {
    $node = $this->saveNode('Verified', [$this->buildChain()]);
    $this->assertIndexIsTrue();
    $this->assertSame([], $this->indexer()->verify(10)['mismatches']);

    $pids = array_keys($this->indexRows());
    $database = $this->container->get('database');
    $database->update(ListIndexerInterface::TABLE)
      ->fields(['root_id' => (int) $node->id() + 99])
      ->condition('pid', $pids[0])
      ->execute();
    $database->delete(ListIndexerInterface::TABLE)->condition('pid', $pids[1])->execute();
    $database->insert(ListIndexerInterface::TABLE)
      ->fields([
        'pid' => 9999,
        'bundle' => 'leaf',
        'root_type' => 'node',
        'root_id' => 1,
        'depth' => 1,
        'langcode' => 'en',
        'label' => 'ghost',
      ])
      ->execute();

    $all = $this->indexer()->verify(NULL);
    $text = implode("\n", $all['mismatches']);
    $this->assertStringContainsString('root_id', $text, 'The changed column is named.');
    $this->assertStringContainsString('missing from the index', $text);
    $this->assertStringContainsString('pid 9999: in the index but the paragraph does not exist', $text);
    $this->assertNotSame([], $this->indexer()->verify(10)['mismatches'], 'A sample over a tiny table sees the same problems.');
  }

  /**
   * A paragraph whose label cannot be computed never stops a save.
   *
   * Deleting a paragraph type from config leaves its paragraphs behind, and an
   * orphan's label names its type, so Paragraph::label() throws for them. The
   * index must store a stand-in and carry on.
   */
  public function testUncomputableLabelDoesNotBlockSaving(): void {
    $orphan = Paragraph::create(['type' => 'leaf']);
    $orphan->save();
    $this->container->get('config.factory')->getEditable('paragraphs.paragraphs_type.leaf')->delete();
    $this->container->get('entity_type.manager')->getStorage('paragraphs_type')->resetCache();
    $this->container->get('entity_type.manager')->getStorage('paragraph')->resetCache();

    $reloaded = Paragraph::load($orphan->id());
    $this->assertNotNull($reloaded);
    $reloaded->save();

    $rows = $this->indexRows();
    $this->assertStringStartsWith('(label unavailable)', (string) $rows[(int) $orphan->id()]['label']);
  }

  /**
   * Without the table (deploy before updb) saving still works.
   */
  public function testSavingWorksWhenTheTableIsMissing(): void {
    $this->container->get('database')->schema()->dropTable(ListIndexerInterface::TABLE);
    $node = $this->saveNode('No table', [$this->buildChain()]);
    $this->assertNotNull($node->id());
    $node->delete();
  }

  /**
   * The rebuild worker's chunk size is the documented 500.
   */
  public function testChunkSizeConstant(): void {
    $this->assertSame(500, ListIndexRebuildWorker::CHUNK);
  }

}
