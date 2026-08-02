<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_card\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Adds paragraph template suggestions for card bundles.
 */
final class PbcardThemeSuggestionsHook {

  /**
   * Card bundle machine names.
   *
   * @var list<string>
   */
  private const array CARD_BUNDLES = [
    'card_two_columns_bundle',
    'card_three_columns_bundle',
    'card_text_section_bundle',
    'card_image_section_bundle',
  ];

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
    if (in_array($bundle, self::CARD_BUNDLES, TRUE)) {
      $suggestions[] = 'paragraph__' . $bundle;
    }
  }

}
