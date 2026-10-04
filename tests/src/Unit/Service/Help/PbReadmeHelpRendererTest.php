<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit\Service\Help;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\FilterPluginManager;
use Drupal\filter\Plugin\FilterInterface;
use Drupal\paragraphs_bundles\Service\Help\PbReadmeHelpRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PbReadmeHelpRenderer.
 *
 * This is the single hook_help() implementation for the parent module and all
 * 26 bundle sub-modules, so its fallbacks are worth pinning: a missing import
 * in one of the 27 copies it replaces was a live fatal on the help page.
 */
#[CoversClass(PbReadmeHelpRenderer::class)]
#[Group('paragraphs_bundles')]
final class PbReadmeHelpRendererTest extends TestCase {

  /**
   * Directory standing in for the module directory.
   */
  private string $moduleDir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->moduleDir = sys_get_temp_dir() . '/pb-readme-' . uniqid('', TRUE);
    mkdir($this->moduleDir, 0777, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $readme = $this->moduleDir . '/README.md';
    if (is_file($readme)) {
      unlink($readme);
    }
    if (is_dir($this->moduleDir)) {
      rmdir($this->moduleDir);
    }
    parent::tearDown();
  }

  /**
   * Returns NULL for a route belonging to another module.
   */
  public function testForeignRouteReturnsNull(): void {
    $this->writeReadme('# Alert');
    $renderer = $this->createRenderer(markdownEnabled: FALSE);

    $this->assertNull($renderer->render('help.page.node', 'paragraph_bundle_alert'));
    $this->assertNull($renderer->render('entity.node.canonical', 'paragraph_bundle_alert'));
  }

  /**
   * Returns NULL when the module ships no README.
   */
  public function testMissingReadmeReturnsNull(): void {
    $renderer = $this->createRenderer(markdownEnabled: FALSE);

    $this->assertNull(
      $renderer->render('help.page.paragraph_bundle_alert', 'paragraph_bundle_alert'),
    );
  }

  /**
   * Escapes the README source when the markdown module is absent.
   */
  public function testWithoutMarkdownModuleTheSourceIsEscaped(): void {
    $this->writeReadme("# Alert\n<script>alert('xss')</script>");
    $renderer = $this->createRenderer(markdownEnabled: FALSE);

    $output = $renderer->render('help.page.paragraph_bundle_alert', 'paragraph_bundle_alert');

    $this->assertNotNull($output);
    $html = (string) $output;
    $this->assertStringStartsWith('<pre>', $html);
    $this->assertStringEndsWith('</pre>', $html);
    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringContainsString('&lt;script&gt;', $html);
  }

  /**
   * Falls back to escaped source when the markdown plugin is not a filter.
   *
   * A broken or shadowed markdown plugin must not fatal the help page.
   */
  public function testBrokenMarkdownPluginFallsBackToEscapedSource(): void {
    $this->writeReadme('# Alert <b>bold</b>');
    $renderer = $this->createRenderer(markdownEnabled: TRUE, filter: new \stdClass());

    $output = $renderer->render('help.page.paragraph_bundle_alert', 'paragraph_bundle_alert');

    $this->assertNotNull($output);
    $html = (string) $output;
    $this->assertStringStartsWith('<pre>', $html);
    $this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $html);
  }

  /**
   * Runs the README through the markdown filter when one is available.
   */
  public function testMarkdownFilterProcessesTheReadme(): void {
    $this->writeReadme('# Alert');

    $filter = $this->createMock(FilterInterface::class);
    $filter->expects($this->once())
      ->method('process')
      ->with('# Alert', 'en')
      ->willReturn(new FilterProcessResult('<h1>Alert</h1>'));

    $renderer = $this->createRenderer(markdownEnabled: TRUE, filter: $filter);

    $this->assertSame(
      '<h1>Alert</h1>',
      $renderer->render('help.page.paragraph_bundle_alert', 'paragraph_bundle_alert'),
    );
  }

  /**
   * Writes a README into the stand-in module directory.
   *
   * @param string $contents
   *   README source.
   */
  private function writeReadme(string $contents): void {
    file_put_contents($this->moduleDir . '/README.md', $contents);
  }

  /**
   * Builds the renderer with mocked dependencies.
   *
   * @param bool $markdownEnabled
   *   Whether the markdown module reports as installed.
   * @param object|null $filter
   *   Object the filter plugin manager returns for the markdown plugin.
   *
   * @return \Drupal\paragraphs_bundles\Service\Help\PbReadmeHelpRenderer
   *   Renderer under test.
   */
  private function createRenderer(bool $markdownEnabled, ?object $filter = NULL): PbReadmeHelpRenderer {
    $moduleList = $this->createStub(ModuleExtensionList::class);
    $moduleList->method('getPath')->willReturn($this->moduleDir);

    $moduleHandler = $this->createStub(ModuleHandlerInterface::class);
    $moduleHandler->method('moduleExists')->willReturnCallback(
      static fn (string $module): bool => $module === 'markdown' && $markdownEnabled,
    );

    $config = $this->createStub(ImmutableConfig::class);
    $config->method('getRawData')->willReturn([]);
    $configFactory = $this->createStub(ConfigFactoryInterface::class);
    $configFactory->method('get')->willReturn($config);

    $filterManager = $this->createStub(FilterPluginManager::class);
    if ($filter !== NULL) {
      $filterManager->method('createInstance')->willReturn($filter);
    }

    return new PbReadmeHelpRenderer(
      $moduleList,
      $moduleHandler,
      $filterManager,
      $configFactory,
    );
  }

}
