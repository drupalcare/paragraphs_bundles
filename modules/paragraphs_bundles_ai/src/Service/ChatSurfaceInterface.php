<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\paragraphs_bundles_ai\Model\SurfaceDecision;

/**
 * Decides whether the node-form chat bar appears, and builds it.
 *
 * @see \Drupal\paragraphs_bundles_ai\Service\ChatSurface
 * @see docs/PB-AI-BRIEF.md §0
 */
interface ChatSurfaceInterface {

  /**
   * Permission required to see the bar at all.
   */
  public const string PERMISSION = 'use paragraphs bundles ai assistant';

  /**
   * Whether to show the bar, and what is missing when not.
   *
   * Separate from build() so the answer can be printed on the settings page.
   * Someone who turned this on and sees nothing needs the reason, and the node
   * form is the one place that cannot give it to them.
   *
   * @param string|null $contentType
   *   The node bundle whose form is being built, when there is one. A content
   *   type with no paragraph field cannot receive sections, so the bar is
   *   pointless there — passing NULL skips that check, for callers such as the
   *   settings page that are asking about the site rather than one form.
   *
   * @return \Drupal\paragraphs_bundles_ai\Model\SurfaceDecision
   *   The decision.
   */
  public function decide(?string $contentType = NULL): SurfaceDecision;

  /**
   * The render array for the chat bar.
   *
   * @param string|null $contentType
   *   The node bundle whose form is being built.
   *
   * @return array<string, mixed>
   *   A render array, or an empty array when the bar should not appear.
   */
  public function build(?string $contentType = NULL): array;

}
