<?php

/**
 * @file
 * Post-update functions for paragraph_bundle_carousel.
 */

declare(strict_types=1);

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
