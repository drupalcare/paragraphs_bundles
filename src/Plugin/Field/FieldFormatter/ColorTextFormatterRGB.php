<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Renders the hex value as `R,G,B`.
 *
 * Plugin id `color_text_formatter_rgb`.
 */
#[FieldFormatter(
  id: 'color_text_formatter_rgb',
  label: new TranslatableMarkup('RGB Color'),
  field_types: ['paragraphs_bundles_rgb'],
)]
final class ColorTextFormatterRGB extends FormatterBase {

  /**
   * {@inheritdoc}
   *
   * @return array<int, array<string, mixed>>
   *   Render array, one per item.
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      $hex = ltrim((string) $item->value, '#');
      if (strlen($hex) !== 6) {
        continue;
      }
      $r = (int) hexdec(substr($hex, 0, 2));
      $g = (int) hexdec(substr($hex, 2, 2));
      $b = (int) hexdec(substr($hex, 4, 2));
      $elements[$delta] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $r . ',' . $g . ',' . $b,
      ];
    }
    return $elements;
  }

}
