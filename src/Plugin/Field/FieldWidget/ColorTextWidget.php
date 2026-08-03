<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Color;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Hex-colour textfield widget with picker JS.
 *
 * Plugin id `color_text_widget`.
 */
#[FieldWidget(
  id: 'color_text_widget',
  label: new TranslatableMarkup('RGB value as (0,0,0)'),
  field_types: ['paragraphs_bundles_rgb'],
)]
final class ColorTextWidget extends WidgetBase {

  /**
   * Maps pb_display_* field names to their visual category.
   *
   * @var array<string, string>
   */
  private const array COLOR_CATEGORIES = [
    'pb_display_content_bg' => 'background',
    'pb_display_bg' => 'background',
    'pb_display_bg_hover' => 'background',
    'pb_display_text' => 'text',
    'pb_display_text_hover' => 'text',
    'pb_display_border_color' => 'border',
    'pb_display_border_hover' => 'border',
    'pb_display_link' => 'link',
    'pb_display_link_hover' => 'link',
  ];

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
    $value = $items[$delta]->value ?? '';
    $fieldName = $items->getFieldDefinition()->getName();
    $category = self::COLOR_CATEGORIES[$fieldName] ?? '';

    $wrapperClasses = ['pb-color-picker', 'pb-color-field__wrapper'];
    if ($category !== '') {
      $wrapperClasses[] = 'pb-color-field__wrapper--' . $category;
    }
    $classAttr = Html::escape(implode(' ', $wrapperClasses));

    // Merge $element so #title and #description reach the value form item
    // (same pattern as core StringTextfieldWidget).
    $element['value'] = $element + [
      '#type' => 'textfield',
      '#default_value' => $value,
      '#size' => 10,
      '#maxlength' => 7,
      '#prefix' => Markup::create(
        '<div class="' . $classAttr . '" data-drupal-selector="pb-color-picker">'
      ),
      '#suffix' => Markup::create('</div>'),
      '#attributes' => [
        'pattern' => '^#[a-fA-F0-9]{6}',
      ],
      '#element_validate' => [
        [static::class, 'validateColor'],
      ],
    ];
    return $element;
  }

  /**
   * Element validate callback for hex colour input.
   *
   * @param array<string, mixed> $element
   *   The form element being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   */
  public static function validateColor(array &$element, FormStateInterface $form_state): void {
    $value = $element['#value'] ?? '';
    if ($value === '') {
      $form_state->setValueForElement($element, '');
      return;
    }
    // A non-string submitted value cannot be a colour, and handing it to
    // Color::validateHex() would raise a TypeError rather than a form error.
    if (!is_string($value) || !Color::validateHex($value)) {
      $form_state->setError($element, new TranslatableMarkup('Color must be a 3- or 6-digit hexadecimal value, suitable for CSS.'));
    }
  }

}
