<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paragraphs_bundles_ai\Service\SectionWriterInterface;
use Drupal\paragraphs_bundles_ai\Tool\SafeContextTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates paragraph sections — the only tool here that changes anything.
 *
 * It re-runs every check `pb_resolve_target` makes, so calling that first is a
 * courtesy to the person (they get the question sooner) rather than a
 * safeguard. Skipping it cannot produce a bad write.
 *
 * @package Drupal\paragraphs_bundles_ai\Plugin\AiFunctionCall
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[FunctionCall(
  id: 'pb_content:create_sections',
  function_name: 'pb_create_sections',
  name: 'Create paragraph sections',
  description: 'Creates empty paragraph sections on a new unpublished page, or adds them to an existing page as a new revision. Call pb_resolve_target first. Never publishes anything and never replaces existing sections unless explicitly told to.',
  group: 'pb_content',
  module_dependencies: ['paragraphs_bundles'],
  context_definitions: [
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Paragraph bundle'),
      description: 'Machine name of the section to create, e.g. slideshow_bundle.',
      required: TRUE,
    ),
    'content_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content type'),
      description: 'Machine name of the content type. Required for a new page; ignored when node_id is given.',
      required: FALSE,
    ),
    'field' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Paragraph field'),
      description: 'Machine name of the field to write into. Omit when the content type has exactly one.',
      required: FALSE,
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Page title'),
      description: 'Title for a new page. Required unless node_id is given. Ask the person; do not invent one.',
      required: FALSE,
    ),
    'count' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('How many'),
      description: 'How many sections to create. Defaults to 1.',
      required: FALSE,
    ),
    'node_id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Existing page'),
      description: 'Node id to add sections to. Omit to create a new unpublished page.',
      required: FALSE,
    ),
    'replace' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Replace existing sections'),
      description: 'Only set this when the person explicitly said to replace what is already there. Defaults to appending.',
      required: FALSE,
    ),
  ],
)]
final class CreateSections extends FunctionCallBase implements ExecutableFunctionCallInterface {

  use SafeContextTrait;

  /**
   * Writes the sections.
   */
  protected SectionWriterInterface $writer;

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
    $instance->writer = $container->get(SectionWriterInterface::class);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $bundle = $this->string('bundle');

    if ($bundle === '') {
      $this->setOutput(
        'Which section should I create? Call pb_resolve_target if you are not sure what this site has.',
      );

      return;
    }

    $count = $this->contextValue('count');
    $nodeId = $this->contextValue('node_id');
    // Only an explicit TRUE replaces. Anything else — absent, null, a string
    // the model invented — appends.
    $replace = $this->contextValue('replace') === TRUE;

    $result = $this->writer->write(
      $this->string('content_type') ?: NULL,
      $this->string('field') ?: NULL,
      $bundle,
      is_numeric($count) ? (int) $count : 1,
      $this->string('title'),
      is_numeric($nodeId) ? (int) $nodeId : NULL,
      $replace,
    );

    if (!$result->created) {
      $lines = ['Nothing was created:'];
      foreach ($result->refusals as $refusal) {
        $lines[] = '  ' . $refusal;
      }

      $this->setOutput(implode("\n", $lines));

      return;
    }

    $lines = [];

    if ($result->isNewNode) {
      $lines[] = sprintf(
        'Created a new page with %d %s section(s), at /node/%d/edit.',
        $result->sections,
        $bundle,
        (int) $result->nodeId,
      );
    }
    else {
      $lines[] = sprintf(
        $replace
          ? 'Replaced the sections on node %3$d with %1$d %2$s section(s), at /node/%4$d/edit.'
          : 'Added %1$d %2$s section(s) to node %3$d, at /node/%4$d/edit.',
        $result->sections,
        $bundle,
        (int) $result->nodeId,
        (int) $result->nodeId,
      );
      $lines[] = sprintf(
        'This is revision %d — the previous one is intact if you want it back.',
        (int) $result->revisionId,
      );
    }

    foreach ($result->notes as $note) {
      $lines[] = $note;
    }

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
