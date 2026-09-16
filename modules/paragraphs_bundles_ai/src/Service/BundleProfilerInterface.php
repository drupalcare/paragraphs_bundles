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

}
