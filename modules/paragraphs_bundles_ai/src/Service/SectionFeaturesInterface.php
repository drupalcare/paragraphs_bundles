<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\paragraphs_bundles_ai\Model\BundleProfile;

/**
 * The features a new section is switched on with, and how to say so.
 *
 * Maintainer, 2026-09-27: *"the user can get message after we finish that we
 * activated these features but can be disabled or changed, but if you dont
 * show the user the capablities of each bundle they will never know."*
 */
interface SectionFeaturesInterface {

  /**
   * The briefs' features for a section, held to its real display fields.
   *
   * A value is kept only when the field exists on the Display tab and the
   * value is one it can hold: an allowed key for a list, on or off for a
   * boolean, a whole number for an integer. Anything else — a colour, a date,
   * free text — is left out; those are a person's choice for one page.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\BundleProfile $profile
   *   The section.
   *
   * @return array<string, bool|int|string>
   *   Display field machine name => value, ready for the composer.
   */
  public function valuesFor(BundleProfile $profile): array;

  /**
   * Each feature as a person reads it: field label, then the chosen option.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\BundleProfile $profile
   *   The section.
   * @param array<string, bool|int|string> $values
   *   What valuesFor() returned.
   *
   * @return list<string>
   *   For example `Bottom Navigation: Dots with Play/Pause button`.
   */
  public function describe(BundleProfile $profile, array $values): array;

}
