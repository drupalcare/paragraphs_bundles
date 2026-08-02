<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_node_reference\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Field widget hook — conditional NF reference fields by view mode.
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
    if ($items === NULL || $widget === NULL) {
      return;
    }

    if ($widget->getPluginId() !== 'options_select') {
      return;
    }

    $field_definition = $items->getFieldDefinition();
    $field_name = $field_definition->getName();
    $entity = $items->getEntity();
    if ($field_name !== 'pb_content_view_mode'
      || !$entity instanceof Paragraph
      || $entity->bundle() !== 'node_reference_bundle') {
      return;
    }

    $element['#attached']['library'][] = 'paragraph_bundle_node_reference/pb-conditional-fields';

    if (!isset($element['#field_name'], $element['subform'])) {
      return;
    }

    $base_selector = ':input[name="' . $element['#field_name'] . '"]';
    $states = [
      'vm_title' => 'pb_content_nf_title_only',
      'vm_teaser_text' => 'pb_content_nf_teaser_text',
      'vm_slideshow' => 'pb_content_nf_slideshow',
      'vm_3d_carousel' => 'pb_content_nf_3d_carousel',
    ];

    foreach ($states as $value => $target_field) {
      if (!isset($element['subform'][$target_field])) {
        continue;
      }
      $element['subform'][$target_field]['#states'] = [
        'visible' => [
          $base_selector => ['value' => $value],
        ],
      ];
    }
  }

}
