<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\paragraphs_bundles_ai\Model\BundleProfile;

/**
 * Default presets.
 *
 * THE FILTERING IS THE FEATURE. A preset is written once, in the fields that
 * exist on all 35 sections with a Display tab, and then applied to whichever
 * section a person chose. `image_background_bundle` has no border and
 * `image_overlay_bundle` has no hover colours; they get everything else and
 * nothing is invented to fill the gap.
 *
 * A VALUE THAT THE FIELD WOULD REFUSE IS DROPPED HERE, not offered to the
 * field and left to fail on save. List fields accept only their own keys, and
 * a preset naming a key a site has since renamed would otherwise produce a
 * section that half-applied — the kind of failure that looks like the
 * assistant ignoring instructions.
 */
final class StylePresets implements StylePresetsInterface {

  /**
   * Constructs the service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Reads the shipped presets, which a site may override.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function options(): array {
    $out = [];

    foreach ($this->presets() as $id => $preset) {
      $label = $preset['label'] ?? $id;
      $out[$id] = is_string($label) ? $label : $id;
    }

    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function valuesFor(string $preset, BundleProfile $profile): array {
    $presets = $this->presets();
    $values = $presets[$preset]['values'] ?? NULL;

    if (!is_array($values)) {
      return [];
    }

    $out = [];

    foreach ($values as $name => $value) {
      $name = (string) $name;
      $fact = $profile->field($name);

      // The bundle does not have this field, or a site has moved it off the
      // Display tab. Either way it is not this preset's business.
      if ($fact === NULL || $fact->tab !== 'display' || $fact->holdsSections) {
        continue;
      }

      if ($fact->allowedValues !== [] && !in_array((string) $value, $fact->allowedValues, TRUE)) {
        continue;
      }

      $out[$name] = $value;
    }

    return $out;
  }

  /**
   * The presets as stored.
   *
   * @return array<string, array<string, mixed>>
   *   Preset id => definition.
   */
  private function presets(): array {
    $presets = $this->configFactory->get('paragraphs_bundles_ai.presets')->get('presets');

    if (!is_array($presets)) {
      return [];
    }

    $out = [];

    foreach ($presets as $id => $preset) {
      if (is_string($id) && is_array($preset)) {
        $out[$id] = $preset;
      }
    }

    return $out;
  }

}
