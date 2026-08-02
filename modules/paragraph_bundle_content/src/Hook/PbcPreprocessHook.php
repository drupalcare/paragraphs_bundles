<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_content\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Drupal\paragraphs_bundles\Service\Css\PbCssFileManagerInterface;
use Drupal\paragraphs_bundles\Service\Css\PbDynamicCssBuilder;

/**
 * Preprocess hook — exposes pb_content node dynamic library to Twig.
 */
final class PbcPreprocessHook {

  public function __construct(
    private readonly PbCssFileManagerInterface $cssFiles,
  ) {}

  /**
   * Implements hook_preprocess_node().
   *
   * @param array<string, mixed> $variables
   *   Render variables (passed by reference by the dispatcher).
   */
  #[Hook('preprocess_node')]
  public function preprocessNode(array &$variables): void {
    $node = $variables['node'] ?? NULL;
    if (!$node instanceof NodeInterface || $node->bundle() !== 'pb_content') {
      return;
    }
    $slug = PbDynamicCssBuilder::NODE_PB_CONTENT_SLUG;
    $id = (int) $node->id();
    $variables['pb_dynamic_library'] = $this->cssFiles->exists($slug, $id)
      ? 'paragraphs_bundles/dynamic--' . $slug . '-' . $id
      : NULL;
  }

}
