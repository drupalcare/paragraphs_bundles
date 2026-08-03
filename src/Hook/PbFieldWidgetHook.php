<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Hook;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs_bundles\Utility\PbRenderArray;

/**
 * Attaches PB admin/widget libraries once per request when a PB field renders.
 *
 * Paragraph subforms are often AJAX-loaded; node edit is not an admin route.
 * Widget-level #attached on every color field duplicates libraries (Drupal
 * dedupes, but clutters the render array). This hook runs when the first
 * color_text_widget or range_number_widget on a paragraph or pb_content
 * node field is built.
 */
final class PbFieldWidgetHook {

  /**
   * PB field widget plugin IDs that need admin assets.
   *
   * @var list<string>
   */
  private const array PB_WIDGET_PLUGIN_IDS = [
    'color_text_widget',
    'range_number_widget',
  ];

  /**
   * Node field names that use PB display widgets on pb_content forms.
   *
   * @var list<string>
   */
  private const array PB_CONTENT_NODE_FIELDS = [
    'pb_display_content_bg',
    'pb_display_content_bg_opacity',
  ];

  /**
   * Libraries to attach once per request when a PB widget is on the form.
   *
   * @var list<string>
   */
  private const array PARAGRAPH_WIDGET_LIBRARIES = [
    'paragraphs_bundles/paragraphs-bundles-admin',
    'paragraphs_bundles/color-picker',
    'paragraphs_bundles/opacity-range',
  ];

  /**
   * Whether admin/widget libraries were attached this request.
   */
  private static bool $librariesAttached = FALSE;

  /**
   * Implements hook_field_widget_single_element_form_alter().
   *
   * @param array<string, mixed> $element
   *   Field widget form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array<string, mixed> $context
   *   Widget context (form, widget, items, delta, default).
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(
    array &$element,
    FormStateInterface $form_state,
    array $context,
  ): void {
    if (self::$librariesAttached) {
      return;
    }
    if (!$this->isPbWidget($context)) {
      return;
    }
    PbRenderArray::attachLibraries($element, ...self::PARAGRAPH_WIDGET_LIBRARIES);
    self::$librariesAttached = TRUE;
  }

  /**
   * Whether the widget context is a PB display widget that needs admin assets.
   *
   * @param array<string, mixed> $context
   *   Widget alter context.
   *
   * @return bool
   *   TRUE when libraries should attach.
   */
  private function isPbWidget(array $context): bool {
    $widget = $context['widget'] ?? NULL;
    if (!$widget instanceof WidgetInterface) {
      return FALSE;
    }
    if (!in_array($widget->getPluginId(), self::PB_WIDGET_PLUGIN_IDS, TRUE)) {
      return FALSE;
    }
    $items = $context['items'] ?? NULL;
    if (!$items instanceof FieldItemListInterface) {
      return FALSE;
    }
    $definition = $items->getFieldDefinition();
    $entity_type = $definition->getTargetEntityTypeId();
    if ($entity_type === 'paragraph') {
      return TRUE;
    }
    if ($entity_type === 'node') {
      return in_array($definition->getName(), self::PB_CONTENT_NODE_FIELDS, TRUE);
    }
    return FALSE;
  }

}
