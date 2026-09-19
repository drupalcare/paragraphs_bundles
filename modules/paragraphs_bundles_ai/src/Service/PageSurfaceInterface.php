<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\node\NodeInterface;
use Drupal\paragraphs_bundles_ai\Model\PageDecision;

/**
 * Decides whether the assistant appears on a page a person is looking at.
 *
 * @see \Drupal\paragraphs_bundles_ai\Service\PageSurface
 */
interface PageSurfaceInterface {

  /**
   * Whether to show the assistant on this node view.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node being rendered.
   * @param string $viewMode
   *   The view mode it is being rendered in.
   *
   * @return \Drupal\paragraphs_bundles_ai\Model\PageDecision
   *   Show plus every displayed paragraph field it may write into, or hide
   *   plus the reason.
   */
  public function decide(NodeInterface $node, string $viewMode): PageDecision;

  /**
   * The paragraph fields on a node that this view mode actually renders.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $viewMode
   *   The view mode.
   *
   * @return array<string, string>
   *   Field machine name => label, for fields that are displayed.
   */
  public function displayedParagraphFields(NodeInterface $node, string $viewMode): array;

}
