<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paragraphs_bundles_ai\Service\BundleProfilerInterface;
use Drupal\paragraphs_bundles_ai\Tool\SafeContextTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists the sections this site actually has.
 *
 * WHY A CATALOGUE COMES BEFORE EVERYTHING ELSE. Which sections exist depends
 * on which Paragraphs Bundles submodules a site installed, so "hero" is a word
 * the person used, not a machine name a model may assume. This turns their
 * word into a real machine name — or shows that nothing here matches it, which
 * is the honest answer and the one that stops an invented bundle reaching a
 * write.
 *
 * @package Drupal\paragraphs_bundles_ai\Plugin\AiFunctionCall
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[FunctionCall(
  id: 'pb_content:list_bundles',
  function_name: 'pb_list_bundles',
  name: 'List the paragraph sections on this site',
  description: 'Lists every section type this site has, with its machine name, what it is called, whether it holds child sections and whether it has a title. Call this to turn what a person asked for into a real machine name. It never changes anything.',
  group: 'pb_content',
  module_dependencies: ['paragraphs_bundles'],
  context_definitions: [
    'matching' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Filter'),
      description: 'Optional word to filter by, matched against the machine name and the label, e.g. "card" or "slide". Omit to list everything.',
      required: FALSE,
    ),
    'holds_sections' => new ContextDefinition(
      data_type: 'boolean',
      label: new TranslatableMarkup('Only sections that nest'),
      description: 'Set TRUE to list only sections that can hold other sections inside them.',
      required: FALSE,
    ),
  ],
)]
final class ListBundles extends FunctionCallBase implements ExecutableFunctionCallInterface {

  use SafeContextTrait;

  /**
   * Answers what each bundle is made of.
   */
  protected BundleProfilerInterface $profiler;

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
    $instance->profiler = $container->get(BundleProfilerInterface::class);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $filter = mb_strtolower($this->string('matching'));
    $nestingOnly = $this->contextValue('holds_sections') === TRUE;

    $profiles = $this->profiler->all();
    $lines = [];

    foreach ($profiles as $id => $profile) {
      if ($nestingOnly && !$profile->nests()) {
        continue;
      }

      if ($filter !== ''
        && !str_contains(mb_strtolower($id), $filter)
        && !str_contains(mb_strtolower($profile->label), $filter)) {
        continue;
      }

      $notes = [];

      if ($profile->nests()) {
        $notes[] = 'holds sections';
      }

      if ($profile->titleField === NULL) {
        $notes[] = 'no title';
      }

      if (!$profile->hasDisplayTab) {
        $notes[] = 'styled by its parent';
      }

      $lines[] = sprintf(
        '  %s — %s%s',
        $id,
        $profile->label,
        $notes === [] ? '' : ' (' . implode('; ', $notes) . ')',
      );
    }

    if ($lines === []) {
      // An empty result and a site with no sections are different problems and
      // need different answers, so they get different sentences.
      $this->setOutput($profiles === []
        ? 'This site has no paragraph sections at all — no Paragraphs Bundles submodule is enabled.'
        : sprintf(
          'None of the %d sections on this site match "%s". Call this again without a filter to see them all, and use the person\'s words to pick one rather than inventing a name.',
          count($profiles),
          $this->string('matching'),
        ));

      return;
    }

    array_unshift(
      $lines,
      sprintf('%d section(s)%s:', count($lines), $filter === '' ? '' : ' matching "' . $this->string('matching') . '"'),
    );

    $lines[] = '';
    $lines[] = 'Call pb_describe_bundle with one of these machine names before filling it.';

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
