<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_content\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Field theme-suggestion hook for pb_content field overrides.
 */
final class PbcFieldSuggestionsHook {

  /**
   * Implements hook_theme_suggestions_field_alter().
   *
   * @param array<int, string> $suggestions
   *   Theme hook suggestions (passed by reference).
   * @param array<string, mixed> $variables
   *   Preprocess variables for the field.
   */
  #[Hook('theme_suggestions_field_alter')]
  public function themeSuggestionsFieldAlter(array &$suggestions, array $variables): void {
    $targetFields = [
      'pb_content_reference_para_block',
      'pb_content_type_body',
    ];
    $targetBundle = 'pb_content';
    $currentFieldName = $variables['element']['#field_name'] ?? '';

    if (!is_string($currentFieldName) || !in_array($currentFieldName, $targetFields, TRUE)) {
      return;
    }
    if (!isset($variables['element']['#bundle']) || $variables['element']['#bundle'] !== $targetBundle) {
      return;
    }
    $suggestions[] = 'field__' . $targetBundle . '__' . $currentFieldName;
  }

}
