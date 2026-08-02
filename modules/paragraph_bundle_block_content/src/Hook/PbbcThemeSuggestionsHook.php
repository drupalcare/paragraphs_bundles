<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_block_content\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Adds paragraph template suggestion for block_content_bundle.
 */
final class PbbcThemeSuggestionsHook {

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
    if ($paragraph->bundle() === 'block_content_bundle') {
      $suggestions[] = 'paragraph__block_content_bundle';
    }
  }

}
