<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The shipped agent, and whether it points at tools that exist.
 *
 * WHY THIS FILE EXISTS. A FunctionCall plugin reaches an assistant only through
 * ai_agents — ai_assistant_api builds its list from `actions_enabled`, which is
 * a different plugin type entirely. MEASURED on sts.local 2026-09-05: this
 * module shipped three working tools and no agent, so nothing on a site that
 * installed it could call any of them. The agent config is what makes the
 * module usable, which makes it worth testing rather than trusting.
 *
 * A tool id in that file that no longer matches a plugin id is drift that
 * produces an agent which loads, looks configured, and silently cannot do what
 * it advertises. Nothing else catches it: the YAML is valid, the agent
 * installs, and the failure only appears when a person asks.
 *
 * @group paragraphs_bundles
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class AgentConfigTest extends KernelTestBase {

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
    'node',
    'file',
    'entity_reference_revisions',
    'paragraphs',
    'key',
    'ai',
    // REQUIRED, and finding that out is why this test earns its place. All
    // three tool plugins declare module_dependencies: ['paragraphs_bundles'],
    // so without the parent module installed the plugin manager offers none of
    // them and the agent's whole tool list reads as drift. That filtering is
    // correct — the tools are meaningless without the bundles — but a test
    // environment that omits it reports a false alarm.
    'paragraphs_bundles',
    'paragraphs_bundles_ai',
  ];

  /**
   * The modules this site has, scanned once.
   *
   * @var array<\Drupal\Core\Extension\Extension>|null
   */
  private static ?array $availableModules = NULL;

  /**
   * The parsed agent configuration.
   *
   * @var array<string, mixed>
   */
  private array $agent;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // Without drupal/ai there is no function-call plugin manager, so "does
    // this tool exist" has no answer here. drupal/ai is a dependency of this
    // submodule and deliberately not of the project, so it is absent whenever
    // paragraphs_bundles is built from its own composer.json.
    if (self::$availableModules === NULL) {
      // FALSE disables the file cache, which needs a prefix that only exists
      // once a site has booted — and this runs before parent::setUp().
      self::$availableModules = (new ExtensionDiscovery(DRUPAL_ROOT, FALSE))->scan('module');
    }

    if (!isset(self::$availableModules['ai'])) {
      $this->markTestSkipped(
        'drupal/ai is not installed, so the agent\'s tool list cannot be checked here.',
      );
    }

    parent::setUp();

    $path = sprintf(
      '%s/config/optional/ai_agents.ai_agent.paragraphs_bundles_agent.yml',
      \Drupal::service('extension.list.module')->getPath('paragraphs_bundles_ai'),
    );

    self::assertFileExists($path, 'The module ships a default agent, or its tools reach nobody.');
    $parsed = Yaml::decode((string) file_get_contents($path));
    self::assertIsArray($parsed);
    $this->agent = $parsed;
  }

  /**
   * Every tool the agent claims is a plugin that actually exists.
   */
  public function testEveryToolTheAgentListsExists(): void {
    $manager = \Drupal::service('plugin.manager.ai.function_calls');
    $available = array_keys($manager->getDefinitions());
    $listed = array_keys(array_filter($this->agent['tools'] ?? []));

    self::assertNotEmpty($listed, 'An agent with no tools is an agent that does nothing.');

    foreach ($listed as $id) {
      self::assertContains($id, $available, sprintf('The agent lists "%s", which is not a plugin.', $id));
    }
  }

  /**
   * It ships every tool the module has, not a subset that drifted.
   */
  public function testItShipsEveryToolTheModuleHas(): void {
    $manager = \Drupal::service('plugin.manager.ai.function_calls');
    $ours = array_filter(
      array_keys($manager->getDefinitions()),
      static fn(string $id): bool => str_starts_with($id, 'pb_content:'),
    );
    $listed = array_keys(array_filter($this->agent['tools'] ?? []));

    sort($ours);
    sort($listed);
    self::assertSame($ours, $listed, 'A tool the module ships but the agent omits is unreachable.');
  }

  /**
   * Every listed tool has settings, because ai_agents reads them per tool.
   */
  public function testEveryToolHasItsSettings(): void {
    foreach (array_keys(array_filter($this->agent['tools'] ?? [])) as $id) {
      self::assertArrayHasKey($id, $this->agent['tool_settings'] ?? [], sprintf('No settings for "%s".', $id));
    }
  }

  /**
   * The prompt carries the rules a model cannot work out for itself.
   */
  public function testThePromptCarriesTheRulesThatMatter(): void {
    $prompt = (string) ($this->agent['system_prompt'] ?? '');

    self::assertMatchesRegularExpression(
      '/unpublished/i',
      $prompt,
      'Nothing is published; a person told otherwise thinks it failed.',
    );
    self::assertMatchesRegularExpression(
      '/pb_resolve_target FIRST/i',
      $prompt,
      'The order is the safety model, and it has to be stated.',
    );
  }

  /**
   * It is enforced-dependent on this module, so it is removed with it.
   */
  public function testItIsTiedToThisModule(): void {
    self::assertSame(
      ['paragraphs_bundles_ai'],
      $this->agent['dependencies']['enforced']['module'] ?? [],
      'Without an enforced dependency the agent outlives the module that defines its tools.',
    );
  }

}
