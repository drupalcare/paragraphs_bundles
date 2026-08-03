<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Opacity input widget bound to 1–100.
 *
 * Renders a standard Drupal number form item (label + input + description).
 * The paired range slider and inner wrapper are added by JS so Gin/Claro
 * form-item classes stay on the outer element unchanged.
 *
 * Plugin id `range_number_widget`.
 */
#[FieldWidget(
  id: 'range_number_widget',
  label: new TranslatableMarkup('Value 1 to 100'),
  field_types: ['paragraphs_bundles_range'],
)]
final class RangeNumberWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   *   Field items.
   * @param int $delta
   *   Item delta.
   * @param array<string, mixed> $element
   *   Render element.
   * @param array<string, mixed> $form
   *   Form array (unused).
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return array<string, mixed>
   *   Widget element.
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element['value'] = $element + [
      '#type' => 'number',
      '#default_value' => $items[$delta]->value ?? '100',
      '#size' => 7,
      '#maxlength' => 3,
      '#attributes' => [
        'pattern' => '^(100|[1-9]?\d)$',
      ],
      '#element_validate' => [
        [static::class, 'validateOpacity'],
      ],
    ];
    return $element;
  }

  /**
   * Element validate callback for the 1–100 opacity range.
   *
   * @param array<string, mixed> $element
   *   The form element being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public static function validateOpacity(array &$element, FormStateInterface $form_state): void {
    $value = $element['#value'] ?? '';
    if ($value === '') {
      $form_state->setValueForElement($element, '');
      return;
    }
    // Anything non-numeric fails the range: casting first would score an
    // array as 1 and a word as 0, and only the second of those is rejected.
    if (!is_numeric($value)) {
      $form_state->setError($element, new TranslatableMarkup('Opacity number must be between 1 and 100.'));
      return;
    }
    $int = (int) $value;
    if ($int < 1 || $int > 100) {
      $form_state->setError($element, new TranslatableMarkup('Opacity number must be between 1 and 100.'));
    }
  }

}
