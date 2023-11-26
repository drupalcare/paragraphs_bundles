<?php

namespace Drupal\paragraphs_bundles\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the 'range_number_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "range_number_formatter",
 *   module = "paragraphs_bundles",
 *   label = @Translation("Opacity Number"),
 *   field_types = {
 *     "paragraphs_bundles_range"
 *   }
 * )
 */
class RangeNumberFormatter extends FormatterBase {

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
