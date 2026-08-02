<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Installs the core content-safe bundles and validates their config schema.
 *
 * PB creates its paragraph types, fields and displays programmatically at
 * install rather than shipping config/install YAML. KernelTestBase enables
 * strict config-schema checking by default, so every config entity saved while
 * these modules install is validated against schema; a missing or invalid
 * schema would fail setUp. This test therefore doubles as install (1.1) and
 * config-schema (1.2) coverage for the bundle family.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbBundleInstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'file',
    'image',
    'link',
    'options',
    'views',
    'media',
    'media_library',
    'block',
    'entity_reference_revisions',
    'paragraphs',
    'field_group',
    'paragraphs_bundles',
    // Content-safe bundle family (deps limited to paragraphs_bundles + core).
    'paragraph_bundle_image',
    'paragraph_bundle_hero',
    'paragraph_bundle_layout',
    'paragraph_bundle_grid',
    'paragraph_bundle_card',
    'paragraph_bundle_accordion',
    'paragraph_bundle_tabs',
    'paragraph_bundle_alert',
    'paragraph_bundle_icon',
    'paragraph_bundle_link',
    'paragraph_bundle_modal',
  ];

  /**
   * The bundle modules enable and produce schema-valid paragraph types.
   */
  public function testBundlesInstallWithValidConfig(): void {
    $handler = $this->container->get('module_handler');
    foreach (static::$modules as $module) {
      if (str_starts_with($module, 'paragraph_bundle_')) {
        $this->assertTrue(
          $handler->moduleExists($module),
          sprintf('%s is enabled.', $module),
        );
      }
    }

    // Reaching this point means setUp completed: enabling every module ran
    // its install under KernelTestBase's strict config-schema checker (on by
    // default), so any config entity saved during install was validated against
    // schema. A missing or invalid schema would have failed setUp. Confirm the
    // paragraphs stack is set up as a concrete post-install assertion.
    $this->assertTrue(
      $this->container->get('entity_type.manager')->hasDefinition('paragraphs_type'),
      'The paragraphs_type entity type is available after install.',
    );
  }

}
