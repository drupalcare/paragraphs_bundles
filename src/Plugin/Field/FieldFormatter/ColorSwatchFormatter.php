<?php

namespace Drupal\paragraphs_bundles\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'color_swatch_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "color_swatch_formatter",
 *   module = "paragraphs_bundles",
 *   label = @Translation("Color Swatch"),
 *   field_types = {
 *     "paragraphs_bundles_rgb"
 *   }
 * )
 */
class ColorSwatchFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'show_label' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);

    $elements['show_label'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show hex value label'),
      '#default_value' => $this->getSetting('show_label'),
      '#description' => $this->t('Display the hex color code next to the color swatch.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    $show_label = $this->getSetting('show_label');

    if ($show_label) {
      $summary[] = $this->t('Showing color swatch with hex label');
    }
    else {
      $summary[] = $this->t('Showing color swatch only');
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $show_label = $this->getSetting('show_label');

    foreach ($items as $delta => $item) {
      $hex_value = trim($item->value);

      // Build the swatch element.
      $swatch = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => [
          'class' => ['color-swatch'],
          'style' => 'display: inline-block; width: 30px; height: 30px; background-color: ' . $hex_value . '; border: 1px solid #ccc; border-radius: 3px; vertical-align: middle;',
          'aria-label' => $this->t('Color swatch for @color', ['@color' => $hex_value]),
        ],
      ];

      // If label is enabled, wrap swatch and label together.
      if ($show_label) {
        $elements[$delta] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['color-swatch-wrapper'],
            'style' => 'display: inline-block; margin-right: 10px; margin-bottom: 5px;',
          ],
          'swatch' => $swatch,
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'span',
            '#value' => $hex_value,
            '#attributes' => [
              'class' => ['color-swatch-label'],
              'style' => 'margin-left: 8px; vertical-align: middle; font-family: monospace;',
            ],
          ],
        ];
      }
      else {
        // Just the swatch, no label.
        $elements[$delta] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['color-swatch-wrapper'],
            'style' => 'display: inline-block; margin-right: 5px; margin-bottom: 5px;',
          ],
          'swatch' => $swatch,
        ];
      }
    }

    return $elements;
  }

}
