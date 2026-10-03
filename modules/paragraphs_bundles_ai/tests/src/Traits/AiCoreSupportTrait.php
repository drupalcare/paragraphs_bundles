<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Traits;

/**
 * Skips a test on a Drupal major this sub-module does not support.
 *
 * Paragraphs_bundles_ai declares `core_version_requirement: ^11.3` (see its
 * info.yml for why: drupal/ai has no Drupal 12 release). A Kernel or
 * Functional test that lists the module in `$modules` therefore cannot even
 * boot on Drupal 12 — core stops it with "module 'paragraphs_bundles_ai' is
 * incompatible with this version of Drupal core", and a run on a Drupal 12 site
 * reports dozens of errors that say nothing about the code.
 *
 * Call skipUnlessCoreSupportsAi() as the FIRST line of setUp(), before
 * parent::setUp(): it is parent::setUp() that installs the modules and throws.
 * Widen the check the day the info.yml line is widened — one place each.
 */
trait AiCoreSupportTrait {

  /**
   * Marks the test skipped when running on Drupal 12 or later.
   */
  protected function skipUnlessCoreSupportsAi(): void {
    if ((int) explode('.', \Drupal::VERSION)[0] >= 12) {
      $this->markTestSkipped('paragraphs_bundles_ai declares core ^11.3; skipped on Drupal ' . \Drupal::VERSION . '.');
    }
  }

}
