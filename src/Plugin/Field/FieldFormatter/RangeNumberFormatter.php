<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Renders the opacity number as a `<div>`.
 *
 * Plugin id `range_number_formatter`.
 */
#[FieldFormatter(
  id: 'range_number_formatter',
  label: new TranslatableMarkup('Opacity Number'),
  field_types: ['paragraphs_bundles_range'],
)]
final class RangeNumberFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\paragraphs_bundles\Plugin\Field\FieldType\RangeItem> $items
   *   Field items. The formatter is declared for `paragraphs_bundles_range`
   *   only, so every item is a RangeItem.
   * @param string $langcode
   *   Language code.
   *
   * @return array<int, array<string, mixed>>
   *   Render array, one per item.
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => (string) $item->value,
      ];
    }
    return $elements;
  }

}
