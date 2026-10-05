<?php

/**
 * @file
 * Update paths for Paragraphs Bundles AI.
 *
 * 🚨 `config/install` IS NOT AN INSTALLER FOR A SITE THAT ALREADY HAS THE
 * MODULE. Core reads that directory exactly once, when the module is installed,
 * and never again. So shipping a NEW config file in an existing module gives it
 * to fresh installs only — every site that enabled the module earlier gets
 * nothing, reports no error, and simply behaves as though the feature does not
 * exist.
 *
 * Measured on a test site, 2026-09-19, which is how this was found: the briefs
 * panel showed the paragraphs type description instead of the brief, and
 * `\Drupal::config('paragraphs_bundles_ai.briefs')->isNew()` was TRUE while the
 * file sat correctly on disk. The maintainer saw it as a bug in the panel. The
 * panel was right; the config had never been created.
 *
 * ⚠ A post_update is recorded BY NAME once it has run, and never runs again on
 * that site. Editing the body of a hook that has already run changes nothing
 * anywhere. A later change needs a NEW function with a new name.
 */

declare(strict_types=1);

/**
 * Installs the best-practice briefs on sites that already had this module.
 *
 * @return string
 *   What happened, for the person running the update.
 */
function paragraphs_bundles_ai_post_update_install_briefs(): string {
  $name = 'paragraphs_bundles_ai.briefs';

  // Nothing to do on a fresh install: the config installer already created it
  // from the same file. Saying so is better than silently doing nothing.
  if (!\Drupal::config($name)->isNew()) {
    return (string) t('The briefs were already installed; nothing changed.');
  }

  // installDefaultConfig() creates every config/install object that does NOT
  // already exist and leaves the rest alone, so this cannot overwrite a site's
  // own presets or settings. That is why it is used here rather than reading
  // the YAML and saving it: core owns the schema check, the dependency check
  // and the language handling, and a hand-rolled save skips all three.
  \Drupal::service('config.installer')->installDefaultConfig('module', 'paragraphs_bundles_ai');

  $briefs = \Drupal::config($name)->get('briefs');
  $count = is_array($briefs) ? count($briefs) : 0;

  if ($count === 0) {
    return (string) t('The briefs could not be installed. Check that @file is present and valid.', [
      '@file' => 'config/install/' . $name . '.yml',
    ]);
  }

  return (string) \Drupal::translation()->formatPlural(
    $count,
    'Installed the best-practice brief for 1 section.',
    'Installed the best-practice briefs for @count sections.',
  );
}
