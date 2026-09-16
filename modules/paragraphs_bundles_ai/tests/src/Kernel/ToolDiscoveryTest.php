<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Kernel;

use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Every tool is discoverable, and answers when called with nothing.
 *
 * WHY THIS EXISTS, and why it was the last thing missing here. The three
 * services behind these tools each had a full passing test class —
 * SectionWriterTest, FieldDrafterTest, TargetResolverTest — and the plugins in
 * front of them had none. Service tests cannot catch the two failures that
 * matter most, because both live in the gap between the service and the plugin
 * manager:
 *
 *   - a service that works perfectly while NO model can reach it, because the
 *     plugin is undiscoverable or renamed;
 *   - a tool that throws instead of refusing when the model leaves an argument
 *     out.
 *
 * The sibling modules utilikit_ai and vvj_core_ai have both halves. This one
 * did not, which is the whole reason it is here.
 *
 * THE SECOND HALF IS THE SafeContextTrait GUARANTEE.
 * `Context::getContextValue()` throws when a required context has no value, and
 * `FunctionCallBase::populateValues()` only sets the arguments the model
 * actually returned. So an omitted argument makes the first read throw from
 * inside execute(), bypassing the refusal the tool was written to give — and a
 * model omitting an argument is one of the most ordinary things it does.
 *
 * @group paragraphs_bundles
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class ToolDiscoveryTest extends KernelTestBase {

  /**
   * Every tool this module ships: function name => plugin id.
   *
   * Written out rather than derived from the plugin manager. A test that asks
   * the manager what exists and then checks that it exists passes on an empty
   * set, which is precisely the failure it is supposed to catch.
   *
   * @var array<string, string>
   */
  private const TOOLS = [
    'pb_resolve_target' => 'pb_content:resolve_target',
    'pb_draft_field_text' => 'pb_content:draft_field_text',
    'pb_create_sections' => 'pb_content:create_sections',
    'pb_list_bundles' => 'pb_content:list_bundles',
    'pb_describe_bundle' => 'pb_content:describe_bundle',
    'pb_compose_section' => 'pb_content:compose_section',
  ];

  /**
   * Modules present on this site, scanned once before the container exists.
   *
   * @var array<\Drupal\Core\Extension\Extension>|null
   */
  private static ?array $availableModules = NULL;

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
    // Required: all three plugins declare module_dependencies on
    // paragraphs_bundles, so without it the manager offers none of them.
    'paragraphs_bundles',
    'paragraphs_bundles_ai',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // drupal/ai is a dependency of this submodule and deliberately not of the
    // project, so it is absent whenever paragraphs_bundles is built from its
    // own composer.json — which is what drupal.org CI does. The check has to
    // run before parent::setUp(), which is what installs $modules, so the
    // container does not exist yet and ExtensionDiscovery is the only option.
    if (self::$availableModules === NULL) {
      self::$availableModules = (new ExtensionDiscovery(DRUPAL_ROOT, FALSE))->scan('module');
    }

    if (!isset(self::$availableModules['ai'])) {
      $this->markTestSkipped(
        'drupal/ai is not installed, so the function-call plugin manager does not '
        . 'exist and tool discovery cannot be checked here.',
      );
    }

    parent::setUp();
  }

  /**
   * Every tool is registered under the id and function name it claims.
   */
  public function testEveryToolIsDiscoverable(): void {
    $definitions = \Drupal::service('plugin.manager.ai.function_calls')->getDefinitions();

    foreach (self::TOOLS as $functionName => $pluginId) {
      self::assertArrayHasKey($pluginId, $definitions, sprintf(
        'The %s tool is not discoverable, so no model can call it.',
        $pluginId,
      ));

      $definition = $definitions[$pluginId];
      self::assertSame(
        $functionName,
        $this->definitionValue($definition, 'function_name'),
        $pluginId . ' is registered under a different function name than it declares.',
      );
      self::assertSame(
        'pb_content',
        $this->definitionValue($definition, 'group'),
        $pluginId . ' is not in the pb_content group, so the agent will not gather it.',
      );
    }
  }

  /**
   * A tool called with no arguments at all refuses in words rather than throws.
   */
  public function testEveryToolAnswersWhenCalledWithNothing(): void {
    foreach (self::TOOLS as $pluginId) {
      $tool = $this->tool($pluginId);

      $tool->execute();
      $output = (string) $tool->getReadableOutput();

      self::assertNotSame('', trim($output), sprintf(
        '%s returned nothing when called with no arguments. A model that omits '
        . 'an argument must get a refusal it can read, not silence.',
        $pluginId,
      ));
      self::assertGreaterThan(40, strlen($output), sprintf(
        '%s answered too tersely for a model to act on.',
        $pluginId,
      ));
    }
  }

  /**
   * Instantiates a tool and proves it is executable.
   */
  private function tool(string $pluginId): ExecutableFunctionCallInterface {
    $tool = \Drupal::service('plugin.manager.ai.function_calls')->createInstance($pluginId);

    self::assertInstanceOf(
      ExecutableFunctionCallInterface::class,
      $tool,
      $pluginId . ' cannot be executed.',
    );

    return $tool;
  }

  /**
   * Reads a key from a plugin definition, which may be an array or an object.
   */
  private function definitionValue(mixed $definition, string $key): mixed {
    if (is_array($definition)) {
      return $definition[$key] ?? NULL;
    }

    return is_object($definition) ? ($definition->{$key} ?? NULL) : NULL;
  }

}
