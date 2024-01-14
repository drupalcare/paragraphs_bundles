<?php

namespace Drupal\paragraphs_bundles\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation for HEX color text formatter.
 *
 * @FieldFormatter(
 *   id = "color_text_formatter_hex",
 *   module = "paragraphs_bundles",
 *   label = @Translation("HEX Color"),
 *   field_types = {
 *     "paragraphs_bundles_rgb"
 *   }
 * )
 */
class ColorTextFormatterHEX extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $hex_val = trim($item->value);
      $elements[$delta] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $hex_val,
      ];
    }

    return $elements;
  }

}
