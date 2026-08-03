<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_content\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs_bundles\Utility\PbRenderArray;

/**
 * HTML preprocess — appends max-width body class from pb_content nodes.
 */
final class PbcHtmlHook {

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_preprocess_html().
   *
   * @param array<string, mixed> $variables
   *   Preprocess variables (passed by reference).
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    $node = $this->routeMatch->getParameter('node')
      ?? $this->routeMatch->getParameter('node_preview');

    if (!$node instanceof NodeInterface || $node->bundle() !== 'pb_content') {
      return;
    }
    if (!$node->hasField('pb_display_content_width') || $node->get('pb_display_content_width')->isEmpty()) {
      return;
    }

    $value = $node->get('pb_display_content_width')->value;
    if (!is_string($value) || $value === '') {
      return;
    }

    $trimmed = preg_replace('/^pb__/', '', $value);
    if (!is_string($trimmed)) {
      return;
    }
    $safe = Html::getClass($trimmed);
    PbRenderArray::addClass($variables, 'pb-' . $safe);
  }

}
