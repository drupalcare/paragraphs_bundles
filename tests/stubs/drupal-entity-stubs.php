<?php

// phpcs:ignoreFile -- maintenance script; excluded from release tarball.

declare(strict_types=1);

/**
 * @file
 * Minimal entity interfaces for paragraphs_bundles unit tests.
 *
 * The paragraphs contrib module is not a composer dependency in this repo;
 * these stubs satisfy type hints used by CSS builder services under test.
 */

namespace Drupal\Core\Entity {

  if (!interface_exists(ContentEntityInterface::class)) {
    /**
     * Minimal content entity contract for test stubs.
     */
    interface ContentEntityInterface {

      /**
       * Entity id.
       */
      public function id(): int|string|null;

      /**
       * Bundle machine name.
       */
      public function bundle(): string;

      /**
       * Whether a field exists on the entity.
       */
      public function hasField(string $field_name): bool;

      /**
       * Returns a field item list.
       *
       * @return \Drupal\Core\Field\FieldItemListInterface
       *   Field item list.
       */
      public function get(string $field_name): FieldItemListInterface;

    }
  }

}

namespace Drupal\Core\Field {

  if (!interface_exists(FieldItemListInterface::class)) {
    /**
     * Minimal field item list contract for test stubs.
     */
    interface FieldItemListInterface {

      /**
       * Whether the field has no values.
       */
      public function isEmpty(): bool;

      /**
       * First field item, if any.
       *
       * @return \Drupal\Core\Field\FieldItemInterface|null
       *   First item.
       */
      public function first(): ?FieldItemInterface;

    }
  }

  if (!interface_exists(FieldItemInterface::class)) {
    /**
     * Minimal field item contract for test stubs.
     */
    interface FieldItemInterface {

      /**
       * Raw stored value array.
       *
       * @return array<string, mixed>
       *   Field value structure.
       */
      public function getValue(): array;

    }
  }

}

namespace Drupal\paragraphs {

  use Drupal\Core\Entity\ContentEntityInterface;

  if (!interface_exists(ParagraphInterface::class)) {
    /**
     * Paragraph entity stub interface for unit tests.
     */
    interface ParagraphInterface extends ContentEntityInterface {
    }
  }

}

namespace Drupal\node {

  use Drupal\Core\Entity\ContentEntityInterface;

  if (!interface_exists(NodeInterface::class)) {
    /**
     * Node entity stub interface for unit tests.
     */
    interface NodeInterface extends ContentEntityInterface {
    }
  }

}
