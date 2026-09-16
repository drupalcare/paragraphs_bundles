<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\ai\Dto\StructuredOutputSchema;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\paragraphs_bundles_ai\Model\BundleProfile;
use Drupal\paragraphs_bundles_ai\Model\DraftedSection;

/**
 * Default drafter.
 *
 * THE SCHEMA IS THE RULE. The bundle's own fields become a JSON schema, so the
 * model answers inside a shape this site defined: the property names are the
 * real field names, a list field becomes an enum of its real keys, and a
 * character limit becomes a maxLength. A field name the model invents has
 * nowhere to go.
 *
 * WHY strict IS FALSE, and it is not laziness. A provider's strict mode
 * requires EVERY property to be listed as required, which would force the
 * model to produce a value for every field on the section — including the ones
 * the person never mentioned. A half-filled section is the honest outcome of
 * "add a hero saying we deliver on Tuesdays"; inventing an alignment and a
 * title tag to satisfy a validator is not. The answer is checked here and
 * checked again by the composer, so a loose schema costs a refusal at worst.
 *
 * IT NEVER CHOOSES A TARGET. No bundle, no node, no field comes from the
 * model: those arrive from two dropdowns and the page the person is on.
 */
final class SectionDrafter implements SectionDrafterInterface {

  /**
   * Field types this can ask for. Everything else is left alone.
   *
   * Plain text only, by decision: an image, a link or a reference needs a
   * value that exists on this site, and a model that guesses one produces
   * something that looks right and points nowhere.
   */
  private const TEXTUAL = ['string', 'string_long', 'text', 'text_long', 'list_string'];

  /**
   * Constructs the drafter.
   *
   * @param \Drupal\paragraphs_bundles_ai\Service\BundleProfilerInterface $profiler
   *   Supplies the fields the schema is built from.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Reads which provider and model the site chose, by name.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $providerManager
   *   Builds the provider. Typed through the core interface because drupal/ai
   *   is absent whenever this project is built from its own composer.json.
   */
  public function __construct(
    private readonly BundleProfilerInterface $profiler,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly PluginManagerInterface $providerManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function draft(string $bundle, string $words, int $children = 0): DraftedSection {
    $words = trim($words);

    if ($words === '') {
      return DraftedSection::refused(['Say what the section should be about.']);
    }

    $profile = $this->profiler->profile($bundle);

    if ($profile === NULL) {
      return DraftedSection::refused([sprintf('There is no section "%s" on this site.', $bundle)]);
    }

    [$providerId, $modelId] = $this->chosenProvider();

    if ($providerId === '') {
      return DraftedSection::refused([
        'No default AI provider is set for chat. Choose one at Configuration › AI › Settings.',
      ]);
    }

    $childBundle = $children > 0 ? $this->soleChildBundle($profile) : NULL;
    $schema = $this->schemaFor($profile, $childBundle, $children);

    if ($schema === []) {
      return DraftedSection::refused([
        sprintf('The section %s has no text fields to fill in.', $profile->label),
      ]);
    }

    $input = new ChatInput([new ChatMessage('user', $words)]);
    $input->setSystemPrompt($this->systemPrompt($profile, $childBundle, $children));
    $input->setChatStructuredJsonSchema(new StructuredOutputSchema(
      name: 'pb_section',
      description: sprintf('Content for one %s section.', $profile->label),
      strict: FALSE,
      json_schema: $schema,
    ));

    try {
      $provider = $this->providerManager->createInstance($providerId);
      $output = $provider->chat($input, $modelId, ['paragraphs_bundles_ai']);
      $answer = $output->getNormalized();
      $text = method_exists($answer, 'getText') ? (string) $answer->getText() : '';
    }
    catch (\Throwable $error) {
      // The provider's own message is relayed rather than replaced: "model not
      // found" and "rate limited" need different actions from the person, and
      // one generic sentence hides which one happened.
      return DraftedSection::refused([
        'The AI provider could not answer: ' . $error->getMessage(),
      ]);
    }

    $decoded = json_decode($text, TRUE);

    if (!is_array($decoded)) {
      return DraftedSection::refused([
        'The model did not answer in the shape this section needs. Try again, or say it differently.',
      ]);
    }

    return DraftedSection::of(
      $this->pickValues($profile, $decoded),
      $this->pickChildren($decoded, $childBundle),
      $modelId,
    );
  }

  /**
   * The provider and model the site chose, by name.
   *
   * @return array{0: string, 1: string}
   *   Provider id and model id, both empty when none is set.
   */
  private function chosenProvider(): array {
    $providers = $this->configFactory->get('ai.settings')->get('default_providers');
    $chat = is_array($providers) ? ($providers['chat'] ?? NULL) : NULL;

    if (!is_array($chat)) {
      return ['', ''];
    }

    $provider = is_string($chat['provider_id'] ?? NULL) ? $chat['provider_id'] : '';
    $model = is_string($chat['model_id'] ?? NULL) ? $chat['model_id'] : '';

    return [$provider, $model];
  }

  /**
   * The one bundle a section's children must be, when there is exactly one.
   *
   * Every nesting bundle PB ships that names its children names exactly one —
   * a slideshow takes slides and nothing else — so the person never has to
   * choose a child type, and neither does the model.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\BundleProfile $profile
   *   The parent.
   *
   * @return string|null
   *   The child bundle, or NULL when it is not a single obvious one.
   */
  private function soleChildBundle(BundleProfile $profile): ?string {
    if (count($profile->childFields) !== 1) {
      return NULL;
    }

    $accepts = reset($profile->childFields);

    return count($accepts) === 1 ? (string) reset($accepts) : NULL;
  }

  /**
   * Builds the JSON schema for a bundle's fillable fields.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\BundleProfile $profile
   *   The bundle.
   * @param string|null $childBundle
   *   The child bundle to draft too, or NULL.
   * @param int $children
   *   How many children to ask for.
   *
   * @return array<string, mixed>
   *   A JSON schema, or an empty array when the bundle holds no text.
   */
  private function schemaFor(BundleProfile $profile, ?string $childBundle, int $children): array {
    $properties = $this->propertiesFor($profile);

    if ($properties === [] && $childBundle === NULL) {
      return [];
    }

    if ($childBundle !== NULL && $children > 0) {
      $childProfile = $this->profiler->profile($childBundle);
      $childProperties = $childProfile === NULL ? [] : $this->propertiesFor($childProfile);

      if ($childProperties !== []) {
        $properties['children'] = [
          'type' => 'array',
          'description' => sprintf('Exactly %d child section(s), in order.', $children),
          'maxItems' => $children,
          'items' => [
            'type' => 'object',
            'properties' => $childProperties,
          ],
        ];
      }
    }

    return ['type' => 'object', 'properties' => $properties];
  }

  /**
   * The schema properties for one bundle's content fields.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\BundleProfile $profile
   *   The bundle.
   *
   * @return array<string, mixed>
   *   Property name => JSON schema fragment.
   */
  private function propertiesFor(BundleProfile $profile): array {
    $properties = [];

    foreach ($profile->contentFields as $name) {
      $fact = $profile->field($name);

      if ($fact === NULL || $fact->holdsSections) {
        continue;
      }

      if (!in_array($fact->type, self::TEXTUAL, TRUE)) {
        continue;
      }

      $property = ['type' => 'string', 'description' => $fact->label];

      // A list field's real keys, so the answer is a choice rather than a
      // guess at what "centre" might be stored as.
      if ($fact->allowedValues !== []) {
        $property['enum'] = $fact->allowedValues;
      }

      if ($fact->maxLength !== NULL) {
        $property['maxLength'] = $fact->maxLength;
      }

      $properties[$name] = $property;
    }

    return $properties;
  }

  /**
   * The system prompt: what this is, and what not to do.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\BundleProfile $profile
   *   The bundle.
   * @param string|null $childBundle
   *   The child bundle, when children are wanted.
   * @param int $children
   *   How many children.
   *
   * @return string
   *   The prompt.
   */
  private function systemPrompt(BundleProfile $profile, ?string $childBundle, int $children): string {
    $lines = [
      sprintf('You are writing the content of one "%s" section on a Drupal site.', $profile->label),
      'Answer only with the fields in the schema. Leave out any field the person did not ask for rather than inventing something to fill it.',
      'Write plain text. No HTML, no Markdown, no quotation marks around the whole value.',
      'Keep to any character limit in the schema: the field will not hold more.',
    ];

    if ($childBundle !== NULL && $children > 0) {
      $lines[] = sprintf('Also write %d child section(s) in "children", in the order they should appear.', $children);
    }

    return implode("\n", $lines);
  }

  /**
   * Keeps only the answers that name a real field on this bundle.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\BundleProfile $profile
   *   The bundle.
   * @param array<string, mixed> $decoded
   *   The model's answer.
   *
   * @return array<string, mixed>
   *   Values worth offering to the composer.
   */
  private function pickValues(BundleProfile $profile, array $decoded): array {
    $out = [];

    foreach ($decoded as $name => $value) {
      $name = (string) $name;

      if ($name === 'children' || !is_scalar($value)) {
        continue;
      }

      $fact = $profile->field($name);

      if ($fact === NULL || $fact->tab !== 'content') {
        continue;
      }

      $text = trim((string) $value);

      if ($text !== '') {
        $out[$name] = $text;
      }
    }

    return $out;
  }

  /**
   * The child sections in the answer, as the composer expects them.
   *
   * @param array<string, mixed> $decoded
   *   The model's answer.
   * @param string|null $childBundle
   *   The bundle every child must be.
   *
   * @return list<array<string, mixed>>
   *   Children for the composer.
   */
  private function pickChildren(array $decoded, ?string $childBundle): array {
    if ($childBundle === NULL || !isset($decoded['children']) || !is_array($decoded['children'])) {
      return [];
    }

    $out = [];

    foreach ($decoded['children'] as $child) {
      if (!is_array($child)) {
        continue;
      }

      $values = [];

      foreach ($child as $name => $value) {
        if (is_scalar($value) && trim((string) $value) !== '') {
          $values[(string) $name] = trim((string) $value);
        }
      }

      // The bundle is ours, not the model's: it is the only bundle this
      // parent's child field accepts.
      $out[] = ['bundle' => $childBundle, 'values' => $values];
    }

    return $out;
  }

}
