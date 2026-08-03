<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Renders the stored hex value as a `<div>`.
 *
 * Plugin id `color_text_formatter_hex`.
 *
 * @extends \Drupal\Core\Field\FormatterBase<\Drupal\Core\Field\FieldItemListInterface<\Drupal\paragraphs_bundles\Plugin\Field\FieldType\ColorItem>>
 */
#[FieldFormatter(
  id: 'color_text_formatter_hex',
  label: new TranslatableMarkup('HEX Color'),
  field_types: ['paragraphs_bundles_rgb'],
)]
final class ColorTextFormatterHEX extends FormatterBase {

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\paragraphs_bundles\Plugin\Field\FieldType\ColorItem> $items
   *   Field items. The formatter is declared for `paragraphs_bundles_rgb`
   *   only, so every item is a ColorItem.
   * @param string $langcode
   *   Language code.
   *
   * @return array<int, array<string, mixed>>
   *   Render array, one per item.
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    /** @var array<int, array<string, mixed>> $elements */
    $elements = [];
    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => trim((string) $item->value),
      ];
    }
    return $elements;
  }

}
