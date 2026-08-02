<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_carousel\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Adds paragraph template suggestion for carousel_bundle.
 */
final class PbcaThemeSuggestionsHook {

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
    if ($paragraph->bundle() === 'carousel_bundle') {
      $suggestions[] = 'paragraph__carousel_bundle';
    }
  }

}
