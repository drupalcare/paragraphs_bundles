<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Verifies the parent module installs with its services wired.
 *
 * Enables paragraphs_bundles with its full declared dependency set and asserts
 * the module is enabled and its core services resolve from the container.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbInstallTest extends KernelTestBase {

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
  ];

  /**
   * The module enables and its core services are available.
   */
  public function testModuleInstallsWithServices(): void {
    $this->assertTrue(
      $this->container->get('module_handler')->moduleExists('paragraphs_bundles'),
    );

    foreach ([
      'paragraphs_bundles.bundle_slugger',
      'paragraphs_bundles.css.file_manager',
      'paragraphs_bundles.css.color_normalizer',
      'paragraphs_bundles.css.dynamic_builder',
    ] as $service_id) {
      $this->assertTrue(
        $this->container->has($service_id),
        sprintf('Service %s is registered.', $service_id),
      );
    }
  }

}
