<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_webform\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Adds paragraph template suggestion for webform_bundle.
 */
final class PbwThemeSuggestionsHook {

  /**
   * Implements hook_theme_suggestions_paragraph_alter().
   *
   * @param array<int, string> $suggestions
   *   Theme suggestions.
   * @param array<string, mixed> $variables
   *   Theme variables.
   */
  #[Hook('theme_suggestions_paragraph_alter')]
  public function themeSuggestionsParagraphAlter(array &$suggestions, array $variables): void {
    $paragraph = $variables['elements']['#paragraph'] ?? NULL;
    if (!$paragraph instanceof ParagraphInterface) {
      return;
    }
    if ($paragraph->bundle() === 'webform_bundle') {
      $suggestions[] = 'paragraph__webform_bundle';
    }
  }

}
