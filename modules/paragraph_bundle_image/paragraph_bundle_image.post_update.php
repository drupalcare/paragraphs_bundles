<?php

/**
 * @file
 * Post-update functions for paragraph_bundle_image.
 */

declare(strict_types=1);

use Drupal\Core\Entity\Display\EntityDisplayInterface;

/**
 * Adds the Title Position field (above or below the picture).
 *
 * Existing image sections hold no value, and an empty value prints the title
 * above the picture as before, so their markup does not change.
 */
function paragraph_bundle_image_post_update_title_position(): string {
  $em = \Drupal::entityTypeManager();
  $types = $em->getStorage('paragraphs_type');
  $bundles = array_values(array_filter(
    ['image_bundle', 'image_narrow_bundle', 'image_wide_bundle'],
    static fn(string $bundle): bool => $types->load($bundle) !== NULL,
  ));
  if ($bundles === []) {
    return (string) t('No image paragraph types; nothing to do.');
  }

  \Drupal::moduleHandler()->loadInclude('paragraphs_bundles', 'install');
  paragraphs_bundles_import_optional_config_if_missing('paragraph_bundle_image', 'field.storage.paragraph.pb_content_title_position');

  foreach ($bundles as $bundle) {
    paragraphs_bundles_import_optional_config_if_missing('paragraph_bundle_image', 'field.field.paragraph.' . $bundle . '.pb_content_title_position');

    $form = $em->getStorage('entity_form_display')->load('paragraph.' . $bundle . '.default');
    if ($form instanceof EntityDisplayInterface && $form->getComponent('pb_content_title_position') === NULL) {
      // The shipped Content tab is title (1), tag (2), alignment (3), then
      // the picture fields from 4. Move those down one only where the site
      // still has the shipped weights; a site that reordered keeps its order.
      $shipped = ['pb_content_image' => 4, 'pb_content_image_link' => 5, 'pb_content_image_link_title' => 6];
      $untouched = TRUE;
      foreach ($shipped as $name => $weight) {
        $component = $form->getComponent($name);
        if ($component !== NULL && (int) ($component['weight'] ?? 0) !== $weight) {
          $untouched = FALSE;
        }
      }
      if ($untouched) {
        foreach ($shipped as $name => $weight) {
          $component = $form->getComponent($name);
          if ($component !== NULL) {
            $form->setComponent($name, ['weight' => $weight + 1] + $component);
          }
        }
      }
      $form->setComponent('pb_content_title_position', [
        'type' => 'options_select',
        'weight' => 4,
        'region' => 'content',
        'settings' => [],
        'third_party_settings' => [],
      ]);

      // Put it in the Content tab, right after the title alignment.
      $groups = $form->getThirdPartySettings('field_group');
      $tab = $groups['group_pb_tab_content'] ?? NULL;
      if (is_array($tab) && is_array($tab['children'] ?? NULL) && !in_array('pb_content_title_position', $tab['children'], TRUE)) {
        $children = $tab['children'];
        $at = array_search('pb_content_title_alignment', $children, TRUE);
        $at = $at === FALSE ? count($children) : (int) $at + 1;
        array_splice($children, $at, 0, ['pb_content_title_position']);
        $tab['children'] = $children;
        $form->setThirdPartySetting('field_group', 'group_pb_tab_content', $tab);
      }
      $form->save();
    }

    // The template reads the value; the field itself never prints.
    $view = $em->getStorage('entity_view_display')->load('paragraph.' . $bundle . '.default');
    if ($view instanceof EntityDisplayInterface) {
      $hidden = $view->get('hidden');
      if ($view->getComponent('pb_content_title_position') !== NULL || !is_array($hidden) || !isset($hidden['pb_content_title_position'])) {
        $view->removeComponent('pb_content_title_position')->save();
      }
    }
  }

  return (string) t('Title Position field added to: @list.', ['@list' => implode(', ', $bundles)]);
}
