<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Core\Field\FieldItemListInterface;

/**
 * Turns a plain string into a value a field will accept without losing itself.
 *
 * ONE OWNER FOR A QUESTION TWO SERVICES GOT WRONG. Both the composer and the
 * editor take words from a model and hand them to `set()`. On a plain string
 * field that is correct. On a FORMATTED field it is data loss: Drupal replaces
 * the whole item with `['value' => …]` and the `format` property is dropped,
 * so a body authored in Full HTML comes back rendering through the site's
 * fallback format — escaped, as source, in front of visitors.
 *
 * @see \Drupal\paragraphs_bundles_ai\Service\TextFormats
 */
interface TextFormatsInterface {

  /**
   * The value to write into this field for this text.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field being written to, as it currently stands. Its existing format
   *   is preserved when it has one.
   * @param string $text
   *   The words.
   *
   * @return array<string, string>|string
   *   An item array carrying the format, or the plain string when the field
   *   has no format to carry.
   */
  public function valueFor(FieldItemListInterface $items, string $text): array|string;

}
