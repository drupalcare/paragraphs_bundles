<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Form;

/**
 * Ensures styling fields stay hidden on paragraph view displays.
 */
final class PbViewDisplayVerifier implements PbViewDisplayVerifierInterface {

  /**
   * {@inheritdoc}
   */
  public function verify(array $viewDisplay): array {
    $errors = [];
    $content = $viewDisplay['content'] ?? [];
    if (!is_array($content)) {
      $errors[] = 'content section missing or invalid';
      return $errors;
    }

    foreach (array_keys($content) as $field) {
      if (!is_string($field) || !str_starts_with($field, 'pb_display_')) {
        continue;
      }
      $errors[] = "view display must hide {$field} (found in content)";
    }

    return $errors;
  }

}
