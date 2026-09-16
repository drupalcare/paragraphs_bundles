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
 * Says what one section is made of, before anything tries to fill it.
 *
 * THE TOOL THAT ENDS THE GUESSING. Measured across the 39 shipped bundles:
 * NINE different field names mean "the title" and three sections have none,
 * and TWENTY-FIVE different field names hold children. A model writing into
 * `pb_content_title` is right about 32 sections and silently wrong about the
 * rest — silently, because writing to a field that does not exist is refused
 * by the tool, while writing a sentence into a field that wanted `h2` is not.
 *
 * It reads the INSTALLED form display, so what it reports is this site, not
 * the module's shipped defaults.
 *
 * @package Drupal\paragraphs_bundles_ai\Plugin\AiFunctionCall
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[FunctionCall(
  id: 'pb_content:describe_bundle',
  function_name: 'pb_describe_bundle',
  name: 'Describe one paragraph section',
  description: 'Reports what a section holds: which field is its title, which fields take text or choices and what each accepts, which fields hold child sections, and how it is styled. Call this before writing anything into a section. It never changes anything.',
  group: 'pb_content',
  module_dependencies: ['paragraphs_bundles'],
  context_definitions: [
    'bundle' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup('Paragraph bundle'),
      description: 'Machine name of the section, e.g. slideshow_bundle. Call pb_list_bundles if you do not know it.',
      required: TRUE,
    ),
  ],
)]
final class DescribeBundle extends FunctionCallBase implements ExecutableFunctionCallInterface {

  use SafeContextTrait;

  /**
   * Answers what a bundle is made of.
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
    $bundle = $this->string('bundle');

    if ($bundle === '') {
      $this->setOutput(
        'Which section? Give the machine name, e.g. slideshow_bundle. '
        . 'Call pb_list_bundles to see what this site has.',
      );

      return;
    }

    $profile = $this->profiler->profile($bundle);

    if ($profile === NULL) {
      $this->setOutput(sprintf(
        'There is no section "%s" on this site — the machine name may be wrong, or its module is not enabled. '
        . 'Call pb_list_bundles and use a name from that list.',
        $bundle,
      ));

      return;
    }

    $lines = [$profile->line(), ''];

    $lines[] = $profile->titleField === NULL
      ? 'TITLE: this section has no title field. Do not invent one.'
      : 'TITLE: ' . $profile->titleField;

    $lines[] = '';
    $lines[] = 'FIELDS YOU CAN FILL (the Content tab):';

    foreach ($profile->contentFields as $name) {
      $fact = $profile->field($name);
      $lines[] = '  ' . ($fact === NULL ? $name : $fact->line());
    }

    if ($profile->childFields !== []) {
      $lines[] = '';
      $lines[] = 'FIELDS THAT HOLD OTHER SECTIONS:';

      foreach ($profile->childFields as $name => $accepts) {
        $lines[] = sprintf(
          '  %s — %s',
          $name,
          $accepts === [] ? 'any section' : 'only ' . implode(', ', $accepts),
        );
      }

      $lines[] = 'Use pb_compose_section to create this section together with its children in one call.';
    }

    $lines[] = '';
    $lines[] = $profile->hasDisplayTab
      ? sprintf(
        'STYLING: %d fields on the Display tab, including %s. Do not write to these unless asked to style something.',
        count($profile->displayFields),
        implode(', ', array_slice($profile->displayFields, 0, 6)),
      )
      : 'STYLING: none of its own — this section is styled by whatever holds it.';

    if ($profile->unclassified !== []) {
      $lines[] = '';
      $lines[] = 'NOT UNDERSTOOD, so left alone: ' . implode(', ', $profile->unclassified);
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
