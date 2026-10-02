<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs_bundles\Service\ListIndex\ListIndexerInterface;
use Drupal\Tests\paragraphs_bundles\Traits\ListIndexHostTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The upgrade path to the Paragraphs List index: update 9010 and the backfill.
 *
 * A fresh install gets the table from hook_schema(); a site that already runs
 * the module gets it from update 9010 and a post-update. They are different
 * paths and must end in the same place.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class ListIndexUpdateTest extends KernelTestBase {

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
    $this->createListIndexHosts();
    $module_path = $this->container->get('extension.list.module')->getPath('paragraphs_bundles');
    require_once $this->root . '/' . $module_path . '/paragraphs_bundles.install';
    require_once $this->root . '/' . $module_path . '/paragraphs_bundles.post_update.php';
  }

  /**
   * An existing site: no table, paragraphs already saved, then update + fill.
   */
  public function testUpdateCreatesTheTableAndThePostUpdateFillsIt(): void {
    $database = $this->container->get('database');
    $this->assertFalse($database->schema()->tableExists(ListIndexerInterface::TABLE), 'The site starts before the update.');

    // Paragraphs saved before the table existed leave no rows behind.
    $this->saveNode('Existing page', [$this->buildChain()]);
    $orphan = Paragraph::create(['type' => 'leaf']);
    $orphan->save();

    paragraphs_bundles_update_9010();
    $this->assertTrue($database->schema()->tableExists(ListIndexerInterface::TABLE));
    $this->assertSame([], $this->indexRows(), 'Update 9010 only creates the table.');

    $sandbox = [];
    $steps = 0;
    do {
      paragraphs_bundles_post_update_build_list_index($sandbox);
      $this->assertLessThan(10, ++$steps);
    } while (empty($sandbox['#finished']) || $sandbox['#finished'] < 1);

    $this->assertCount(4, $this->indexRows(), 'Three nested paragraphs and one orphan.');
    $result = $this->container->get(ListIndexerInterface::class)->verify(NULL);
    // Orphan labels may differ here (see the queued second pass); everything
    // else must already be exact.
    foreach ($result['mismatches'] as $line) {
      $this->assertStringContainsString('label', $line, $line);
    }
    $queue = $this->container->get('queue')->get('paragraphs_bundles_list_index_rebuild');
    $this->assertSame(1, $queue->numberOfItems(), 'The post-update queued the label-complete pass.');

    // Running the update again is harmless.
    $this->assertStringContainsString('already exists', paragraphs_bundles_update_9010());
  }

  /**
   * A table made before root_title existed gets the column, the index and data.
   */
  public function testUpdate9011AddsRootTitleAndThePostUpdateFillsIt(): void {
    $database = $this->container->get('database');
    $this->installSchema('paragraphs_bundles', [ListIndexerInterface::TABLE]);
    $this->saveNode('Titled page', [$this->buildChain()]);
    $this->assertCount(3, $this->indexRows());

    // Put the table back as it was before: no column, no index.
    $database->schema()->dropIndex(ListIndexerInterface::TABLE, 'root_title_pid');
    $database->schema()->dropField(ListIndexerInterface::TABLE, 'root_title');
    $this->assertFalse($database->schema()->fieldExists(ListIndexerInterface::TABLE, 'root_title'));

    $this->assertStringContainsString('Added', paragraphs_bundles_update_9011());
    $this->assertTrue($database->schema()->fieldExists(ListIndexerInterface::TABLE, 'root_title'));
    $this->assertTrue($database->schema()->indexExists(ListIndexerInterface::TABLE, 'root_title_pid'));
    $this->assertStringContainsString('already exist', paragraphs_bundles_update_9011(), 'Safe to run twice.');
    foreach ($this->indexRows() as $row) {
      $this->assertSame('', $row['root_title'], 'The new column starts empty.');
    }

    $sandbox = [];
    $steps = 0;
    do {
      paragraphs_bundles_post_update_refill_list_index_root_title($sandbox);
      $this->assertLessThan(10, ++$steps);
    } while (empty($sandbox['#finished']) || $sandbox['#finished'] < 1);
    foreach ($this->indexRows() as $row) {
      $this->assertSame('Titled page', $row['root_title']);
    }

    $sandbox = [];
    $this->assertStringContainsString('No Paragraphs List row needed', paragraphs_bundles_post_update_refill_list_index_root_title($sandbox));
  }

  /**
   * The update and a fresh install end with the same table definition.
   */
  public function testUpdateAndSchemaAgree(): void {
    $database = $this->container->get('database');
    paragraphs_bundles_update_9010();
    $this->assertTrue($database->schema()->tableExists(ListIndexerInterface::TABLE));
    $definition = paragraphs_bundles_schema()[ListIndexerInterface::TABLE];
    foreach (array_keys($definition['fields']) as $column) {
      $this->assertTrue($database->schema()->fieldExists(ListIndexerInterface::TABLE, $column), "$column exists");
    }
    foreach (array_keys($definition['indexes']) as $index) {
      $this->assertTrue($database->schema()->indexExists(ListIndexerInterface::TABLE, $index), "$index exists");
    }
  }

}
