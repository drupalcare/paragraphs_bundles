<?php

/**
 * @file
 * Post update functions for paragraph_bundle_alert.
 */

declare(strict_types=1);

use Drupal\Core\Entity\Display\EntityDisplayInterface;

/**
 * Adds the Show state label option to the alert.
 *
 * Off by default: existing alerts hold no value and keep the state word
 * visually hidden, so their markup does not change.
 */
function paragraph_bundle_alert_post_update_show_state_label(): string {
  $bundle = 'alert_bundle';
  $field = 'pb_display_alert_show_state_lbl';
  $em = \Drupal::entityTypeManager();
  if ($em->getStorage('paragraphs_type')->load($bundle) === NULL) {
    return (string) t('No alert paragraph type; nothing to do.');
  }

  \Drupal::moduleHandler()->loadInclude('paragraphs_bundles', 'install');
  paragraphs_bundles_import_optional_config_if_missing('paragraph_bundle_alert', 'field.storage.paragraph.' . $field);
  paragraphs_bundles_import_optional_config_if_missing('paragraph_bundle_alert', 'field.field.paragraph.' . $bundle . '.' . $field);

  $form = $em->getStorage('entity_form_display')->load('paragraph.' . $bundle . '.default');
  if ($form instanceof EntityDisplayInterface && $form->getComponent($field) === NULL) {
    $form->setComponent($field, [
      'type' => 'boolean_checkbox',
      'weight' => 36,
      'region' => 'content',
      'settings' => ['display_label' => TRUE],
      'third_party_settings' => [],
    ]);
    // Display tab, "Additional" group, as its last item.
    $group = $form->getThirdPartySetting('field_group', 'group_pb_display_extra');
    if (is_array($group) && is_array($group['children'] ?? NULL) && !in_array($field, $group['children'], TRUE)) {
      $group['children'][] = $field;
      $form->setThirdPartySetting('field_group', 'group_pb_display_extra', $group);
    }
    $form->save();
  }

  // The template reads the value; the field itself never prints.
  $view = $em->getStorage('entity_view_display')->load('paragraph.' . $bundle . '.default');
  if ($view instanceof EntityDisplayInterface) {
    $hidden = $view->get('hidden');
    if ($view->getComponent($field) !== NULL || !is_array($hidden) || !isset($hidden[$field])) {
      $view->removeComponent($field)->save();
    }
  }

  return (string) t('Show state label option added to the alert.');
}
