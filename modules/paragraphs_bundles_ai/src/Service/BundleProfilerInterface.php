<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\paragraphs_bundles_ai\Model\BundleProfile;

/**
 * Answers what a paragraph bundle is made of.
 *
 * Every other service in this module reads its answer instead of guessing at
 * field names, so a bundle added tomorrow costs nothing and the bundles whose
 * title is not `pb_content_title` stop being silent exceptions.
 */
interface BundleProfilerInterface {

  /**
   * Profiles one bundle.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   *
   * @return \Drupal\paragraphs_bundles_ai\Model\BundleProfile|null
   *   The profile, or NULL when no such paragraph type exists on this site —
   *   a typo, or a module that is not enabled. The two are the same answer
   *   here and a different sentence in the caller.
   */
  public function profile(string $bundle): ?BundleProfile;

  /**
   * Profiles every paragraph bundle on the site.
   *
   * @return array<string, \Drupal\paragraphs_bundles_ai\Model\BundleProfile>
   *   Bundle machine name => profile, sorted by machine name.
   */
  public function all(): array;

  /**
   * The bundles a person adds to a page, as opposed to inside another section.
   *
   * THE RULE IS COMPUTED, NOT NAMED. A bundle is a CHILD when some other
   * bundle's field names it explicitly — which is a fact in config, not a
   * guess from the `_section_` naming convention. Measured 2026-09-16 on
   * a test site: 8 of the 40 installed bundles are named that way, leaving 32
   * parents, and every one of the 8 is a `*_section_bundle`. The two agree
   * today; when they disagree, config is right and the name is decoration.
   *
   * ⚠ A field that accepts ANY bundle names nothing, so it marks nothing as a
   * child. On a site where every field accepts anything, every bundle is a
   * parent — which is the honest answer, not a bug: nothing there says a slide
   * belongs inside a slideshow.
   *
   * @return array<string, \Drupal\paragraphs_bundles_ai\Model\BundleProfile>
   *   Bundle machine name => profile, sorted, children removed.
   */
  public function parents(): array;

}
