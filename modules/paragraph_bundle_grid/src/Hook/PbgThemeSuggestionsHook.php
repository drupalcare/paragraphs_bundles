<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_grid\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Adds paragraph template suggestion for grid_bundle.
 */
final class PbgThemeSuggestionsHook {

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
    if ($paragraph->bundle() === 'grid_bundle') {
      $suggestions[] = 'paragraph__grid_bundle';
    }
  }

}
