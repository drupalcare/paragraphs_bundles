<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Renders a swatch with optional hex label.
 *
 * Plugin id `color_swatch_formatter`.
 */
#[FieldFormatter(
  id: 'color_swatch_formatter',
  label: new TranslatableMarkup('Color Swatch'),
  field_types: ['paragraphs_bundles_rgb'],
)]
final class ColorSwatchFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   Default formatter settings.
   */
  public static function defaultSettings(): array {
    return [
      'show_label' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return array<string, mixed>
   *   Settings form elements.
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
   *
   * @return array<int, \Drupal\Core\StringTranslation\TranslatableMarkup>
   *   Settings summary.
   */
  public function settingsSummary(): array {
    return [
      $this->getSetting('show_label')
        ? $this->t('Showing color swatch with hex label')
        : $this->t('Showing color swatch only'),
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @return array<int, array<string, mixed>>
   *   Render array, one per item.
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    $show_label = (bool) $this->getSetting('show_label');

    foreach ($items as $delta => $item) {
      $hex = trim((string) $item->value);
      $swatch = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => [
          'class' => ['color-swatch'],
          'style' => 'display: inline-block; width: 30px; height: 30px; background-color: ' . $hex . '; border: 1px solid #ccc; border-radius: 3px; vertical-align: middle;',
          'aria-label' => $this->t('Color swatch for @color', ['@color' => $hex]),
        ],
      ];

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
            '#value' => $hex,
            '#attributes' => [
              'class' => ['color-swatch-label'],
              'style' => 'margin-left: 8px; vertical-align: middle; font-family: monospace;',
            ],
          ],
        ];
        continue;
      }

      $elements[$delta] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['color-swatch-wrapper'],
          'style' => 'display: inline-block; margin-right: 5px; margin-bottom: 5px;',
        ],
        'swatch' => $swatch,
      ];
    }

    return $elements;
  }

}
