<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_content\Hook;

use Drupal\block_content\BlockContentInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme suggestions for the pb_block custom block content type.
 */
final class PbcThemeSuggestionsHook {

  /**
   * Implements hook_theme_suggestions_block_alter().
   *
   * @param array<int, string> $suggestions
   *   Theme suggestions.
   * @param array<string, mixed> $variables
   *   Theme variables.
   */
  #[Hook('theme_suggestions_block_alter')]
  public function themeSuggestionsBlockAlter(array &$suggestions, array $variables): void {
    $elements = $variables['elements'] ?? NULL;
    if (!is_array($elements)) {
      return;
    }
    $content = $elements['content'] ?? NULL;
    if (!is_array($content)) {
      return;
    }

    $block_content = $content['#block_content'] ?? NULL;
    if (!$block_content instanceof BlockContentInterface) {
      return;
    }
    if ($block_content->bundle() !== 'pb_block') {
      return;
    }

    $suggestions[] = 'block__pb_block';
  }

  /**
   * Implements hook_theme_suggestions_block_content_alter().
   *
   * @param array<int, string> $suggestions
   *   Theme suggestions.
   * @param array<string, mixed> $variables
   *   Theme variables.
   */
  #[Hook('theme_suggestions_block_content_alter')]
  public function themeSuggestionsBlockContentAlter(array &$suggestions, array $variables): void {
    $elements = $variables['elements'] ?? NULL;
    $block_content = is_array($elements) ? ($elements['#block_content'] ?? NULL) : NULL;
    if (!$block_content instanceof BlockContentInterface) {
      return;
    }
    if ($block_content->bundle() !== 'pb_block') {
      return;
    }

    $suggestions[] = 'block_content__pb_block';
  }

}
