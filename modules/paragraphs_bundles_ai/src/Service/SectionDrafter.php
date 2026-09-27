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
use Drupal\paragraphs_bundles_ai\Model\ChildSlot;
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
   * @param \Drupal\paragraphs_bundles_ai\Service\ChildSlotsInterface $slots
   *   Says what goes inside a section, slot by slot.
   */
  public function __construct(
    private readonly BundleProfilerInterface $profiler,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly PluginManagerInterface $providerManager,
    private readonly ChildSlotsInterface $slots,
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

    $plan = $this->plan($profile, $children);

    // ASKED FOR CHILDREN THAT CANNOT BE WRITTEN — say so, rather than build
    // the section without them and report success. Measured 2026-09-17: this
    // was silent on 12 of the 18 nesting bundles PB ships, so "Two columns,
    // how many: 3" produced an empty section, badged New, with notes that
    // never mentioned the three missing cards. Since 2026-09-27 the briefs'
    // nesting list answers most of those; this is what is left.
    if ($children > 0 && $plan === []) {
      return DraftedSection::refused([$this->whyNoChildren($profile)]);
    }

    $schema = $this->schemaFor($profile, $plan);

    if ($schema === []) {
      return DraftedSection::refused([
        $this->t('The section @section has no text fields to fill in.', ['@section' => $profile->label]),
      ]);
    }

    $input = new ChatInput([new ChatMessage('user', $words)]);
    $input->setSystemPrompt($this->systemPrompt($profile, $plan));
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
      $this->pickChildren($answer, $plan),
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
   * How many sections go in which slot.
   *
   * ONE SLOT: "How many" is the count, and 0 means none were asked for.
   * SEVERAL SLOTS — two columns, a flip box's two faces, a modal's body and
   * footer: one in each, whatever the count says. A count cannot say which
   * column, and a column left empty still takes its width.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\BundleProfile $profile
   *   The parent.
   * @param int $children
   *   The count the person gave.
   *
   * @return array<string, array{slot: \Drupal\paragraphs_bundles_ai\Model\ChildSlot, count: int}>
   *   Field machine name => the slot and how many to write in it.
   */
  private function plan(BundleProfile $profile, int $children): array {
    $slots = $this->slots->slotsFor($profile->bundle);

    if (count($slots) === 1) {
      return $children > 0 ? [$slots[0]->field => ['slot' => $slots[0], 'count' => $children]] : [];
    }

    $plan = [];

    foreach ($slots as $slot) {
      $plan[$slot->field] = ['slot' => $slot, 'count' => 1];
    }

    return $plan;
  }

  /**
   * Builds the JSON schema for a bundle's fillable fields and its children.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\BundleProfile $profile
   *   The bundle.
   * @param array<string, array{slot: \Drupal\paragraphs_bundles_ai\Model\ChildSlot, count: int}> $plan
   *   What goes in which slot.
   *
   * @return array<string, mixed>
   *   A JSON schema, or an empty array when there is nothing to write.
   */
  private function schemaFor(BundleProfile $profile, array $plan): array {
    $properties = $this->propertiesFor($profile);
    $slotProperties = [];

    foreach ($plan as $field => $entry) {
      $slotProperties[$field] = [
        'type' => 'array',
        'description' => sprintf('Exactly %d section(s) for "%s", in order.', $entry['count'], $field),
        'maxItems' => $entry['count'],
        'items' => $this->itemSchema($entry['slot'], TRUE),
      ];
    }

    if ($slotProperties !== []) {
      $properties['children'] = ['type' => 'object', 'properties' => $slotProperties];
    }

    return $properties === [] ? [] : ['type' => 'object', 'properties' => $properties];
  }

  /**
   * The schema for one section inside a slot.
   *
   * THE FIELDS OF EVERY ALLOWED BUNDLE, SIDE BY SIDE. JSON schema cannot say
   * "these fields when bundle is X" in a way every provider honours, so the
   * model gets them all and pickChild() keeps only the chosen bundle's.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\ChildSlot $slot
   *   The slot.
   * @param bool $withInner
   *   Whether this section's own slots are asked for too. TRUE only one level
   *   down: a slide holds a Simple, and the Simple holds nothing.
   *
   * @return array<string, mixed>
   *   A JSON schema object.
   */
  private function itemSchema(ChildSlot $slot, bool $withInner): array {
    $properties = [
      'bundle' => [
        'type' => 'string',
        'enum' => $slot->bundles,
        'description' => sprintf('Which section this is. Use "%s" unless the person asked for one of the others.', $slot->default()),
      ],
    ];
    $inner = [];

    foreach ($slot->bundles as $bundle) {
      $profile = $this->profiler->profile($bundle);

      if ($profile === NULL) {
        continue;
      }

      $properties += $this->propertiesFor($profile);

      if (!$withInner) {
        continue;
      }

      foreach ($this->slots->slotsFor($bundle) as $innerSlot) {
        $inner[$innerSlot->field] ??= [
          'type' => 'array',
          'description' => sprintf('Exactly 1 section for "%s".', $innerSlot->field),
          'maxItems' => 1,
          'items' => $this->itemSchema($innerSlot, FALSE),
        ];
      }
    }

    if ($inner !== []) {
      $properties['children'] = ['type' => 'object', 'properties' => $inner];
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
   * @param array<string, array{slot: \Drupal\paragraphs_bundles_ai\Model\ChildSlot, count: int}> $plan
   *   What goes in which slot.
   *
   * @return string
   *   The prompt.
   */
  private function systemPrompt(BundleProfile $profile, array $plan): string {
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

    if ($plan !== []) {
      $lines[] = 'Also write the sections that go inside it, in "children", keyed by the field each group belongs in. Put the kind of each section in its "bundle".';
      $described = [];

      foreach ($plan as $field => $entry) {
        $slot = $entry['slot'];
        $lines[] = sprintf(
          '- "%s": %d section(s). Use %s unless the person asks for %s.',
          $field,
          $entry['count'],
          $this->named($slot->default()),
          count($slot->bundles) > 1
            ? implode(' or ', array_map($this->named(...), array_slice($slot->bundles, 1)))
            : 'nothing else',
        );

        foreach ($slot->bundles as $bundle) {
          foreach ($this->slots->slotsFor($bundle) as $innerSlot) {
            $lines[] = sprintf(
              '  Each %s also holds 1 section in its own "children"."%s": %s by default%s.',
              $this->named($bundle),
              $innerSlot->field,
              $this->named($innerSlot->default()),
              count($innerSlot->bundles) > 1
                ? ', or ' . implode(' or ', array_map($this->named(...), array_slice($innerSlot->bundles, 1))) . ' if asked'
                : '',
            );
          }

          $described[$bundle] = TRUE;
        }
      }

      // What each kind IS, for the same reason as the parent.
      foreach (array_keys($described) as $bundle) {
        $childProfile = $this->profiler->profile((string) $bundle);

        if ($childProfile !== NULL && $childProfile->description !== '') {
          $lines[] = sprintf('A %s is: %s', $this->named((string) $bundle), $childProfile->description);
        }
      }

      $lines[] = 'Write real content for each section, not a description of what the section looks like.';
    }

    return implode("\n", $lines);
  }

  /**
   * A bundle as the model sees it: machine name, then label.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   *
   * @return string
   *   For example `"simple_bundle" (Simple)`.
   */
  private function named(string $bundle): string {
    $profile = $this->profiler->profile($bundle);

    return $profile === NULL ? sprintf('"%s"', $bundle) : sprintf('"%s" (%s)', $bundle, $profile->label);
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
   * The child sections in the answer, keyed by slot, as the composer takes them.
   *
   * @param array<string, mixed> $decoded
   *   The model's answer.
   * @param array<string, array{slot: \Drupal\paragraphs_bundles_ai\Model\ChildSlot, count: int}> $plan
   *   What was asked for, slot by slot.
   *
   * @return array<string, list<array<string, mixed>>>
   *   Field machine name => children for the composer.
   */
  private function pickChildren(array $decoded, array $plan): array {
    $raw = $decoded['children'] ?? NULL;

    if ($plan === [] || !is_array($raw)) {
      return [];
    }

    // A bare list for a section with one slot: the model skipped the key, and
    // there is only one place it can have meant.
    if (array_is_list($raw) && count($plan) === 1) {
      $raw = [(string) array_key_first($plan) => $raw];
    }

    $out = [];

    foreach ($plan as $field => $entry) {
      $list = $raw[$field] ?? NULL;

      if (!is_array($list)) {
        continue;
      }

      // Never more than was asked for: a model that writes seven slides when
      // the person said five has not been given the page.
      foreach (array_slice(array_values($list), 0, $entry['count']) as $item) {
        $child = $this->pickChild($item, $entry['slot'], TRUE);

        if ($child !== NULL) {
          $out[$field][] = $child;
        }
      }
    }

    return $out;
  }

  /**
   * One child from the answer, held to its slot.
   *
   * THE BUNDLE IS CHECKED, NOT TRUSTED. One outside the slot's list becomes
   * the slot's default rather than a refusal: the person still gets the
   * section, in the kind this site said belongs there.
   *
   * @param mixed $item
   *   One element of the model's list.
   * @param \Drupal\paragraphs_bundles_ai\Model\ChildSlot $slot
   *   The slot it goes in.
   * @param bool $withInner
   *   Whether its own children are kept. One level only.
   *
   * @return array<string, mixed>|null
   *   ['bundle' => string, 'values' => array, 'children' => array?], or NULL.
   */
  private function pickChild(mixed $item, ChildSlot $slot, bool $withInner): ?array {
    if (!is_array($item)) {
      return NULL;
    }

    $bundle = is_string($item['bundle'] ?? NULL) && $slot->allows($item['bundle'])
      ? $item['bundle']
      : $slot->default();
    $profile = $this->profiler->profile($bundle);

    if ($profile === NULL) {
      return NULL;
    }

    $child = ['bundle' => $bundle, 'values' => $this->pickValues($profile, $item)];
    $innerRaw = $item['children'] ?? NULL;

    if (!$withInner || !is_array($innerRaw)) {
      return $child;
    }

    foreach ($this->slots->slotsFor($bundle) as $innerSlot) {
      $list = $innerRaw[$innerSlot->field] ?? NULL;

      if (!is_array($list)) {
        continue;
      }

      foreach (array_slice(array_values($list), 0, 1) as $innerItem) {
        $grandchild = $this->pickChild($innerItem, $innerSlot, FALSE);

        if ($grandchild !== NULL) {
          $child['children'][$innerSlot->field][] = $grandchild;
        }
      }
    }

    return $child;
  }

}
