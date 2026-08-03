<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_node_reference\Hook;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs_bundles\Utility\PbRenderArray;

/**
 * Field widget hook — conditional NF reference fields by view mode.
 *
 * Which NF field is shown for the selected view mode is decided in
 * `pb-conditional-field.js`, which also has to clear the fields it hides and
 * to infer the mode from populated fields on first attach. This hook only
 * puts that library on the form.
 */
final class PbnrFieldWidgetHook {

  /**
   * Implements hook_field_widget_single_element_form_alter().
   *
   * @param array<string, mixed> $element
   *   Widget form element (passed by reference).
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array<string, mixed> $context
   *   Widget context.
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(
    array &$element,
    FormStateInterface $form_state,
    array $context,
  ): void {
    $items = $context['items'] ?? NULL;
    $widget = $context['widget'] ?? NULL;
    if (!$items instanceof FieldItemListInterface || !$widget instanceof WidgetInterface) {
      return;
    }

    if ($widget->getPluginId() !== 'options_select') {
      return;
    }

    $field_name = $items->getFieldDefinition()->getName();
    $entity = $items->getEntity();
    if ($field_name !== 'pb_content_view_mode'
      || !$entity instanceof Paragraph
      || $entity->bundle() !== 'node_reference_bundle') {
      return;
    }

    PbRenderArray::attachLibraries($element, 'paragraph_bundle_node_reference/pb-conditional-fields');
  }

}
