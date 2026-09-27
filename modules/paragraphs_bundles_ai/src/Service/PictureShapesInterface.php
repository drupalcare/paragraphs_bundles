<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

/**
 * Says what shape a picture field is shown at.
 *
 * Maintainer, 2026-09-27: *"the place holder must be informed is this a 16:9
 * or card 4:3 max-width max-height"*. Measured the same day, PB's slots are
 * neither: wide crops to 1700x600, card to 900x540 (5:3), narrow to a square.
 * So the answer is READ from how the field renders, never written down: a
 * site that restyles its cards gets its own numbers.
 */
interface PictureShapesInterface {

  /**
   * The largest crop a picture field is shown at.
   *
   * Follows the field's formatter on the paragraph's default view display: an
   * image style, a responsive image style (its largest mapped style), or a
   * media view mode that uses either. A style that scales without cropping, an
   * original-size picture, or a formatter this cannot read has no fixed shape.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   * @param string $field
   *   The picture field's machine name.
   *
   * @return array{0: int, 1: int}|null
   *   Width and height in pixels, or NULL when any shape will do.
   */
  public function shapeFor(string $bundle, string $field): ?array;

}
