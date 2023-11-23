<?php

namespace Drupal\paragraphs_bundles\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'color_text_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "color_text_formatter",
 *   module = "paragraphs_bundles",
 *   label = @Translation("Text color formatter"),
 *   field_types = {
 *     "paragraphs_bundles_rgb"
 *   }
 * )
 */
class ColorTextFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $item->value,
      ];
    }

    return $elements;
  }

}
