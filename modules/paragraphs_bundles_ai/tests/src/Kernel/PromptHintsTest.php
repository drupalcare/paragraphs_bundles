<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs_bundles_ai\Service\PromptHintsInterface;
use Drupal\Tests\paragraphs_bundles_ai\Traits\AiCoreSupportTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The placeholder tells a person what to say for the section they chose.
 *
 * WHAT THIS GUARDS. The maintainer's words: *"if the user asking for hero, they
 * dont know what fields they have, if you dont check and tell them what to fill
 * in each field, this is a mistake."* The dropdown names a section and then
 * offers one empty box; nothing in it says a hero has a body AND a link title,
 * or that an accordion wants each row named.
 *
 * THE SENTENCE IS BUILT FROM REAL FIELDS, so these tests build real fields. A
 * hand-written map would pass a test that asserted the map, and still be wrong
 * on any site that added a field — which is the failure a test should catch,
 * not reproduce.
 *
 * NO BROWSER IS INVOLVED, deliberately. The interface has never been loaded in
 * one, so the sentence has to be provable without it: the script only assigns
 * what this service produced.
 *
 * @group paragraphs_bundles
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PromptHintsTest extends KernelTestBase {

  use AiCoreSupportTrait;

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    // REQUIRED, AND THIS IS THE FIRST TEST IN THE MODULE TO NEED IT. `options`
    // provides `list_string`, which is what the steering fields are — and what
    // this service reads to decide whether to say "as an h2, centered". Without
    // it every test here dies in setUp with "unknown type list_string": 5
    // errors and 0 assertions, which reads like a broken service rather than a
    // missing module.
    'options',
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
   * The service under test.
   */
  private PromptHintsInterface $hints;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->skipUnlessCoreSupportsAi();
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['system', 'field', 'filter']);

    ParagraphsType::create(['id' => 'hero_bundle', 'label' => 'Hero'])->save();
    ParagraphsType::create(['id' => 'accordion_bundle', 'label' => 'Accordion'])->save();
    ParagraphsType::create(['id' => 'accordion_section_bundle', 'label' => 'Accordion Section'])->save();
    ParagraphsType::create(['id' => 'carousel_3d_bundle', 'label' => '3D Carousel'])->save();
    // Left with no fields at all: the only shape for which "takes no words" is
    // a true sentence.
    ParagraphsType::create(['id' => 'empty_bundle', 'label' => 'Empty'])->save();

    // A hero: a title, the two steering lists, and two prose fields.
    $this->field('hero_bundle', 'pb_content_title', 'string', 'Title');
    $this->field('hero_bundle', 'pb_content_body', 'text_long', 'Body');
    $this->field('hero_bundle', 'pb_content_image_link_title', 'string', 'Image Link Title');
    $this->listField('hero_bundle', 'pb_content_title_tag', 'Title tag', ['h1' => 'h1', 'h2' => 'h2']);
    $this->listField('hero_bundle', 'pb_content_title_alignment', 'Title alignment', [
      'left' => 'left',
      'center' => 'center',
    ]);

    // An accordion: a title and exactly one kind of child.
    $this->field('accordion_bundle', 'pb_content_title', 'string', 'Title');
    $this->reference('accordion_bundle', 'pb_content_accordion', 'Rows', ['accordion_section_bundle' => 'accordion_section_bundle']);

    // CHOICES BUT NO PROSE — the 3d_carousel shape, and the defect this guards.
    // Its only settable fields are lists. The hint used to call this "takes no
    // words" while AI-PROMPTS.md records the model setting both heights from
    // "make the large panels 250 and the small ones 150".
    $this->listField('carousel_3d_bundle', 'pb_content_3dcar_lheight', 'Large height', [
      '250' => '250',
      '300' => '300',
    ]);

    $this->hints = $this->container->get(PromptHintsInterface::class);
  }

  /**
   * A hero names its prose fields and teaches the steering habit.
   */
  public function testHeroNamesItsFieldsAndTheSteeringHabit(): void {
    $hint = $this->hints->forBundle('hero_bundle');

    self::assertStringContainsString('as an h2', $hint, 'The heading-level habit is the single most useful thing to teach here.');
    self::assertStringContainsString('centered', $hint);
    self::assertStringContainsString('the Body', $hint);
    self::assertStringContainsString('the Image Link Title', $hint, 'A hero has two prose fields and nothing else says so.');
    self::assertStringEndsWith('.', $hint);
  }

  /**
   * A list field is never offered as something to write a sentence for.
   *
   * `FieldFact::isWords()` counts `list_string`, correctly, for the drafter.
   * Here it would tell somebody to describe "Title alignment" in prose, which
   * the field cannot hold.
   */
  public function testTheSteeringListsAreNotOfferedAsProseFields(): void {
    $hint = $this->hints->forBundle('hero_bundle');

    self::assertStringNotContainsString('the Title tag', $hint);
    self::assertStringNotContainsString('the Title alignment', $hint);
  }

  /**
   * A section that holds children says to name them.
   */
  public function testNestingSectionAsksForItsChildrenByName(): void {
    $hint = $this->hints->forBundle('accordion_bundle');

    self::assertStringContainsString('each Accordion Section', $hint, 'The child is named by its own label, not called "item".');
  }

  /**
   * A section whose only settable fields are CHOICES still gets asked for.
   *
   * THE REGRESSION GUARD FOR 2026-09-17. `3d_carousel_bundle` has no title and
   * no prose — only two list fields — and the hint told a person the section
   * "takes no words", which is false: AI-PROMPTS.md records the model setting
   * both heights from one plain sentence. The maintainer spotted it on the
   * verification page, and the page's own check had passed it, because that
   * check defined "writable" as prose exactly as the code did.
   */
  public function testChoicesAreAskedForEvenWithNoProse(): void {
    $hint = $this->hints->forBundle('carousel_3d_bundle');

    self::assertStringNotContainsString(
      'takes no words',
      $hint,
      'This section has a settable list field, so saying it takes no words is simply false.',
    );
    self::assertStringContainsString('the Large height', $hint, 'The choice is named, by its label.');
    self::assertStringStartsWith('Say ', $hint, 'With no heading clause in front of it, the sentence opens with "Say".');
  }

  /**
   * A bundle with genuinely nothing settable says so, rather than nothing.
   */
  public function testBundleThatTakesNoWordsSaysSo(): void {
    $hint = $this->hints->forBundle('empty_bundle');

    self::assertStringContainsString('takes no words', $hint);
    self::assertStringContainsString('Empty', $hint, 'Named, so the sentence reads as being about the thing just chosen.');
  }

  /**
   * The heading level and alignment are never named as fields.
   *
   * They are real list fields, so a naive "name every choice" rule would add
   * "the Title tag and the Title alignment" to almost every sentence — saying
   * the same thing as "as an h2, centered", in worse words.
   */
  public function testTheSteeringListsAreNotNamedAsChoices(): void {
    $hint = $this->hints->forBundle('hero_bundle');

    self::assertStringNotContainsString('the Title tag', $hint);
    self::assertStringNotContainsString('the Title alignment', $hint);
    self::assertStringContainsString('as an h2', $hint, 'They are offered the way a person says them instead.');
  }

  /**
   * An unknown bundle is an empty string, and many come back keyed.
   */
  public function testUnknownBundleIsEmptyAndManyComeBackKeyed(): void {
    self::assertSame('', $this->hints->forBundle('no_such_bundle'));

    $all = $this->hints->forBundles(['hero_bundle', 'accordion_bundle']);

    self::assertSame(['hero_bundle', 'accordion_bundle'], array_keys($all));
    self::assertNotSame($all['hero_bundle'], $all['accordion_bundle']);
  }

  /**
   * Adds a simple field.
   *
   * @param string $bundle
   *   Paragraph bundle.
   * @param string $name
   *   Field machine name.
   * @param string $type
   *   Field type.
   * @param string $label
   *   Human label.
   */
  private function field(string $bundle, string $name, string $type, string $label): void {
    if (FieldStorageConfig::loadByName('paragraph', $name) === NULL) {
      FieldStorageConfig::create([
        'field_name' => $name,
        'entity_type' => 'paragraph',
        'type' => $type,
      ])->save();
    }

    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $bundle,
      'label' => $label,
    ])->save();
  }

  /**
   * Adds a list field with real allowed values.
   *
   * @param string $bundle
   *   Paragraph bundle.
   * @param string $name
   *   Field machine name.
   * @param string $label
   *   Human label.
   * @param array<string, string> $values
   *   Allowed values.
   */
  private function listField(string $bundle, string $name, string $label, array $values): void {
    if (FieldStorageConfig::loadByName('paragraph', $name) === NULL) {
      FieldStorageConfig::create([
        'field_name' => $name,
        'entity_type' => 'paragraph',
        'type' => 'list_string',
        'settings' => ['allowed_values' => $values],
      ])->save();
    }

    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $bundle,
      'label' => $label,
    ])->save();
  }

  /**
   * Adds a paragraph reference field.
   *
   * @param string $bundle
   *   Paragraph bundle.
   * @param string $name
   *   Field machine name.
   * @param string $label
   *   Human label.
   * @param array<string, string> $targets
   *   Allowed child bundles.
   */
  private function reference(string $bundle, string $name, string $label, array $targets): void {
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
      'label' => $label,
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => ['target_bundles' => $targets],
      ],
    ])->save();
  }

}
