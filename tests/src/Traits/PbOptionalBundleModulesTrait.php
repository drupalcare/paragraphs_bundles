<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Traits;

/**
 * Enables the four bundle modules that need external contrib, when available.
 *
 * `paragraph_bundle_link`, `_views`, `_webform` and `_contact_form` depend on
 * contrib this project only `suggest`s, so they cannot sit in a test's static
 * `$modules` list — that would make the whole class fail on a site without
 * them. They ship five paragraph types between them, and excluding them
 * outright meant the render tests covered 34 of 39 bundles.
 *
 * This enables each one whose dependency is actually present and RETURNS the
 * rest, so a caller can tell full coverage from partial. It deliberately
 * writes nothing: see the note in enableOptionalBundleModules().
 *
 * On Drupal 12 two of the four are always skipped — `webform` and
 * `contact_formatter` have no Drupal 12 release — and that is a fact about
 * those projects, not a defect in these bundle modules.
 */
trait PbOptionalBundleModulesTrait {

  /**
   * Bundle module => the contrib module it needs.
   *
   * @var array<string, string>
   */
  private array $optionalBundleModules = [
    'paragraph_bundle_link' => 'link_attributes',
    'paragraph_bundle_views' => 'viewsreference',
    'paragraph_bundle_webform' => 'webform',
    'paragraph_bundle_contact_form' => 'contact_formatter',
  ];

  /**
   * Enables every optional bundle module whose contrib dependency is present.
   *
   * @return list<string>
   *   Bundle modules that were skipped, with the reason appended.
   */
  protected function enableOptionalBundleModules(): array {
    $list = $this->container->get('extension.list.module');
    $skipped = [];
    $enabled = 0;

    foreach ($this->optionalBundleModules as $module => $dependency) {
      if (!$list->exists($dependency)) {
        $skipped[] = $module . ' (needs ' . $dependency . ')';
        continue;
      }
      try {
        $this->enableModules([$dependency, $module]);
        $enabled++;
      }
      catch (\Throwable $e) {
        $skipped[] = $module . ' (' . $dependency . ' failed to install: ' . $e->getMessage() . ')';
      }
    }

    if ($enabled > 0) {
      // Their paragraph types and field config live in config/optional.
      $this->container->get('config.installer')->installOptionalConfig();
    }

    // WHAT USED TO BE HERE, AND WHY IT HAD TO GO. This wrote the skipped list
    // to STDERR, under a comment saying "Not a failure". Drupal's
    // phpunit.xml.dist sets `beStrictAboutOutputDuringTests="true"`, so that
    // write DID fail the test — the mechanism contradicted its own intent.
    //
    // It went unnoticed because on a site that has all four contrib modules
    // nothing is skipped and nothing is written. Measured on a Drupal 12 test site
    // 2026-09-04: `webform` declares ^10.3 || ^11.0 and `contact_formatter`
    // ^8 .. ^11, so neither installs on Drupal 12 — and both render tests
    // errored there with the skip message as the exception text, on a site
    // where the bundle modules themselves are perfectly fine.
    //
    // The list is returned instead. A caller that cares about coverage can
    // assert on it; nothing writes to test output.
    return $skipped;
  }

}
