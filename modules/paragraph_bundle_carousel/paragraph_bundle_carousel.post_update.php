<?php

/**
 * @file
 * Post-update functions for paragraph_bundle_carousel.
 */

declare(strict_types=1);

use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\field\Entity\FieldConfig;

/**
 * Corrects the Activate Looping help text.
 *
 * It said looping needs autoplay. Looping has always applied to the arrows
 * too, so the text now says what the carousel does. A site that wrote its own
 * help text keeps it.
 */
function paragraph_bundle_carousel_post_update_looping_description(): string {
  $field = FieldConfig::loadByName('paragraph', 'carousel_bundle', 'pb_display_activate_looping');
  $old = 'This feature only works if you set the field "Time in seconds" number bigger than zero.';
  if ($field === NULL || $field->getDescription() !== $old) {
    return (string) t('Activate Looping help text was already changed; nothing to do.');
  }
  $field->setDescription('After the last slide, go back to the first, and before the first, to the last. Applies to the arrows and to autoplay.');
  $field->save();
  return (string) t('Activate Looping help text corrected.');
}

/**
 * Adds the Arrows Over The Slides option to the carousel.
 *
 * Off by default: existing carousels hold no value and keep their arrow lane,
 * so their markup does not change.
 */
function paragraph_bundle_carousel_post_update_arrows_over(): string {
  $bundle = 'carousel_bundle';
  $field = 'pb_display_carousel_arrows_over';
  $em = \Drupal::entityTypeManager();
  if ($em->getStorage('paragraphs_type')->load($bundle) === NULL) {
    return (string) t('No carousel paragraph type; nothing to do.');
  }

  \Drupal::moduleHandler()->loadInclude('paragraphs_bundles', 'install');
  paragraphs_bundles_import_optional_config_if_missing('paragraph_bundle_carousel', 'field.storage.paragraph.' . $field);
  paragraphs_bundles_import_optional_config_if_missing('paragraph_bundle_carousel', 'field.field.paragraph.' . $bundle . '.' . $field);

  $form = $em->getStorage('entity_form_display')->load('paragraph.' . $bundle . '.default');
  if ($form instanceof EntityDisplayInterface && $form->getComponent($field) === NULL) {
    $form->setComponent($field, [
      'type' => 'boolean_checkbox',
      'weight' => 29,
      'region' => 'content',
      'settings' => ['display_label' => TRUE],
      'third_party_settings' => [],
    ]);
    // Last item of the Display tab's "Additional" group, after looping.
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

  return (string) t('Arrows Over The Slides option added to the carousel.');
}
