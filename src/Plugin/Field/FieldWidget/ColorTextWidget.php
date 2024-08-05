<?php

namespace Drupal\paragraphs_bundles\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Color;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'color_text_widget' widget.
 *
 * @FieldWidget(
 *   id = "color_text_widget",
 *   module = "paragraphs_bundles",
 *   label = @Translation("RGB value as (0,0,0)"),
 *   field_types = {
 *     "paragraphs_bundles_rgb"
 *   }
 * )
 */
class ColorTextWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $value = $items[$delta]->value ?? '';
    $element += [
      '#type' => 'textfield',
      '#default_value' => $value,
      '#size' => 10,
      '#maxlength' => 7,
      '#attributes' => [
        'pattern' => '^#[a-fA-F0-9]{6}',
      ],
      '#wrapper_attributes' => [
        'data-drupal-selector' => 'para-color-picker',
      ],
      '#attached' => [
        'library' => [
          'paragraphs_bundles/color-picker',
        ],
      ],
      '#element_validate' => [
        [$this, 'validate'],
      ],
    ];
    return ['value' => $element];
  }

  /**
   * Validate the color text field.
   */
  public function validate($element, FormStateInterface $form_state) {
    $value = $element['#value'];
    if ($value == '') {
      $form_state->setValueForElement($element, '');
      return;
    }
    if (!Color::validateHex($value)) {
      $form_state->setError($element, $this->t('Color must be a 3- or 6-digit hexadecimal value, suitable for CSS.'));
    }
  }

}
