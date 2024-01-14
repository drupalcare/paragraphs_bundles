<?php

namespace Drupal\paragraphs_bundles\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'color_text_formatter_rgb' formatter.
 *
 * @FieldFormatter(
 *   id = "color_text_formatter_rgb",
 *   module = "paragraphs_bundles",
 *   label = @Translation("RGB Color"),
 *   field_types = {
 *     "paragraphs_bundles_rgb"
 *   }
 * )
 */
class ColorTextFormatterRGB extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $hex_val = trim($item->value, '#');
      $r0 = hexdec($hex_val[0] . $hex_val[1]);
      $g0 = hexdec($hex_val[2] . $hex_val[3]);
      $b0 = hexdec($hex_val[4] . $hex_val[5]);
      $elements[$delta] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $r0 . ',' . $g0 . ',' . $b0,
      ];
    }

    return $elements;
  }

}
