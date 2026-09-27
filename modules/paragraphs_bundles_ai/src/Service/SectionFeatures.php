<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs_bundles_ai\Model\BundleProfile;

/**
 * Default features: the briefs' list, filtered to what the field can hold.
 */
final class SectionFeatures implements SectionFeaturesInterface {

  use StringTranslationTrait;

  /**
   * Constructs the service.
   *
   * @param \Drupal\paragraphs_bundles_ai\Service\BriefsInterface $briefs
   *   The one owner of the features list.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $fieldManager
   *   Supplies each list field's option labels, for the note.
   */
  public function __construct(
    private readonly BriefsInterface $briefs,
    private readonly EntityFieldManagerInterface $fieldManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function valuesFor(BundleProfile $profile): array {
    $out = [];

    foreach ($this->briefs->featuresFor($profile->bundle) as $name => $value) {
      $fact = $profile->field($name);

      // Not on this site's bundle, or not a display setting: not a feature.
      if ($fact === NULL || $fact->tab !== 'display' || $fact->holdsSections) {
        continue;
      }

      if ($fact->allowedValues !== []) {
        // Compared as strings: a list_integer's keys arrive as ints.
        if (in_array($value, array_map('strval', $fact->allowedValues), TRUE)) {
          $out[$name] = $value;
        }

        continue;
      }

      if ($fact->type === 'boolean' && in_array($value, ['0', '1', 'true', 'false'], TRUE)) {
        $out[$name] = in_array($value, ['1', 'true'], TRUE);

        continue;
      }

      if ($fact->type === 'integer' && preg_match('/^-?\d+$/', $value) === 1) {
        $out[$name] = (int) $value;
      }
    }

    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function describe(BundleProfile $profile, array $values): array {
    $definitions = $this->fieldManager->getFieldDefinitions('paragraph', $profile->bundle);
    $out = [];

    foreach ($values as $name => $value) {
      $fact = $profile->field($name);
      $definition = $definitions[$name] ?? NULL;

      if ($fact === NULL || $definition === NULL) {
        continue;
      }

      if (is_bool($value)) {
        $shown = $value ? $this->t('on') : $this->t('off');
      }
      elseif ($fact->allowedValues !== []) {
        $options = options_allowed_values($definition->getFieldStorageDefinition());
        $shown = $options[$value] ?? $value;
      }
      else {
        $shown = $value;
      }

      $out[] = (string) $this->t('@label: @value', ['@label' => $fact->label, '@value' => (string) $shown]);
    }

    return $out;
  }

}
