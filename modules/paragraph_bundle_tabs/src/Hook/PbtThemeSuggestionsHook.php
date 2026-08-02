<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_tabs\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Adds paragraph template suggestions for tabs bundles.
 */
final class PbtThemeSuggestionsHook {

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
    $bundle = $paragraph->bundle();
    if ($bundle === 'tabs_bundle' || $bundle === 'tab_section_bundle') {
      $suggestions[] = 'paragraph__' . $bundle;
    }
  }

}
