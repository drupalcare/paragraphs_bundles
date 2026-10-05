<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Kernel;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs_bundles_ai\Service\ChildSlotsInterface;
use Drupal\paragraphs_bundles_ai\Service\SectionComposerInterface;
use Drupal\paragraphs_bundles_ai\Service\SectionDrafter;
use Drupal\Tests\paragraphs_bundles_ai\Traits\AiCoreSupportTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers what goes inside a section: slots, the drafter's ask, the build.
 *
 * WHY THIS EXISTS. Measured 2026-09-27 on a test site: the briefs' `nesting`
 * list had no reader, so for a slideshow the model was asked only for each
 * slide's TITLE, and for two columns, a carousel or a grid only for the
 * heading — asking for children there was refused outright. Both example
 * prompts that ship with the module described content the tool dropped.
 *
 * No request leaves the test: the drafter is given a provider that answers
 * with a fixed JSON document and records what it was sent.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class ChildSlotsTest extends KernelTestBase {
  use AiCoreSupportTrait;

  use UserCreationTrait;

  /**
   * {@inheritdoc}
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
    'paragraphs_bundles',
    'paragraphs_bundles_ai',
  ];

  /**
   * The last request the stub provider received.
   */
  private ?ChatInput $sent = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->skipUnlessCoreSupportsAi();
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'field', 'filter', 'node']);

    foreach ([
      'slideshow_bundle' => 'Slideshow',
      'slideshow_section_bundle' => 'Slideshow section',
      'simple_bundle' => 'Simple',
      'image_bundle' => 'Image',
      'two_columns_bundle' => 'Two Columns',
      'carousel_bundle' => 'Carousel',
      'grid_bundle' => 'Grid',
    ] as $id => $label) {
      ParagraphsType::create(['id' => $id, 'label' => $label])->save();
    }

    $this->field('slideshow_bundle', 'pb_content_title', 'string', 'Title');
    $this->reference('slideshow_bundle', 'pb_content_slideshow_section', ['slideshow_section_bundle' => 'slideshow_section_bundle']);
    $this->field('slideshow_section_bundle', 'pb_content_slideshow_title', 'string', 'Slide title');
    $this->reference('slideshow_section_bundle', 'pb_content_slideshow_body', []);
    $this->field('simple_bundle', 'pb_content_body', 'string_long', 'Body');
    $this->field('image_bundle', 'pb_content_title', 'string', 'Image title');
    $this->field('two_columns_bundle', 'pb_content_title', 'string', 'Title');
    $this->reference('two_columns_bundle', 'pb_content_left_column', []);
    $this->reference('two_columns_bundle', 'pb_content_right_column', []);
    $this->field('carousel_bundle', 'pb_content_title', 'string', 'Title');
    $this->reference('carousel_bundle', 'pb_content_carousel_body', []);
    // A grid accepts anything and has NO nesting entry: nothing to fill.
    $this->field('grid_bundle', 'pb_content_title', 'string', 'Title');
    $this->reference('grid_bundle', 'pb_content_grid_body', []);

    // `hero_bundle` is named but does not exist here: a brief can name a
    // sub-module a site has not enabled, and that name must be dropped.
    $this->config('paragraphs_bundles_ai.briefs')->set('nesting', [
      'two_columns_bundle' => [
        'pb_content_left_column' => ['simple_bundle', 'image_bundle'],
        'pb_content_right_column' => ['simple_bundle', 'image_bundle'],
      ],
      'carousel_bundle' => [
        'pb_content_carousel_body' => ['image_bundle', 'simple_bundle'],
      ],
      'slideshow_section_bundle' => [
        'pb_content_slideshow_body' => ['hero_bundle', 'simple_bundle', 'image_bundle'],
      ],
    ])->save();

    $this->config('ai.settings')->set('default_providers', [
      'chat' => ['provider_id' => 'stub', 'model_id' => 'stub-model'],
    ])->save();
  }

  /**
   * Slots come from the field first, then the nesting list, never a guess.
   */
  public function testSlotsFollowTheFieldThenTheNestingList(): void {
    $slots = $this->container->get(ChildSlotsInterface::class);

    $columns = $slots->slotsFor('two_columns_bundle');
    self::assertSame(['pb_content_left_column', 'pb_content_right_column'], array_map(static fn($s) => $s->field, $columns));
    self::assertSame(['simple_bundle', 'image_bundle'], $columns[0]->bundles);
    self::assertFalse($slots->countable('two_columns_bundle'), 'Two columns get one each; a count says nothing about which column.');

    $carousel = $slots->slotsFor('carousel_bundle');
    self::assertCount(1, $carousel);
    self::assertSame('image_bundle', $carousel[0]->default());
    self::assertTrue($slots->countable('carousel_bundle'));

    // The field names one bundle, so the field decides.
    self::assertSame(['slideshow_section_bundle'], $slots->slotsFor('slideshow_bundle')[0]->bundles);

    // A name this site does not have is dropped; the order is otherwise kept.
    self::assertSame(['simple_bundle', 'image_bundle'], $slots->slotsFor('slideshow_section_bundle')[0]->bundles);

    // Accepts anything, and nobody said what: no slot, rather than a guess.
    self::assertSame([], $slots->slotsFor('grid_bundle'));
    self::assertFalse($slots->countable('grid_bundle'));
  }

  /**
   * Two columns: one section per column, held to the column's list.
   */
  public function testEveryColumnIsAskedForAndTheAnswerIsHeldToIt(): void {
    $draft = $this->drafter([
      'pb_content_title' => 'Bare-root or potted?',
      'children' => [
        'pb_content_left_column' => [
          ['bundle' => 'simple_bundle', 'pb_content_body' => 'Bare-root is cheaper.'],
          // More than was asked for: dropped.
          ['bundle' => 'simple_bundle', 'pb_content_body' => 'An extra one.'],
        ],
        // Not in the column's list: becomes the column's default.
        'pb_content_right_column' => [['bundle' => 'grid_bundle', 'pb_content_body' => 'Potted flowers sooner.']],
      ],
    ])->draft('two_columns_bundle', 'Compare bare-root and potted roses.', 0);

    self::assertTrue($draft->usable, implode(' | ', array_map('strval', $draft->refusals)));
    $simple = static fn(string $body): array => [
      ['bundle' => 'simple_bundle', 'values' => ['pb_content_body' => $body]],
    ];
    self::assertSame([
      'pb_content_left_column' => $simple('Bare-root is cheaper.'),
      'pb_content_right_column' => $simple('Potted flowers sooner.'),
    ], $draft->children);

    // The model was ASKED for both columns, one each.
    $schema = $this->sent?->getChatStructuredJsonSchema()['schema'] ?? [];
    $columns = $schema['properties']['children']['properties'] ?? [];
    self::assertSame(1, $columns['pb_content_left_column']['maxItems'] ?? NULL);
    self::assertSame(['simple_bundle', 'image_bundle'], $columns['pb_content_right_column']['items']['properties']['bundle']['enum'] ?? NULL);
    self::assertStringContainsString('"pb_content_left_column": 1 section(s)', (string) $this->sent?->getSystemPrompt());
  }

  /**
   * A slideshow: the slides, and the section inside each slide.
   */
  public function testEachSlideGetsTheSectionInsideIt(): void {
    $draft = $this->drafter([
      'children' => [
        'pb_content_slideshow_section' => [
          [
            'pb_content_slideshow_title' => 'Tulips',
            'children' => [
              'pb_content_slideshow_body' => [
                ['bundle' => 'simple_bundle', 'pb_content_body' => 'Plant them before the frost.'],
              ],
            ],
          ],
          [
            'pb_content_slideshow_title' => 'Garlic',
            'children' => [
              'pb_content_slideshow_body' => [
                ['bundle' => 'image_bundle', 'pb_content_title' => 'Garlic bed'],
              ],
            ],
          ],
        ],
      ],
    ])->draft('slideshow_bundle', 'Two slides about autumn planting.', 2);

    self::assertTrue($draft->usable, implode(' | ', array_map('strval', $draft->refusals)));
    $slides = $draft->children['pb_content_slideshow_section'] ?? [];
    self::assertCount(2, $slides);
    self::assertSame('slideshow_section_bundle', $slides[0]['bundle']);
    self::assertSame(
      [['bundle' => 'simple_bundle', 'values' => ['pb_content_body' => 'Plant them before the frost.']]],
      $slides[0]['children']['pb_content_slideshow_body'] ?? NULL,
    );
    // A person may ask for the other kind in the list.
    self::assertSame('image_bundle', $slides[1]['children']['pb_content_slideshow_body'][0]['bundle'] ?? NULL);

    $prompt = (string) $this->sent?->getSystemPrompt();
    self::assertStringContainsString('"pb_content_slideshow_body": "simple_bundle" (Simple) by default', $prompt);
  }

  /**
   * How many at 0 asks for the number the words give, as REQUIRED.
   *
   * Measured 2026-09-28: at 0 the model was asked for no children at all, and
   * where they were only capped (maxItems) it left the list out.
   */
  public function testHowManyAtZeroTakesTheWordsAndRequiresThem(): void {
    $this->drafter(['pb_content_slideshow_title' => 'x'])
      ->draft('slideshow_bundle', 'Write a slideshow of five slides, heading as an h2.', 0);

    $schema = $this->sent?->getChatStructuredJsonSchema()['schema'] ?? [];
    $slides = $schema['properties']['children']['properties']['pb_content_slideshow_section'] ?? [];
    self::assertSame(5, $slides['minItems'] ?? NULL, 'The number in the words, not the "2" in h2.');
    self::assertSame(5, $slides['maxItems'] ?? NULL);
    self::assertSame(['children'], $schema['required'] ?? NULL);
    self::assertSame(['pb_content_slideshow_section'], $schema['properties']['children']['required'] ?? NULL);

    $this->drafter(['pb_content_slideshow_title' => 'x'])->draft('slideshow_bundle', 'A slideshow about roses.', 0);
    $schema = $this->sent?->getChatStructuredJsonSchema()['schema'] ?? [];
    self::assertSame(3, $schema['properties']['children']['properties']['pb_content_slideshow_section']['minItems'] ?? NULL, 'No number in the words: a sensible few.');
  }

  /**
   * A timeout says what happened and where the limit is, not raw cURL text.
   *
   * Measured 2026-09-28: a 3D carousel of seven items timed out twice.
   */
  public function testTimeoutSaysWhereTheLimitIs(): void {
    $draft = $this->drafter(['throw' => 'cURL error 28: Operation timed out after 60001 milliseconds'])
      ->draft('slideshow_bundle', 'Three slides about roses.', 3);

    self::assertFalse($draft->usable);
    $said = implode(' ', array_map('strval', $draft->refusals));
    self::assertStringContainsString('took longer than this site allows (60 seconds)', $said);
    self::assertStringNotContainsString('cURL', $said);
  }

  /**
   * Children written under the slot's own name, with no "children" wrapper.
   *
   * The shape a live model returned on 2026-09-28 for a 3D carousel: all
   * seven items at the top of the answer, every one of them dropped.
   */
  public function testSlotNamedAtTheTopIsStillRead(): void {
    $draft = $this->drafter([
      'pb_content_slideshow_section' => [
        [
          'pb_content_slideshow_title' => 'Tulips',
          'pb_content_slideshow_body' => [
            ['bundle' => 'simple_bundle', 'pb_content_body' => 'Plant them before the frost.'],
          ],
        ],
        ['pb_content_slideshow_title' => 'Garlic'],
      ],
    ])->draft('slideshow_bundle', 'Two slides about autumn planting.', 2);

    self::assertTrue($draft->usable, implode(' | ', array_map('strval', $draft->refusals)));
    $slides = $draft->children['pb_content_slideshow_section'] ?? [];
    self::assertCount(2, $slides);
    self::assertSame(
      [['bundle' => 'simple_bundle', 'values' => ['pb_content_body' => 'Plant them before the frost.']]],
      $slides[0]['children']['pb_content_slideshow_body'] ?? NULL,
    );
  }

  /**
   * The composer builds the slide AND the section inside it, and no deeper.
   */
  public function testComposerBuildsOneLevelInsideAndRefusesTwo(): void {
    $this->pageType();
    $composer = $this->container->get(SectionComposerInterface::class);
    $slide = static fn(array $inner): array => [
      'bundle' => 'slideshow_section_bundle',
      'values' => ['pb_content_slideshow_title' => 'Tulips'],
      'children' => ['pb_content_slideshow_body' => [$inner]],
    ];

    $result = $composer->compose('page', 'field_sections', 'slideshow_bundle', [], [
      'pb_content_slideshow_section' => [
        $slide(['bundle' => 'simple_bundle', 'values' => ['pb_content_body' => 'Plant before the frost.']]),
      ],
    ], NULL, 'Autumn');

    self::assertTrue($result->created, implode(' | ', array_map('strval', $result->refusals)));
    $show = Node::load((int) $result->nodeId)?->get('field_sections')->entity;
    self::assertInstanceOf(Paragraph::class, $show);
    $built = $show->get('pb_content_slideshow_section')->entity;
    self::assertInstanceOf(Paragraph::class, $built);
    $inner = $built->get('pb_content_slideshow_body')->entity;
    self::assertInstanceOf(Paragraph::class, $inner, 'The section inside the slide was built.');
    self::assertSame('Plant before the frost.', $inner->get('pb_content_body')->value);

    // A third level is refused, not quietly built.
    $refused = $composer->compose('page', 'field_sections', 'slideshow_bundle', [], [
      'pb_content_slideshow_section' => [
        $slide([
          'bundle' => 'simple_bundle',
          'values' => [],
          'children' => ['pb_content_body' => [['bundle' => 'simple_bundle', 'values' => []]]],
        ]),
      ],
    ], NULL, 'Too deep');

    self::assertFalse($refused->created);
    self::assertStringContainsString('Only one level inside a section is built', implode(' ', array_map('strval', $refused->refusals)));
  }

  /**
   * A drafter whose provider answers with a fixed document.
   *
   * @param array<string, mixed> $answer
   *   What the "model" says.
   *
   * @return \Drupal\paragraphs_bundles_ai\Service\SectionDrafter
   *   The drafter.
   */
  private function drafter(array $answer): SectionDrafter {
    $test = $this;
    $provider = new class($answer, $test) {

      public function __construct(
        private readonly array $answer,
        private readonly ChildSlotsTest $test,
      ) {}

      /**
       * Records the request and answers with the fixed document.
       */
      public function chat(ChatInput $input, string $model, array $tags): object {
        $this->test->record($input);

        // A test can make the provider fail the way a real one does.
        if (is_string($this->answer['throw'] ?? NULL)) {
          throw new \RuntimeException($this->answer['throw']);
        }

        $message = new ChatMessage('assistant', (string) json_encode($this->answer));

        return new class($message) {

          public function __construct(private readonly ChatMessage $message) {}

          /**
           * The answer, as a provider's output normalises it.
           */
          public function getNormalized(): ChatMessage {
            return $this->message;
          }

        };
      }

    };
    $manager = $this->createMock(PluginManagerInterface::class);
    $manager->method('createInstance')->willReturn($provider);

    $drafter = new SectionDrafter(
      $this->container->get('paragraphs_bundles_ai.bundle_profiler'),
      $this->container->get('config.factory'),
      $manager,
      $this->container->get(ChildSlotsInterface::class),
    );
    $drafter->setStringTranslation($this->container->get('string_translation'));

    return $drafter;
  }

  /**
   * Keeps the request the stub provider was sent.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatInput $input
   *   The request.
   */
  public function record(ChatInput $input): void {
    $this->sent = $input;
  }

  /**
   * A content type with one unrestricted paragraph field, and a user for it.
   */
  private function pageType(): void {
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_sections',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'cardinality' => -1,
      'settings' => ['target_type' => 'paragraph'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_sections',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Sections',
      'settings' => ['handler' => 'default:paragraph', 'handler_settings' => ['target_bundles' => []]],
    ])->save();
    $this->setUpCurrentUser(permissions: ['access content', 'create page content', 'bypass node access']);
  }

  /**
   * Adds a plain field to a paragraph bundle.
   */
  private function field(string $bundle, string $name, string $type, string $label): void {
    if (FieldStorageConfig::loadByName('paragraph', $name) === NULL) {
      FieldStorageConfig::create(['field_name' => $name, 'entity_type' => 'paragraph', 'type' => $type])->save();
    }

    FieldConfig::create(['field_name' => $name, 'entity_type' => 'paragraph', 'bundle' => $bundle, 'label' => $label])->save();
  }

  /**
   * Adds a paragraph reference field; an empty target list means "any".
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   * @param string $name
   *   Field machine name.
   * @param array<string, string> $targets
   *   Allowed bundles.
   */
  private function reference(string $bundle, string $name, array $targets): void {
    if (FieldStorageConfig::loadByName('paragraph', $name) === NULL) {
      FieldStorageConfig::create([
        'field_name' => $name,
        'entity_type' => 'paragraph',
        'type' => 'entity_reference_revisions',
        'cardinality' => -1,
        'settings' => ['target_type' => 'paragraph'],
      ])->save();
    }

    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $bundle,
      'label' => $name,
      'settings' => ['handler' => 'default:paragraph', 'handler_settings' => ['target_bundles' => $targets]],
    ])->save();
  }

}
