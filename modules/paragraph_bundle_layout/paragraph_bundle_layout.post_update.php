<?php

/**
 * @file
 * Post-update functions for paragraph_bundle_layout.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldStorageConfig;

/**
 * Adds the "Toolbar above the columns" toggle position to existing sites.
 *
 * New sites get the value from config/optional. Adding an allowed value is
 * allowed on a storage that holds data; no stored value changes, so every
 * existing section renders as before.
 */
function paragraph_bundle_layout_post_update_toggle_toolbar_position(): string {
  $added = [];
  foreach (['pb_display_2c_icn_pos', 'pb_display_3c_icn_pos'] as $field_name) {
    $storage = FieldStorageConfig::loadByName('paragraph', $field_name);
    if (!$storage instanceof FieldStorageConfig) {
      continue;
    }
    $values = $storage->getSetting('allowed_values');
    if (!is_array($values) || array_key_exists('toolbar', $values)) {
      continue;
    }
    $values['toolbar'] = 'Toolbar above the columns';
    $storage->setSetting('allowed_values', $values)->save();
    $added[] = $field_name;
  }
  return $added === []
    ? (string) t('Toolbar position already offered (or no column toggle fields); nothing to do.')
    : (string) t('Toolbar position added to: @list.', ['@list' => implode(', ', $added)]);
}
