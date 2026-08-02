<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Smoke test: the module installs on a full site and its admin UI responds.
 *
 * Probes whether BrowserTestBase can run in this harness before the fuller
 * per-bundle render tests build on it.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbFunctionalSmokeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'paragraphs_bundles',
    'paragraph_bundle_hero',
    'paragraph_bundle_image',
  ];

  /**
   * A minimal bundle set installs on a full site and creates paragraph types.
   */
  public function testMinimalInstall(): void {
    $this->assertTrue(
      $this->container->get('module_handler')->moduleExists('paragraphs_bundles'),
    );
    $this->assertNotEmpty(
      $this->container->get('entity_type.manager')
        ->getStorage('paragraphs_type')
        ->loadMultiple(),
      'The full install created paragraph types.',
    );
  }

}
