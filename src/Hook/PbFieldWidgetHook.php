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
   * Libraries the HOST paragraphs field needs, before any PB widget exists.
   *
   * @var list<string>
   */
  private const array HOST_FIELD_LIBRARIES = [
    'paragraphs_bundles/paragraphs-bundles-admin',
  ];

  /**
   * Whether admin/widget libraries were attached this request.
   */
  private static bool $librariesAttached = FALSE;

  /**
   * Whether the host-field admin CSS was attached this request.
   *
   * Tracked SEPARATELY from $librariesAttached. With one shared flag, whichever
   * path ran first would silence the other -- attaching the dialog CSS for an
   * empty field would then stop the colour-picker and opacity JS attaching when
   * a PB widget appeared later in the same request.
   */
  private static bool $hostLibrariesAttached = FALSE;

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
   * Implements hook_field_widget_complete_form_alter().
   *
   * ⚠ This MUST be the complete-form hook, not the single-element one.
   * hook_field_widget_single_element_form_alter() fires once per DELTA, and a
   * paragraphs field with no paragraphs yet has no deltas -- so it never fires
   * on the empty node/add form, which is precisely the case being fixed here.
   * Measured: attaching from the single-element hook left the stylesheet absent
   * on node/add/para exactly as before.
   *
   * @param array<string, mixed> $field_widget_complete_form
   *   The complete widget form for one field.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array<string, mixed> $context
   *   Widget context (form, widget, items).
   */
  #[Hook('field_widget_complete_form_alter')]
  public function fieldWidgetCompleteFormAlter(
    array &$field_widget_complete_form,
    FormStateInterface $form_state,
    array $context,
  ): void {
    if (self::$hostLibrariesAttached) {
      return;
    }
    if (!$this->hostsParagraphs($context)) {
      return;
    }
    PbRenderArray::attachLibraries($field_widget_complete_form, ...self::HOST_FIELD_LIBRARIES);
    self::$hostLibrariesAttached = TRUE;
  }

  /**
   * Whether this widget is the field that hosts paragraphs on the form.
   *
   * WHY THIS EXISTS. The "Add <bundle>" modal is rendered by the paragraphs
   * widget, and the rule that lays its bundle list out as a wide grid --
   * `.paragraphs-add-dialog .paragraphs-add-dialog-list` -- lives in
   * paragraphs-bundles-admin.css. That stylesheet used to attach ONLY when a
   * PB colour or opacity widget was built, which cannot happen on a node/add
   * form that has no paragraphs yet.
   *
   * The result, measured on node/add/para: the dialog markup was on the page
   * and PB's stylesheet was not, so the FIRST modal opened as an unstyled
   * vertical list in a dialog sized to that narrow content. Adding one
   * paragraph created a PB widget, which attached the CSS, so every later open
   * looked correct -- which is exactly what made it look like a dialog bug
   * rather than a missing stylesheet.
   *
   * Matching on the FIELD (entity_reference_revisions targeting paragraph)
   * rather than on a widget plugin ID list, so this keeps working for the
   * paragraphs, entity_reference_paragraphs and legacy widgets alike.
   *
   * @param array<string, mixed> $context
   *   Widget alter context.
   *
   * @return bool
   *   TRUE when this widget hosts paragraphs and needs the admin CSS.
   */
  private function hostsParagraphs(array $context): bool {
    $items = $context['items'] ?? NULL;
    if (!$items instanceof FieldItemListInterface) {
      return FALSE;
    }
    $definition = $items->getFieldDefinition();
    if ($definition->getType() !== 'entity_reference_revisions') {
      return FALSE;
    }
    return $definition->getSetting('target_type') === 'paragraph';
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
