<?php

/**
 * @file
 * Post-update functions for paragraph_bundle_hero.
 */

declare(strict_types=1);

/**
 * Adds the SVG artwork field from the theme's pb-svg-icons folder.
 */
function paragraph_bundle_hero_post_update_svg_field(): string {
  // Existing sites get what new installs get from config/optional: the field
  // storage and field, the SVG picker right after the picture field in its tab,
  // and no view component (the template prints the SVG). Guarded steps, so a
  // second run changes nothing.
  \Drupal::moduleHandler()->loadInclude('paragraphs_bundles', 'install');
  $done = [];
  $done[] = paragraphs_bundles_svg_field_install('paragraph_bundle_hero', 'hero_bundle', 'pb_content_svg_hero', 'pb_content_image');
  return implode(' ', $done);
}
