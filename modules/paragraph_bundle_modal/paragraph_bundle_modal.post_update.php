<?php

/**
 * @file
 * Post-update functions for paragraph_bundle_modal.
 */

declare(strict_types=1);

use Drupal\field\Entity\FieldConfig;

/**
 * Makes the modal panel Background Color optional, with no default.
 *
 * The field was required with a black default, so every new modal (and every
 * modal made without the form) got a black panel whatever the theme. Now an
 * empty field prints no panel colour and the theme or pattern ground applies;
 * an editor's colour still wins. Stored values are not touched: an existing
 * modal keeps the colour it holds, because a kept default cannot be told
 * apart from a black somebody chose.
 */
function paragraph_bundle_modal_post_update_panel_bg_optional(): string {
  $field = FieldConfig::loadByName('paragraph', 'modal_bundle', 'pb_display_bg');
  if (!$field instanceof FieldConfig) {
    return (string) t('No modal Background Color field on this site; nothing to do.');
  }
  if (!$field->isRequired() && $field->getDefaultValueLiteral() === []) {
    return (string) t('Modal Background Color is already optional with no default; nothing to do.');
  }
  $field->setRequired(FALSE)->setDefaultValue([])->save();
  return (string) t('Modal Background Color is now optional with no default. Stored colours are unchanged.');
}
