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
 * This enables each one whose dependency is actually present and reports the
 * rest, so a run that covers less says so instead of looking complete.
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

    if ($skipped !== []) {
      // Not a failure: a site without the contrib is a supported shape. But
      // the run must not read as full coverage when it is not.
      fwrite(STDERR, sprintf(
        "\n[%s] optional bundle modules not covered: %s\n",
        static::class,
        implode(', ', $skipped),
      ));
    }

    return $skipped;
  }

}
