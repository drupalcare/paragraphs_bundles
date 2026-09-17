<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\paragraphs_bundles_ai\Model\BundleProfile;

/**
 * The named looks a person can apply, and what each one means per bundle.
 *
 * This is how styling becomes safe without a contrast checker in the code: a
 * person wrote the values once and checked them once, so nothing at runtime is
 * deciding what colour anything should be.
 */
interface StylePresetsInterface {

  /**
   * Every preset, for a dropdown.
   *
   * @return array<string, string>
   *   Preset id => label.
   */
  public function options(): array;

  /**
   * What a preset means for one bundle.
   *
   * Values are filtered to the fields that bundle actually has, and to the
   * keys a list field actually accepts — so a preset written once applies to
   * all 35 sections with a Display tab, and a section missing a field simply
   * does not get that value.
   *
   * @param string $preset
   *   Preset id.
   * @param \Drupal\paragraphs_bundles_ai\Model\BundleProfile $profile
   *   The bundle it is being applied to.
   *
   * @return array<string, mixed>
   *   Field machine name => value. Empty when the preset is unknown.
   */
  public function valuesFor(string $preset, BundleProfile $profile): array;

}
