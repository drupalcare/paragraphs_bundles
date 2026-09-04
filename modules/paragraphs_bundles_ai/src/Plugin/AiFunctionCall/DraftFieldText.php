<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paragraphs_bundles_ai\Service\FieldDrafterInterface;
use Drupal\paragraphs_bundles_ai\Tool\SafeContextTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Says what a section can hold, and fits drafted text to it.
 *
 * Two jobs, one tool, because they are two halves of the same question. Called
 * with a bundle alone it reports the fields and their limits; called with a
 * draft as well it returns that draft fitted to the field named. Neither half
 * saves anything.
 *
 * @package Drupal\paragraphs_bundles_ai\Plugin\AiFunctionCall
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[FunctionCall(
  id: 'pb_content:draft_field_text',
  function_name: 'pb_draft_field_text',
  name: 'Draft text for a section field',
  description: 'Reports the text fields a paragraph section holds, with their character limits, and fits a draft to one of them. Call this BEFORE writing any text for a section: field names are chosen by the site builder and cannot be guessed. Returns text for a person to review — it never saves anything.',
  group: 'pb_content',
  module_dependencies: ['paragraphs_bundles'],
  context_definitions: [
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Paragraph bundle'),
      description: 'Machine name of the section, e.g. slideshow_bundle.',
      required: TRUE,
    ),
    'field' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Field'),
      description: 'Machine name of the field to draft for. Omit to list what the section holds.',
      required: FALSE,
    ),
    'draft' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Draft text'),
      description: 'The text you have written for that field. It comes back fitted to the field\'s limit, with a note if it had to be shortened.',
      required: FALSE,
    ),
  ],
)]
final class DraftFieldText extends FunctionCallBase implements ExecutableFunctionCallInterface {

  use SafeContextTrait;

  /**
   * Reads field constraints and fits text to them.
   */
  protected FieldDrafterInterface $drafter;

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
    $instance->drafter = $container->get(FieldDrafterInterface::class);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $bundle = $this->string('bundle');

    if ($bundle === '') {
      $this->setOutput('Which section? Give the paragraph bundle machine name.');

      return;
    }

    $field = $this->string('field');
    $draft = $this->string('draft');

    if ($draft !== '' && $field !== '') {
      $this->setOutput($this->fitted($bundle, $field, $draft));

      return;
    }

    $this->setOutput($this->inventory($bundle, $draft));
  }

  /**
   * The fields a section holds.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   * @param string $draft
   *   A draft supplied without a field, if any.
   *
   * @return string
   *   The readable answer.
   */
  private function inventory(string $bundle, string $draft): string {
    $fields = $this->drafter->draftableFields($bundle);

    if ($fields === NULL) {
      return sprintf(
        'There is no paragraph type "%s" on this site. Its module may not be enabled.',
        $bundle,
      );
    }

    if ($fields === []) {
      // Distinct from an unknown bundle, and the distinction is the whole
      // answer: this section is real, it just is not made of text.
      return sprintf(
        'The section %s holds no text fields — it is made of something else, such as images or references. There is nothing to draft.',
        $bundle,
      );
    }

    $lines = [sprintf('The section %s holds:', $bundle)];

    foreach ($fields as $definition) {
      $lines[] = '  ' . $definition->line();
    }

    $lines[] = $draft === ''
      ? 'Draft text for the ones the person asked for, then call this again with field and draft to have it checked against the limit.'
      : 'A draft was supplied without naming a field. Say which of the fields above it is for and call this again.';

    return implode("\n", $lines);
  }

  /**
   * A draft, fitted to its field.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   * @param string $field
   *   Field machine name.
   * @param string $draft
   *   The text.
   *
   * @return string
   *   The readable answer.
   */
  private function fitted(string $bundle, string $field, string $draft): string {
    $result = $this->drafter->fit($bundle, $field, $draft);

    if (!$result->usable) {
      $lines = ['No draft:'];

      foreach ($result->refusals as $refusal) {
        $lines[] = '  ' . $refusal;
      }

      return implode("\n", $lines);
    }

    $lines = [sprintf('Draft for %s:', $result->field), '', $result->text, ''];

    foreach ($result->notes as $note) {
      $lines[] = $note;
    }

    // Said on every path, not only when something went wrong. A tool that
    // reports its limits only on failure teaches a caller to assume success
    // means the text landed somewhere.
    $lines[] = 'Nothing has been saved — show this to the person and let them place it.';

    return implode("\n", $lines);
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
