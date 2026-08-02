<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Smoke test: the Kernel harness boots with paragraphs available.
 *
 * Proves the test environment can discover + enable the contrib paragraphs
 * stack (installed into modules/contrib/) before the fuller install / service
 * Kernel tests build on it.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbKernelSmokeTest extends KernelTestBase {

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
    'file',
    'entity_reference_revisions',
    'paragraphs',
  ];

  /**
   * The paragraphs stack is discoverable and enables in a kernel.
   */
  public function testParagraphsStackEnabled(): void {
    $handler = $this->container->get('module_handler');
    $this->assertTrue($handler->moduleExists('paragraphs'));
    $this->assertTrue($handler->moduleExists('entity_reference_revisions'));
  }

}
