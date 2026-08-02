<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_link\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Adds paragraph template suggestion for link_bundle.
 */
final class PblThemeSuggestionsHook {

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
    if ($paragraph->bundle() === 'link_bundle') {
      $suggestions[] = 'paragraph__link_bundle';
    }
    if ($paragraph->bundle() === 'link_section_bundle') {
      $suggestions[] = 'paragraph__link_section_bundle';
    }
  }

}
