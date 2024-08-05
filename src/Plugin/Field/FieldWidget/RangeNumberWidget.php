<?php

namespace Drupal\paragraphs_bundles\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'range_number_widget' widget.
 *
 * @FieldWidget(
 *   id = "range_number_widget",
 *   module = "paragraphs_bundles",
 *   label = @Translation("Value 1 to 100"),
 *   field_types = {
 *     "paragraphs_bundles_range"
 *   }
 * )
 */
class RangeNumberWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element += [
      '#type' => 'number',
      '#default_value' => $items[$delta]->value ?? '100',
      '#size' => 7,
      '#maxlength' => 3,
      '#attributes' => [
        'pattern' => '^(100|[1-9]?\d)$',
      ],
      '#wrapper_attributes' => [
        'data-drupal-selector' => 'para-opacity-range',
      ],
      '#attached' => [
        'library' => [
          'paragraphs_bundles/opacity-range',
        ],
      ],
      '#element_validate' => [
        [$this, 'validate'],
      ],
    ];
    return ['value' => $element];
  }

  /**
   * Validate opacity field.
   */
  public function validate($element, FormStateInterface $form_state): void {
    $value = $element['#value'];
    if ($value == '') {
      $form_state->setValueForElement($element, '');
      return;
    }

    if (!($value >= 1 && $value <= 100)) {
      $form_state->setError($element, $this->t('Opacity number must be between 1 and 100.'));
    }

  }

}
