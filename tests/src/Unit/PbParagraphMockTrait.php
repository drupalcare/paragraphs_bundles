<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Builds paragraph/node mocks with string field values for unit tests.
 */
trait PbParagraphMockTrait {

  /**
   * Creates a paragraph mock with optional string field values.
   *
   * @param string $bundle
   *   Bundle machine name.
   * @param int $id
   *   Entity id.
   * @param array<string, string> $fields
   *   Field machine name => stored value.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   Configured mock.
   */
  protected function mockParagraph(string $bundle, int $id, array $fields = []): ParagraphInterface {
    $paragraph = $this->createMock(ParagraphInterface::class);
    $paragraph->method('bundle')->willReturn($bundle);
    $paragraph->method('id')->willReturn($id);
    $paragraph->method('hasField')->willReturnCallback(
      static fn (string $name): bool => array_key_exists($name, $fields),
    );
    $paragraph->method('get')->willReturnCallback(
      function (string $name) use ($fields): FieldItemListInterface {
        $list = $this->createMock(FieldItemListInterface::class);
        if (!array_key_exists($name, $fields)) {
          $list->method('isEmpty')->willReturn(TRUE);
          return $list;
        }
        $item = $this->createMock(FieldItemInterface::class);
        $item->method('getValue')->willReturn(['value' => $fields[$name]]);
        $list->method('isEmpty')->willReturn(FALSE);
        $list->method('first')->willReturn($item);
        return $list;
      },
    );
    return $paragraph;
  }

  /**
   * Creates a node mock with optional string field values.
   *
   * @param string $bundle
   *   Bundle machine name.
   * @param int $id
   *   Entity id.
   * @param array<string, string> $fields
   *   Field machine name => stored value.
   *
   * @return \Drupal\node\NodeInterface
   *   Configured mock.
   */
  protected function mockNode(string $bundle, int $id, array $fields = []): NodeInterface {
    $node = $this->createMock(NodeInterface::class);
    $node->method('bundle')->willReturn($bundle);
    $node->method('id')->willReturn($id);
    $node->method('hasField')->willReturnCallback(
      static fn (string $name): bool => array_key_exists($name, $fields),
    );
    $node->method('get')->willReturnCallback(
      function (string $name) use ($fields): FieldItemListInterface {
        $list = $this->createMock(FieldItemListInterface::class);
        if (!array_key_exists($name, $fields)) {
          $list->method('isEmpty')->willReturn(TRUE);
          return $list;
        }
        $item = $this->createMock(FieldItemInterface::class);
        $item->method('getValue')->willReturn(['value' => $fields[$name]]);
        $list->method('isEmpty')->willReturn(FALSE);
        $list->method('first')->willReturn($item);
        return $list;
      },
    );
    return $node;
  }

}
