<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Traits;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;

/**
 * Builds hosts that carry paragraphs, for the Paragraphs List index tests.
 *
 * A node type `page` and a block type `basic` each get a paragraphs field, and
 * the paragraph type `container` can nest itself through `field_children`.
 */
trait ListIndexHostTrait {

  /**
   * Creates the node type, block type, paragraph type and their fields.
   */
  protected function createListIndexHosts(): void {
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    BlockContentType::create(['id' => 'basic', 'label' => 'Basic'])->save();
    ParagraphsType::create(['id' => 'container', 'label' => 'Container'])->save();
    ParagraphsType::create(['id' => 'leaf', 'label' => 'Leaf'])->save();

    $targets = [
      ['node', 'page', 'field_paragraphs', 'Paragraphs field'],
      ['block_content', 'basic', 'field_paragraphs', 'Block paragraphs'],
      ['paragraph', 'container', 'field_children', 'Children'],
    ];
    foreach ($targets as [$entity_type, $bundle, $field_name, $label]) {
      if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
        FieldStorageConfig::create([
          'field_name' => $field_name,
          'entity_type' => $entity_type,
          'type' => 'entity_reference_revisions',
          'cardinality' => -1,
          'settings' => ['target_type' => 'paragraph'],
        ])->save();
      }
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'label' => $label,
        'settings' => [
          'handler' => 'default:paragraph',
          'handler_settings' => ['negate' => 0, 'target_bundles' => NULL],
        ],
      ])->save();
    }
  }

  /**
   * Builds a three-level chain: top contains mid contains leaf.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   The unsaved top paragraph.
   */
  protected function buildChain(): Paragraph {
    $leaf = Paragraph::create(['type' => 'leaf']);
    $mid = Paragraph::create(['type' => 'container', 'field_children' => [$leaf]]);
    return Paragraph::create(['type' => 'container', 'field_children' => [$mid]]);
  }

  /**
   * Saves a node holding the given paragraphs.
   *
   * @param string $title
   *   The node title.
   * @param list<\Drupal\paragraphs\Entity\Paragraph> $paragraphs
   *   Unsaved or saved paragraphs.
   */
  protected function saveNode(string $title, array $paragraphs): Node {
    $node = Node::create([
      'type' => 'page',
      'title' => $title,
      'field_paragraphs' => $paragraphs,
    ]);
    $node->save();
    return $node;
  }

  /**
   * Saves a block holding the given paragraphs.
   *
   * @param string $info
   *   The block description.
   * @param list<\Drupal\paragraphs\Entity\Paragraph> $paragraphs
   *   Unsaved or saved paragraphs.
   */
  protected function saveBlock(string $info, array $paragraphs): BlockContent {
    $block = BlockContent::create([
      'type' => 'basic',
      'info' => $info,
      'field_paragraphs' => $paragraphs,
    ]);
    $block->save();
    return $block;
  }

  /**
   * Reads the whole index, keyed by paragraph id.
   *
   * @return array<int, array<string, mixed>>
   *   Rows keyed by pid.
   */
  protected function indexRows(): array {
    $rows = [];
    $result = $this->container->get('database')
      ->select('paragraphs_bundles_list_index', 'i')
      ->fields('i')
      ->orderBy('pid')
      ->execute();
    foreach ($result as $row) {
      $rows[(int) $row->pid] = (array) $row;
    }
    return $rows;
  }

}
