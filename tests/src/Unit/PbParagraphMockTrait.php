<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Builds paragraph/node mocks with scalar field values for unit tests.
 */
trait PbParagraphMockTrait {

  /**
   * Creates a paragraph mock with optional string field values.
   *
   * @param string $bundle
   *   Bundle machine name.
   * @param int $id
   *   Entity id.
   * @param array<string, string|int|float|bool> $fields
   *   Field machine name => stored value. NOT string-only: 47 of this module's
   *   171 paragraph fields are integer, decimal, boolean or range storages, and
   *   an integer column holds an `int` on an entity whose value was just set
   *   programmatically. Every test here used to pass strings, which is exactly
   *   why the readers' `is_string()` gate went unnoticed.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   Configured mock.
   */
  protected function mockParagraph(string $bundle, int $id, array $fields = []): ParagraphInterface {
    $paragraph = $this->createStub(ParagraphInterface::class);
    $paragraph->method('bundle')->willReturn($bundle);
    $paragraph->method('id')->willReturn($id);
    $paragraph->method('hasField')->willReturnCallback(
      static fn (string $name): bool => array_key_exists($name, $fields),
    );
    $paragraph->method('get')->willReturnCallback(
      function (string $name) use ($fields): FieldItemListInterface {
        $list = $this->createStub(FieldItemListInterface::class);
        if (!array_key_exists($name, $fields)) {
          $list->method('isEmpty')->willReturn(TRUE);
          return $list;
        }
        $item = $this->createStub(FieldItemInterface::class);
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
   * @param array<string, string|int|float|bool> $fields
   *   Field machine name => stored value. NOT string-only: 47 of this module's
   *   171 paragraph fields are integer, decimal, boolean or range storages, and
   *   an integer column holds an `int` on an entity whose value was just set
   *   programmatically. Every test here used to pass strings, which is exactly
   *   why the readers' `is_string()` gate went unnoticed.
   *
   * @return \Drupal\node\NodeInterface
   *   Configured mock.
   */
  protected function mockNode(string $bundle, int $id, array $fields = []): NodeInterface {
    $node = $this->createStub(NodeInterface::class);
    $node->method('bundle')->willReturn($bundle);
    $node->method('id')->willReturn($id);
    $node->method('hasField')->willReturnCallback(
      static fn (string $name): bool => array_key_exists($name, $fields),
    );
    $node->method('get')->willReturnCallback(
      function (string $name) use ($fields): FieldItemListInterface {
        $list = $this->createStub(FieldItemListInterface::class);
        if (!array_key_exists($name, $fields)) {
          $list->method('isEmpty')->willReturn(TRUE);
          return $list;
        }
        $item = $this->createStub(FieldItemInterface::class);
        $item->method('getValue')->willReturn(['value' => $fields[$name]]);
        $list->method('isEmpty')->willReturn(FALSE);
        $list->method('first')->willReturn($item);
        return $list;
      },
    );
    return $node;
  }

}
