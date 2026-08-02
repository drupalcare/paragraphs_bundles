<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Css;

use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Builds the per-paragraph dynamic CSS string.
 *
 * In v1 the same logic lived inline in `paragraph--*.html.twig`:
 * an eight-key map (pb-bg / pb-bg-h / pb-br / pb-br-h / pb-tx / pb-tx-h /
 * pb-lk / pb-lk-h) was rendered onto the inner wrapper as
 * `style="--pb-bg:rgba(...);"`.
 *
 * v2 collects the same values in PHP, scopes them to a class-based
 * selector that's universal across every paragraph bundle, and emits
 * an un-layered CSS block. The selector pattern is decoupled from any
 * specific bundle's bespoke outer id (`pb__nf-`, `pb__caro-wrap-`,
 * `pb__slide-wrap-`, etc. all coexist in v1) so the same builder
 * works uniformly for every paragraph type without lookups.
 */
interface PbDynamicCssBuilderInterface {

  /**
   * Build the CSS source for a single paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity.
   *
   * @return string
   *   CSS source ready to write to disk. Empty string when the
   *   paragraph has no display values that need a per-paragraph file.
   */
  public function build(ParagraphInterface $paragraph): string;

  /**
   * Build the CSS source for a pb_content node page background.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return string
   *   CSS source scoped to `#node-{id}`. Empty when no background is set.
   */
  public function buildForNode(NodeInterface $node): string;

  /**
   * Build inline `style=""` custom properties for simple single-element scopes.
   *
   * Used when one DOM node can carry `--pb-*` directly (e.g. image overlay
   * container) instead of a per-paragraph dynamic CSS rule block.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity.
   * @param list<string> $include
   *   Custom-property short names to emit (`pb-bg`, `pb-tx`, …).
   *
   * @return string
   *   Semicolon-separated declarations without a trailing semicolon, or
   *   empty when none of the included keys have field values.
   */
  public function buildInlineCustomPropertiesStyle(
    ParagraphInterface $paragraph,
    array $include,
  ): string;

}
