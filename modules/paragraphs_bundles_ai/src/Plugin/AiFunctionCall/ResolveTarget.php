<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paragraphs_bundles_ai\Service\TargetResolverInterface;
use Drupal\paragraphs_bundles_ai\Tool\SafeContextTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Works out where sections should go, or asks what is missing.
 *
 * THE TOOL THAT REFUSES. It never writes anything. Its job is to be unable to
 * answer until it has what it needs, and to say precisely what that is — which
 * is what turns "add a slideshow" into a conversation rather than a guess.
 *
 * Call this before `pb_create_sections`. That tool re-runs the same checks
 * itself and will refuse in the same words, so skipping this one costs a round
 * trip rather than correctness.
 *
 * @package Drupal\paragraphs_bundles_ai\Plugin\AiFunctionCall
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[FunctionCall(
  id: 'pb_content:resolve_target',
  function_name: 'pb_resolve_target',
  name: 'Find where paragraph sections can be added',
  description: 'Checks whether sections can be added with what you know so far, and returns the exact question to ask when they cannot. Call this first. It never changes anything.',
  group: 'pb_content',
  module_dependencies: ['paragraphs_bundles'],
  context_definitions: [
    'content_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content type'),
      description: 'Machine name of the content type, e.g. apex_landing_page. Omit if the person has not said.',
      required: FALSE,
    ),
    'field' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Paragraph field'),
      description: 'Machine name of the paragraph field to write into. Omit unless the person named one; when the content type has exactly one, it is chosen automatically.',
      required: FALSE,
    ),
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Paragraph bundle'),
      description: 'Machine name of the section to create, e.g. slideshow_bundle. Omit if not yet known.',
      required: FALSE,
    ),
    'count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('How many'),
      description: 'How many of that section to create. Defaults to 1.',
      required: FALSE,
    ),
    'operation' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Operation'),
      description: '"create" for a new page, "update" when editing an existing one. Defaults to create.',
      required: FALSE,
    ),
  ],
)]
final class ResolveTarget extends FunctionCallBase implements ExecutableFunctionCallInterface {

  use SafeContextTrait;

  /**
   * Decides where sections may go, or what blocks that.
   */
  protected TargetResolverInterface $resolver;

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.context_definition_normalizer'),
      $container->get('plugin.manager.ai_data_type_converter'),
    );
    $instance->resolver = $container->get(TargetResolverInterface::class);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $count = $this->contextValue('count');
    $count = is_numeric($count) ? (int) $count : 1;

    $operation = $this->string('operation');
    $operation = $operation === 'update' ? 'update' : 'create';

    $resolution = $this->resolver->resolve(
      $this->string('content_type') ?: NULL,
      $this->string('field') ?: NULL,
      $this->string('bundle') ?: NULL,
      // A count below 1 is not a number of sections anyone meant; treat it as
      // one rather than rejecting, and let cardinality speak for the upper
      // bound.
      max(1, $count),
      $operation,
    );

    if ($resolution->resolved) {
      // The bundle is echoed back because the next call REQUIRES it. Saying
      // "use exactly these values" while omitting one of them left the model
      // to carry it from its own earlier reasoning, which is the point in a
      // conversation where a guessed machine name gets substituted.
      //
      // The verb follows the operation too: "create" on an update path
      // described the wrong action to whoever reads the transcript.
      $this->setOutput(sprintf(
        "Ready: %s %s, field %s, section %s, %d of them.\n"
        . 'Call pb_create_sections with exactly these values.',
        $operation === 'update' ? 'add to' : 'create in',
        (string) $resolution->contentType,
        (string) $resolution->field,
        (string) $resolution->bundle,
        max(1, $count),
      ));

      return;
    }

    // Every blocker at once, so the assistant asks one combined question
    // rather than interrogating the person turn by turn.
    $lines = ['Not yet — I need to know:'];

    foreach ($resolution->questions as $question) {
      $lines[] = '  ' . $question;
    }

    $lines[] = 'Ask the person, then call this again with their answer. '
      . 'Do not guess any of it.';

    $this->setOutput(implode("\n", $lines));
  }

  /**
   * A context value as a trimmed string.
   *
   * @param string $name
   *   The context name.
   *
   * @return string
   *   The value, or '' when absent.
   */
  private function string(string $name): string {
    $value = $this->contextValue($name);

    return is_string($value) ? trim($value) : '';
  }

}
