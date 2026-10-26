<?php

/**
 * @file
 * Post-update functions for paragraph_bundle_card.
 */

declare(strict_types=1);

use Drupal\Core\Entity\Display\EntityDisplayInterface;
use Drupal\field\FieldConfigInterface;

/**
 * Adds "Link display" (title link or button under the body) to card text.
 *
 * Existing cards hold no value, and an empty value links the title as
 * before, so their markup does not change. The card link also gains an
 * optional link text field, used as the button label.
 */
function paragraph_bundle_card_post_update_card_link_mode(): string {
  $bundle = 'card_text_section_bundle';
  $field = 'pb_display_card_link_mode';
  $em = \Drupal::entityTypeManager();
  if ($em->getStorage('paragraphs_type')->load($bundle) === NULL) {
    return (string) t('No card text section paragraph type; nothing to do.');
  }

  \Drupal::moduleHandler()->loadInclude('paragraphs_bundles', 'install');
  paragraphs_bundles_import_optional_config_if_missing('paragraph_bundle_card', 'field.storage.paragraph.' . $field);
  paragraphs_bundles_import_optional_config_if_missing('paragraph_bundle_card', 'field.field.paragraph.' . $bundle . '.' . $field);

  // Form: the option after "Open title link in new tab" in the Content tab.
  // A site that already placed the widget keeps its own settings.
  $form = $em->getStorage('entity_form_display')->load('paragraph.' . $bundle . '.default');
  if ($form instanceof EntityDisplayInterface && $form->getComponent($field) === NULL) {
    $form->setComponent($field, [
      'type' => 'options_select',
      'weight' => 7,
      'region' => 'content',
      'settings' => [],
      'third_party_settings' => [],
    ]);
    $group = $form->getThirdPartySetting('field_group', 'group_pb_tab_content');
    if (is_array($group) && !in_array($field, $group['children'] ?? [], TRUE)) {
      $group['children'][] = $field;
      $form->setThirdPartySetting('field_group', 'group_pb_tab_content', $group);
    }
    $form->save();
  }

  // View: a behaviour setting, never printed as a field.
  $view = $em->getStorage('entity_view_display')->load('paragraph.' . $bundle . '.default');
  if ($view instanceof EntityDisplayInterface && $view->getComponent($field) !== NULL) {
    $view->removeComponent($field)->save();
  }

  // The link's own text becomes the button label: allow it (optional) where
  // the site still has the shipped "no link text" setting.
  $link = $em->getStorage('field_config')->load('paragraph.' . $bundle . '.pb_content_link');
  if ($link instanceof FieldConfigInterface && (int) $link->getSetting('title') === 0) {
    $link->setSetting('title', 1)->save();
  }
  paragraphs_bundles_pb_adopt_shipped_property(
    'field.field.paragraph.' . $bundle . '.pb_content_link',
    'description',
    'Add a link here if you would like the title to be clickable.',
  );

  return (string) t('Card text section: "Link display" option added.');
}
