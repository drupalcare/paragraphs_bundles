<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Kernel;

use Drupal\Component\Plugin\PluginManagerInterface;
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
   * The tool ids the agent switches on.
   *
   * @var list<string>
   */
  private array $toolIds = [];

  /**
   * The tool ids that have a settings block.
   *
   * @var list<string>
   */
  private array $settingsKeys = [];

  /**
   * The agent's system prompt.
   */
  private string $prompt = '';

  /**
   * The modules the agent config is enforced-dependent on.
   *
   * @var list<string>
   */
  private array $enforcedModules = [];

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

    $this->toolIds = self::enabledKeys($parsed['tools'] ?? NULL);
    $this->settingsKeys = self::stringKeys($parsed['tool_settings'] ?? NULL);

    $prompt = $parsed['system_prompt'] ?? '';
    $this->prompt = is_string($prompt) ? $prompt : '';

    $dependencies = $parsed['dependencies'] ?? NULL;
    $enforced = is_array($dependencies) ? ($dependencies['enforced'] ?? NULL) : NULL;
    $this->enforcedModules = self::stringList(is_array($enforced) ? ($enforced['module'] ?? NULL) : NULL);
  }

  /**
   * Every tool the agent claims is a plugin that actually exists.
   */
  public function testEveryToolTheAgentListsExists(): void {
    $available = array_keys($this->toolManager()->getDefinitions());

    self::assertNotEmpty($this->toolIds, 'An agent with no tools is an agent that does nothing.');

    foreach ($this->toolIds as $id) {
      self::assertContains($id, $available, sprintf('The agent lists "%s", which is not a plugin.', $id));
    }
  }

  /**
   * It ships every tool the module has, not a subset that drifted.
   */
  public function testItShipsEveryToolTheModuleHas(): void {
    $ours = [];

    foreach (array_keys($this->toolManager()->getDefinitions()) as $id) {
      if (is_string($id) && str_starts_with($id, 'pb_content:')) {
        $ours[] = $id;
      }
    }

    $listed = $this->toolIds;
    sort($ours);
    sort($listed);
    self::assertSame($ours, $listed, 'A tool the module ships but the agent omits is unreachable.');
  }

  /**
   * Every listed tool has settings, because ai_agents reads them per tool.
   */
  public function testEveryToolHasItsSettings(): void {
    foreach ($this->toolIds as $id) {
      self::assertContains($id, $this->settingsKeys, sprintf('No settings for "%s".', $id));
    }
  }

  /**
   * The prompt carries the rules a model cannot work out for itself.
   */
  public function testThePromptCarriesTheRulesThatMatter(): void {
    $prompt = $this->prompt;

    self::assertMatchesRegularExpression(
      '/unpublished/i',
      $prompt,
      'Nothing is published; a person told otherwise thinks it failed.',
    );
    // THE ORDER CHANGED ON 2026-09-16, AND SO DID THIS ASSERTION. It used to
    // require the words "pb_resolve_target FIRST". That stopped being true
    // when pb_list_bundles and pb_describe_bundle were added in front of it:
    // a model cannot resolve a target for a section whose machine name it had
    // to invent, so discovery now comes first and resolution third.
    //
    // The assertion follows the RULE rather than the wording — the prompt must
    // state an order, and discovery must come before building — so that a
    // future reshuffle that drops the ordering entirely still fails here.
    self::assertMatchesRegularExpression(
      '/ORDER MATTERS/i',
      $prompt,
      'The order is the safety model, and it has to be stated.',
    );

    $discovery = strpos($prompt, 'pb_list_bundles');
    $building = strpos($prompt, 'pb_compose_section');

    self::assertIsInt($discovery, 'The prompt must name the tool that finds what this site has.');
    self::assertIsInt($building, 'The prompt must name the tool that builds.');
    self::assertLessThan(
      $building,
      $discovery,
      'Discovery is instructed before building, or the model builds with names it invented.',
    );

    // The add-only promise. A model that believes it can rewrite an existing
    // section will offer to, and there is no tool behind the offer.
    self::assertMatchesRegularExpression(
      '/cannot change a section that already exists/i',
      $prompt,
      'The prompt must say plainly what it cannot do, or it will promise it.',
    );
  }

  /**
   * It is enforced-dependent on this module, so it is removed with it.
   */
  public function testItIsTiedToThisModule(): void {
    self::assertSame(
      ['paragraphs_bundles_ai'],
      $this->enforcedModules,
      'Without an enforced dependency the agent outlives the module that defines its tools.',
    );
  }

  /**
   * The plugin manager, narrowed through a core interface.
   *
   * \Drupal::service() returns object, and drupal/ai's own manager class
   * cannot be named here — it is absent whenever the project is built from its
   * own composer.json, which is what drupal.org CI does. PluginManagerInterface
   * is core, is always resolvable, and declares getDefinitions() through
   * DiscoveryInterface, so this narrows the type honestly instead of hiding the
   * problem behind an exclusion.
   *
   * @return \Drupal\Component\Plugin\PluginManagerInterface
   *   The function-call plugin manager.
   */
  private function toolManager(): PluginManagerInterface {
    $manager = \Drupal::service('plugin.manager.ai.function_calls');

    if (!$manager instanceof PluginManagerInterface) {
      self::fail('The function-call plugin manager is not a plugin manager.');
    }

    return $manager;
  }

  /**
   * The ids of every tool switched on, as strings.
   *
   * Decoded YAML is mixed all the way down. Walking it with real checks costs
   * a few lines and means nothing downstream has to be asserted about.
   *
   * @param mixed $value
   *   The decoded `tools` mapping, or anything else.
   *
   * @return list<string>
   *   The enabled ids.
   */
  private static function enabledKeys(mixed $value): array {
    $out = [];

    if (is_array($value)) {
      foreach ($value as $key => $enabled) {
        if (is_string($key) && $enabled) {
          $out[] = $key;
        }
      }
    }

    return $out;
  }

  /**
   * Every key of a decoded mapping, as strings.
   *
   * @param mixed $value
   *   The decoded mapping, or anything else.
   *
   * @return list<string>
   *   The keys.
   */
  private static function stringKeys(mixed $value): array {
    $out = [];

    if (is_array($value)) {
      foreach (array_keys($value) as $key) {
        if (is_string($key)) {
          $out[] = $key;
        }
      }
    }

    return $out;
  }

  /**
   * A decoded list, as strings.
   *
   * @param mixed $value
   *   The decoded list, or anything else.
   *
   * @return list<string>
   *   The strings in it.
   */
  private static function stringList(mixed $value): array {
    $out = [];

    if (is_array($value)) {
      foreach ($value as $item) {
        if (is_string($item)) {
          $out[] = $item;
        }
      }
    }

    return $out;
  }

}
