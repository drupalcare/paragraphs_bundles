<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paragraphs_bundles_ai\Service\SectionComposerInterface;
use Drupal\paragraphs_bundles_ai\Tool\SafeContextTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a section with its words already in it, and its children nested.
 *
 * THE TOOL THAT MAKES THE ANSWER LOOK LIKE THE REQUEST. "A slideshow with
 * three slides about baby food" is one sentence and, before this, four tool
 * calls that ended in four empty boxes. Here it is one call: the parent, its
 * title, and a child per slide with its own words.
 *
 * IT NEVER TOUCHES AN EXISTING SECTION. There is no paragraph id in its
 * arguments — it creates new ones and fills those. That is the add-only rule,
 * expressed as an absent parameter rather than a check that could be skipped.
 *
 * @package Drupal\paragraphs_bundles_ai\Plugin\AiFunctionCall
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[FunctionCall(
  id: 'pb_content:compose_section',
  function_name: 'pb_compose_section',
  name: 'Create a section with its content and children',
  description: 'Creates one section, fills its fields with the text you give, and nests child sections inside it — all in one call. Call pb_describe_bundle first so the field names are real. Adds to an existing page as a new revision, or creates a new unpublished page. Never publishes, never changes a section that already exists.',
  group: 'pb_content',
  module_dependencies: ['paragraphs_bundles'],
  context_definitions: [
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Paragraph bundle'),
      description: 'Machine name of the section to create, e.g. slideshow_bundle.',
      required: TRUE,
    ),
    'values' => new ContextDefinition(
      data_type: 'map',
      label: new TranslatableMarkup('Field values'),
      description: 'Field machine name => value for this section, e.g. {"pb_content_title": "Autumn range"}. Use the names pb_describe_bundle reported. Styling fields are refused.',
      required: FALSE,
    ),
    'children' => new ContextDefinition(
      data_type: 'list',
      label: new TranslatableMarkup('Child sections'),
      description: 'Sections to nest inside, each {"bundle": "slideshow_section_bundle", "values": {"pb_content_slideshow_title": "Slide one"}}. Omit when the section holds nothing.',
      required: FALSE,
    ),
    'child_field' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Which field the children go in'),
      description: 'Only needed when the section has more than one field that holds sections, such as two_columns. pb_describe_bundle lists them.',
      required: FALSE,
    ),
    'content_type' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Content type'),
      description: 'Machine name of the content type. Required for a new page; ignored when node_id is given.',
      required: FALSE,
    ),
    'field' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Paragraph field on the page'),
      description: 'Machine name of the page field to add to. Omit when the content type has exactly one.',
      required: FALSE,
    ),
    'title' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Page title'),
      description: 'Title for a new page. Required unless node_id is given. Ask the person; do not invent one.',
      required: FALSE,
    ),
    'node_id' => new ContextDefinition(
      data_type: 'integer',
      label: new TranslatableMarkup('Existing page'),
      description: 'Node id to add this section to. Omit to create a new unpublished page.',
      required: FALSE,
    ),
  ],
)]
final class ComposeSection extends FunctionCallBase implements ExecutableFunctionCallInterface {

  use SafeContextTrait;

  /**
   * Builds and fills the section.
   */
  protected SectionComposerInterface $composer;

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
    $instance->composer = $container->get(SectionComposerInterface::class);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $bundle = $this->string('bundle');

    if ($bundle === '') {
      $this->setOutput(
        'Which section should I create? Call pb_list_bundles to see what this site has, '
        . 'then pb_describe_bundle to learn its field names before filling it.',
      );

      return;
    }

    $values = $this->structure('values');
    $children = array_values($this->structure('children'));
    $nodeId = $this->contextValue('node_id');

    $result = $this->composer->compose(
      $this->string('content_type') ?: NULL,
      $this->string('field') ?: NULL,
      $bundle,
      $values,
      array_filter($children, is_array(...)),
      $this->string('child_field') ?: NULL,
      $this->string('title'),
      is_numeric($nodeId) ? (int) $nodeId : NULL,
    );

    if (!$result->created) {
      $lines = ['Nothing was created:'];

      foreach ($result->refusals as $refusal) {
        $lines[] = '  ' . $refusal;
      }

      $lines[] = 'Fix these and call again. Do not retry unchanged.';

      $this->setOutput(implode("\n", $lines));

      return;
    }

    $lines = [];

    $lines[] = $result->isNewNode
      ? sprintf('Created a new unpublished page with a %s section, at /node/%d/edit.', $bundle, (int) $result->nodeId)
      : sprintf('Added a %s section to node %d, at /node/%d/edit.', $bundle, (int) $result->nodeId, (int) $result->nodeId);

    if ($result->revisionId !== NULL) {
      $lines[] = sprintf('This is revision %d — the previous one is intact if you want it back.', $result->revisionId);
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

  /**
   * A structured context value, however the model sent it.
   *
   * WHY THIS ACCEPTS A STRING TOO. The `map` and `list` data types are
   * converted for us when the model returns real JSON structures, and models
   * routinely return the same argument as a JSON *string* instead. Accepting
   * both costs three lines; refusing the string form would produce a refusal
   * the person cannot act on, about an argument they never typed.
   *
   * @param string $name
   *   The context name.
   *
   * @return array<string|int, mixed>
   *   The structure, or an empty array when absent or unreadable.
   */
  private function structure(string $name): array {
    $value = $this->contextValue($name);

    if (is_array($value)) {
      return $value;
    }

    if (is_string($value) && trim($value) !== '') {
      $decoded = json_decode($value, TRUE);

      return is_array($decoded) ? $decoded : [];
    }

    return [];
  }

}
