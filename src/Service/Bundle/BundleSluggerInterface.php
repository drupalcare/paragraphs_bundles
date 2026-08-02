<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Bundle;

/**
 * Converts paragraph type machine names into CSS-file-friendly slugs.
 *
 * Paragraph types in v1 use the `_bundle` suffix convention
 * (`simple_bundle`, `hero_bundle`, `card_two_columns_bundle`, etc.).
 * For dynamic CSS file naming we want short kebab-case slugs:
 *
 *   simple_bundle              → simple
 *   hero_bundle                → hero
 *   3d_carousel_bundle         → 3d-carousel
 *   card_two_columns_bundle    → card-two-columns
 *   accordion_section_bundle   → accordion-section
 *
 * The slug is appended to the file name template
 * `pb-{slug}-{paragraph_id}.css`.
 */
interface BundleSluggerInterface {

  /**
   * Convert a paragraph type machine name into a kebab-case slug.
   *
   * Strips a trailing `_bundle` suffix (if present) and replaces
   * remaining underscores with hyphens. Idempotent.
   *
   * @param string $bundle
   *   Paragraph type machine name (e.g. `simple_bundle`).
   *
   * @return string
   *   Kebab-case slug suitable for file names and CSS classes.
   */
  public function slugify(string $bundle): string;

}
