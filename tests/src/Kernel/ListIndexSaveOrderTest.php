<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\paragraphs_bundles\Traits\ListIndexHostTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The foundation of the Paragraphs List index: save order on a NEW host.
 *
 * The entity_reference_revisions module saves a composite child before its new
 * host has an id, so the child's parent_id is set only afterwards. If the index were
 * written at the wrong moment, every row of a brand-new node would carry no
 * root. This proves the index is written after that has finished.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class ListIndexSaveOrderTest extends KernelTestBase {

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
   * A new node with three levels: every row has the node id as its root.
   */
  public function testNewNodeWithThreeLevelsIndexesEveryRowUnderTheNid(): void {
    $top = $this->buildChain();
    $node = $this->saveNode('Brand new page', [$top]);
    $nid = (int) $node->id();
    $this->assertGreaterThan(0, $nid);

    $rows = $this->indexRows();
    $this->assertCount(3, $rows, 'Three paragraphs, three index rows.');
    $depths = [];
    foreach ($rows as $row) {
      $this->assertSame('node', $row['root_type']);
      $this->assertSame($nid, (int) $row['root_id'], 'Every row is rooted at the nid, not 0.');
      $depths[] = (int) $row['depth'];
      $this->assertStringStartsWith('Brand new page > ', (string) $row['label']);
    }
    sort($depths);
    $this->assertSame([1, 2, 3], $depths);
  }

}
