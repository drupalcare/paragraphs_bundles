<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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
 * WHICH FIELDS CAN BE FILLED BY WRITING IS NOT DECIDED HERE. It is a property
 * of the field, answered by FieldFact::isWords(), so a reference, an image or
 * a colour never reaches the schema — and the answer lives in one place rather
 * than in three services that could drift apart.
 */
final class SectionDrafter implements SectionDrafterInterface {

  use StringTranslationTrait;

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
      return DraftedSection::refused([$this->t('Say what the section should be about.')]);
    }

    $profile = $this->profiler->profile($bundle);

    if ($profile === NULL) {
      return DraftedSection::refused([
        $this->t('There is no section "@section" on this site.', ['@section' => $bundle]),
      ]);
    }

    [$providerId, $modelId] = $this->chosenProvider();

    if ($providerId === '') {
      return DraftedSection::refused([
        $this->t('No default AI provider is set for chat. Choose one at Configuration › AI › Settings.'),
      ]);
    }

    $childBundle = $children > 0 ? $profile->soleChildBundle() : NULL;

    // ASKED FOR CHILDREN THAT CANNOT BE WRITTEN — say so, rather than build
    // the section without them and report success. Measured 2026-09-17: this
    // was silent on 12 of the 18 nesting bundles PB ships, so "Two columns,
    // how many: 3" produced an empty section, badged New, with notes that
    // never mentioned the three missing cards.
    if ($children > 0 && $childBundle === NULL) {
      return DraftedSection::refused([$this->whyNoChildren($profile)]);
    }

    $schema = $this->schemaFor($profile, $childBundle, $children);

    if ($schema === []) {
      return DraftedSection::refused([
        $this->t('The section @section has no text fields to fill in.', ['@section' => $profile->label]),
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

    $answer = $this->ask($input, $providerId, $modelId);

    if (!is_array($answer)) {
      return DraftedSection::refused([$answer]);
    }

    return DraftedSection::of(
      $this->pickValues($profile, $answer),
      $this->pickChildren($answer, $childBundle),
      $modelId,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function draftEdit(string $bundle, array $current, string $words): DraftedSection {
    $words = trim($words);

    if ($words === '') {
      return DraftedSection::refused([$this->t('Say what should change about this section.')]);
    }

    $profile = $this->profiler->profile($bundle);

    if ($profile === NULL) {
      return DraftedSection::refused([
        $this->t('There is no section "@section" on this site.', ['@section' => $bundle]),
      ]);
    }

    [$providerId, $modelId] = $this->chosenProvider();

    if ($providerId === '') {
      return DraftedSection::refused([
        $this->t('No default AI provider is set for chat. Choose one at Configuration › AI › Settings.'),
      ]);
    }

    $properties = $this->propertiesFor($profile);

    if ($properties === []) {
      return DraftedSection::refused([
        $this->t('The section @section has no text fields to change.', ['@section' => $profile->label]),
      ]);
    }

    $lines = [
      sprintf('You are rewriting one "%s" section that already exists on a Drupal site.', $profile->label),
      'It currently says:',
    ];

    foreach ($current as $name => $value) {
      $lines[] = sprintf('- %s: %s', $name, $value);
    }

    $lines[] = 'Return ONLY the fields that should change. Leave out every field that should stay as it is.';
    $lines[] = 'Write plain text, within any character limit in the schema. No HTML, no Markdown.';

    $input = new ChatInput([new ChatMessage('user', $words)]);
    $input->setSystemPrompt(implode("\n", $lines));
    $input->setChatStructuredJsonSchema(new StructuredOutputSchema(
      name: 'pb_section_edit',
      description: sprintf('Replacement words for a %s section.', $profile->label),
      strict: FALSE,
      json_schema: ['type' => 'object', 'properties' => $properties],
    ));

    $answer = $this->ask($input, $providerId, $modelId);

    if (!is_array($answer)) {
      return DraftedSection::refused([$answer]);
    }

    // No children, ever: changing what a section CONTAINS is a different act
    // from changing what it SAYS, and only the second one was asked for.
    return DraftedSection::of($this->pickValues($profile, $answer), [], $modelId);
  }

  /**
   * Sends one request and decodes the answer.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatInput $input
   *   The prepared request.
   * @param string $providerId
   *   Provider plugin id.
   * @param string $modelId
   *   Model id.
   *
   * @return array<string, mixed>|\Stringable
   *   The decoded answer, or a sentence explaining why there is none.
   *
   *   `string` was in this union and never returned: every non-array path here
   *   goes through $this->t(), which is TranslatableMarkup. Leaving it in
   *   invited the mistake this project has already made once — a caller
   *   testing the refusal with is_string(), which silently stopped matching
   *   the day the refusal became translatable. Callers check !is_array().
   */
  private function ask(ChatInput $input, string $providerId, string $modelId): array|\Stringable {
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
      return $this->t('The AI provider could not answer: @error', ['@error' => $error->getMessage()]);
    }

    $decoded = json_decode($text, TRUE);

    return is_array($decoded)
      ? $decoded
      : $this->t('The model did not answer in the shape this section needs. Try again, or say it differently.');
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
   * Why children could not be written, in words the person can act on.
   *
   * The two causes need different actions — one is a question only the person
   * can answer, the other is a gap in how the site configured the field — so
   * collapsing them into "could not add children" would hide which.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\BundleProfile $profile
   *   The parent.
   *
   * @return \Stringable
   *   A complete sentence. Always TranslatableMarkup — every branch returns
   *   $this->t() — so `string` was in this union without ever being produced.
   */
  private function whyNoChildren(BundleProfile $profile): \Stringable {
    $fields = array_keys($profile->childFields);

    if (count($fields) > 1) {
      return $this->t('A "@section" holds sections in more than one place (@fields), so I cannot tell where these should go. Add the section first, then put them in the one you want.', [
        '@section' => $profile->label,
        '@fields' => implode(' and ', $fields),
      ]);
    }

    if ($fields === []) {
      return $this->t('A "@section" cannot hold other sections.', ['@section' => $profile->label]);
    }

    return $this->t('The "@field" field on a "@section" accepts any kind of section, so this site has never said what belongs inside one. I can add the section itself, but you will need to choose what goes in it.', [
      '@field' => (string) $fields[0],
      '@section' => $profile->label,
    ]);
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

      if ($fact === NULL || !$fact->isWords()) {
        continue;
      }

      // The label says what the field is CALLED; the description says what
      // belongs in it. Both go, because the label alone described
      // `pb_content_title` to the model as "Title" and nothing more.
      $property = [
        'type' => 'string',
        'description' => $fact->description === ''
          ? $fact->label
          : $fact->label . ' — ' . $fact->description,
      ];

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
    ];

    // WHAT THE SECTION IS, not just what it is called. A label alone leaves the
    // model guessing from a name: asked for a parallax it returned a
    // description of the layout, because nothing had told it a parallax is a
    // banner with content over a fixed image. PB ships this text already.
    if ($profile->description !== '') {
      $lines[] = sprintf('What a "%s" is: %s', $profile->label, $profile->description);
    }

    $lines[] = 'Answer only with the fields in the schema. Leave out any field the person did not ask for rather than inventing something to fill it.';
    $lines[] = 'Write plain text. No HTML, no Markdown, no quotation marks around the whole value.';
    $lines[] = 'Keep to any character limit in the schema: the field will not hold more.';

    if ($childBundle !== NULL && $children > 0) {
      $childProfile = $this->profiler->profile($childBundle);
      $childLabel = $childProfile === NULL ? $childBundle : $childProfile->label;

      $lines[] = sprintf(
        'Also write %d "%s" child section(s) in "children", in the order they should appear.',
        $children,
        $childLabel,
      );

      // And what a child IS, for the same reason as the parent.
      if ($childProfile !== NULL && $childProfile->description !== '') {
        $lines[] = sprintf('Each "%s" is: %s', $childLabel, $childProfile->description);
      }

      $lines[] = 'Write real content for each child, not a description of what the section looks like.';
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
