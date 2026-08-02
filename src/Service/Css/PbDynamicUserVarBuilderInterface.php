<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Css;

use Drupal\paragraphs\ParagraphInterface;

/**
 * Builds per-paragraph dynamic CSS for non-colour user field values.
 *
 * Declarations are written on the same DOM nodes that carried v1 inline
 * `style=""` attributes (see USER_STYLE_SCOPES in the implementation).
 */
interface PbDynamicUserVarBuilderInterface {

  /**
   * Build CSS custom-property blocks for user-set layout/content fields.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph entity.
   * @param string $outerSelector
   *   Outer scope selector (e.g. `#pb__grid-42`).
   *
   * @return string
   *   CSS source. Empty when no user values need emission.
   */
  public function build(ParagraphInterface $paragraph, string $outerSelector): string;

}
