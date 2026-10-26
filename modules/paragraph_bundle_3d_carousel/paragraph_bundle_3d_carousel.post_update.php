<?php

/**
 * @file
 * Post-update functions for paragraph_bundle_3d_carousel.
 */

declare(strict_types=1);

use Drupal\Core\Entity\Display\EntityDisplayInterface;

/**
 * Adds the section title (title, tag, alignment) to the 3D carousel.
 *
 * Every other bundle already had one. Existing carousels hold no title, and
 * an empty title prints nothing, so their markup does not change.
 */
function paragraph_bundle_3d_carousel_post_update_section_title(): string {
  $bundle = '3d_carousel_bundle';
  $em = \Drupal::entityTypeManager();
  if ($em->getStorage('paragraphs_type')->load($bundle) === NULL) {
    return (string) t('No 3D carousel paragraph type; nothing to do.');
  }

  \Drupal::moduleHandler()->loadInclude('paragraphs_bundles', 'install');
  paragraphs_bundles_import_optional_config_if_missing('paragraphs_bundles', 'field.storage.paragraph.pb_content_title');
  paragraphs_bundles_ensure_pb_title_storages();
  paragraphs_bundles_import_optional_config_if_missing('paragraph_bundle_3d_carousel', 'field.field.paragraph.' . $bundle . '.pb_content_title');
  paragraphs_bundles_pb_title_import_instances_for_bundle($bundle, 'paragraph_bundle_3d_carousel');

  // The shared wiring helper only places tag and alignment AFTER an existing
  // title widget, so the title widget itself goes in first. A site that
  // already placed it keeps its own settings.
  $form = $em->getStorage('entity_form_display')->load('paragraph.' . $bundle . '.default');
  if ($form instanceof EntityDisplayInterface && $form->getComponent('pb_content_title') === NULL) {
    $form->setComponent('pb_content_title', [
      'type' => 'string_textfield',
      'weight' => 1,
      'region' => 'content',
      'settings' => ['size' => 60, 'placeholder' => ''],
      'third_party_settings' => [],
    ])->save();
  }

  $view = $em->getStorage('entity_view_display')->load('paragraph.' . $bundle . '.default');
  if ($view instanceof EntityDisplayInterface && $view->getComponent('pb_content_title') === NULL) {
    $view->setComponent('pb_content_title', [
      'type' => 'string',
      'label' => 'hidden',
      'settings' => ['link_to_entity' => FALSE],
      'third_party_settings' => [],
      'weight' => 17,
      'region' => 'content',
    ])->save();
  }

  // Tag/alignment widgets after the title, title fields first in the Content
  // tab, tag/alignment hidden on the view display.
  paragraphs_bundles_pb_title_wire_bundle($bundle);

  return (string) t('3D carousel section title added.');
}
