<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs_bundles_ai\Service\FieldDrafterInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * What a section holds, and whether a draft will actually fit it.
 *
 * WHY THE LIMITS ARE READ AND NOT ASSUMED. A field's character limit lives in
 * storage settings the site builder chose, and a draft written without it is
 * text that reads fine and cannot be saved. The interesting cases here are all
 * the ones where something is silently lost: a limit nobody checked, a
 * multi-byte character cut in half, markup aimed at a plain field.
 *
 * @group paragraphs_bundles
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class FieldDrafterTest extends KernelTestBase {

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
    'node',
    'file',
    'entity_reference_revisions',
    'paragraphs',
    // The parent is a hard dependency in info.yml, and this module's services
    // now reference one of its services. Installing this module without it is
    // a configuration no site can have, and it fails at container compile.
    // drupal/ai too: SectionDrafter takes its provider manager, so the
    // container cannot compile without it.
    'key',
    'ai',
    'paragraphs_bundles',
    'paragraphs_bundles_ai',
  ];

  /**
   * The service under test.
   */
  private FieldDrafterInterface $drafter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['system', 'field', 'filter']);

    ParagraphsType::create(['id' => 'slideshow_bundle', 'label' => 'Slideshow'])->save();
    ParagraphsType::create(['id' => 'image_bundle', 'label' => 'Image'])->save();

    // A headline with a real limit, a body without one, and an integer field
    // that must not be offered for drafting.
    $this->field('slideshow_bundle', 'pb_headline', 'string', 'Headline', ['max_length' => 40]);
    $this->field('slideshow_bundle', 'pb_body', 'text_long', 'Body');
    $this->field('slideshow_bundle', 'pb_weight', 'integer', 'Weight');

    // A section made of something other than text.
    $this->field('image_bundle', 'pb_alt_weight', 'integer', 'Weight');

    $this->drafter = $this->container->get(FieldDrafterInterface::class);
  }

  /**
   * The inventory names only the text fields, with their real limits.
   */
  public function testItListsTextFieldsWithTheirLimits(): void {
    $fields = $this->drafter->draftableFields('slideshow_bundle');

    self::assertIsArray($fields);
    // Alphabetical by machine name, and asserted as such: the service sorts
    // deliberately so this answer does not shuffle between sites.
    self::assertSame(['pb_body', 'pb_headline'], array_keys($fields));
    self::assertSame(40, $fields['pb_headline']->maxLength);
    self::assertNull($fields['pb_body']->maxLength, 'text_long has no character limit.');
    self::assertFalse($fields['pb_headline']->allowsHtml, 'string fields show tags, not markup.');
    self::assertTrue($fields['pb_body']->allowsHtml);
  }

  /**
   * Base fields are machinery and must never be offered for drafting.
   *
   * A paragraph carries id, uuid, type, parent_id, parent_field_name and more.
   * Listing them invites writing into them, and none of them is a place for a
   * headline.
   */
  public function testBaseFieldsAreNeverOffered(): void {
    $fields = $this->drafter->draftableFields('slideshow_bundle');

    self::assertIsArray($fields);
    foreach (['id', 'uuid', 'type', 'parent_id', 'parent_field_name', 'status'] as $machinery) {
      self::assertArrayNotHasKey($machinery, $fields);
    }
  }

  /**
   * An unknown bundle and a bundle with no text are different answers.
   *
   * NULL means "no such section" — a typo, or a module that is not enabled.
   * An empty array means the section is real and simply is not made of text.
   * Collapsing the two would hide which, and they need different fixes.
   */
  public function testAnUnknownBundleAndAnUntextualOneDiffer(): void {
    self::assertNull($this->drafter->draftableFields('no_such_bundle'));
    self::assertSame([], $this->drafter->draftableFields('image_bundle'));
  }

  /**
   * A draft inside the limit comes back untouched.
   */
  public function testDraftThatFitsIsReturnedUnchanged(): void {
    $result = $this->drafter->fit('slideshow_bundle', 'pb_headline', '  Spring collection is here  ');

    self::assertTrue($result->usable, implode(' | ', $result->refusals));
    self::assertSame('Spring collection is here', $result->text, 'Trimmed of whitespace, not of words.');
    self::assertFalse($result->trimmed);
    self::assertSame(40, $result->limit);
  }

  /**
   * An over-long draft is shortened, and the shortening is REPORTED.
   *
   * Silently fitting text to a limit is the failure this class exists to
   * prevent: the copy reads fine, the last clause is gone, and nobody notices
   * until it is published.
   */
  public function testAnOverLongDraftIsShortenedAndSaysSo(): void {
    $long = 'Spring collection is here and it is the finest we have ever produced';
    $result = $this->drafter->fit('slideshow_bundle', 'pb_headline', $long);

    self::assertTrue($result->usable);
    self::assertTrue($result->trimmed);
    self::assertLessThanOrEqual(40, mb_strlen($result->text));
    self::assertSame(mb_strlen($long), $result->originalLength);
    self::assertStringContainsString('Shortened from', implode(' ', $result->notes));
    // A word boundary, not a guillotine mid-word.
    self::assertStringEndsNotWith('-', $result->text);
    self::assertSame(rtrim($result->text), $result->text);
  }

  /**
   * Shortening counts CHARACTERS, not bytes.
   *
   * `substr()` cuts by byte and splits a multi-byte character in half, which
   * produces a broken glyph rather than a short sentence. On a site whose
   * content is not English that is every truncation, not an edge case — so the
   * fixture is deliberately Arabic, where every character is two bytes.
   */
  public function testShorteningIsMultiByteSafe(): void {
    $arabic = str_repeat('مرحبا ', 20);
    $result = $this->drafter->fit('slideshow_bundle', 'pb_headline', $arabic);

    self::assertTrue($result->usable);
    self::assertTrue($result->trimmed);
    self::assertLessThanOrEqual(40, mb_strlen($result->text));
    // The real assertion: still valid UTF-8. A byte-wise cut fails this.
    self::assertSame($result->text, mb_convert_encoding($result->text, 'UTF-8', 'UTF-8'));
    self::assertNotSame('', $result->text);
  }

  /**
   * A field with no limit takes the draft whole, however long.
   */
  public function testFieldWithoutLimitNeverShortens(): void {
    $long = str_repeat('Long body copy. ', 200);
    $result = $this->drafter->fit('slideshow_bundle', 'pb_body', $long);

    self::assertTrue($result->usable);
    self::assertFalse($result->trimmed);
    self::assertNull($result->limit);
    self::assertSame(trim($long), $result->text);
  }

  /**
   * Markup aimed at a plain field is flagged, not silently stripped.
   *
   * Rewriting someone's copy on a guess is worse than saying what will happen,
   * and the tags may be deliberate.
   */
  public function testMarkupInPlainFieldIsFlaggedAndKept(): void {
    $result = $this->drafter->fit('slideshow_bundle', 'pb_headline', '<em>Spring</em> is here');

    self::assertTrue($result->usable);
    self::assertStringContainsString('<em>', $result->text, 'The draft is reported, not edited.');
    self::assertStringContainsString('plain text field', implode(' ', $result->notes));
  }

  /**
   * Markup in a formatted field draws no complaint.
   *
   * Without this the note above could be firing on every field.
   */
  public function testMarkupInFormattedFieldIsNotFlagged(): void {
    $result = $this->drafter->fit('slideshow_bundle', 'pb_body', '<em>Spring</em> is here');

    self::assertTrue($result->usable);
    self::assertSame([], $result->notes);
  }

  /**
   * A non-text field is refused by naming what it actually is.
   */
  public function testNonTextFieldSaysWhatItIs(): void {
    $result = $this->drafter->fit('slideshow_bundle', 'pb_weight', 'Spring');

    self::assertFalse($result->usable);
    // (string) BECAUSE A REFUSAL IS TranslatableMarkup, NOT A STRING, AND THAT
    // IS DELIBERATE. The translation pass turned every user-facing sentence in
    // this module into t(), refusals included. assertStringContainsString()
    // requires a string haystack and throws a TypeError on anything else, so
    // these assertions have been erroring ever since — unnoticed, because this
    // project's phpunit.xml.dist sweeps Unit directories only and nobody had
    // run the Kernel suite since. Casting here keeps the assertion honest
    // without asking the production code to stop being translatable.
    self::assertStringContainsString('is a integer field', (string) $result->refusals[0]);
  }

  /**
   * A field that does not exist lists the ones that do.
   */
  public function testAnUnknownFieldListsTheRealOnes(): void {
    $result = $this->drafter->fit('slideshow_bundle', 'pb_nope', 'Spring');

    self::assertFalse($result->usable);
    self::assertStringContainsString('no text field "pb_nope"', (string) $result->refusals[0]);
    self::assertStringContainsString('pb_headline', (string) $result->refusals[0]);
  }

  /**
   * An empty draft is refused rather than returned as an empty success.
   */
  public function testAnEmptyDraftIsRefused(): void {
    $result = $this->drafter->fit('slideshow_bundle', 'pb_headline', "   \n  ");

    self::assertFalse($result->usable);
    self::assertStringContainsString('nothing to fit', (string) $result->refusals[0]);
  }

  /**
   * An unknown bundle is refused before any field is considered.
   */
  public function testAnUnknownBundleIsRefused(): void {
    $result = $this->drafter->fit('no_such_bundle', 'pb_headline', 'Spring');

    self::assertFalse($result->usable);
    self::assertStringContainsString('no paragraph type "no_such_bundle"', (string) $result->refusals[0]);
  }

  /**
   * Adds a field to a paragraph bundle.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   * @param string $name
   *   Field machine name.
   * @param string $type
   *   Field type.
   * @param string $label
   *   Human label.
   * @param array<string, mixed> $storageSettings
   *   Storage settings, such as max_length.
   */
  private function field(
    string $bundle,
    string $name,
    string $type,
    string $label,
    array $storageSettings = [],
  ): void {
    if (FieldStorageConfig::loadByName('paragraph', $name) === NULL) {
      FieldStorageConfig::create([
        'field_name' => $name,
        'entity_type' => 'paragraph',
        'type' => $type,
        'settings' => $storageSettings,
      ])->save();
    }

    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $bundle,
      'label' => $label,
    ])->save();
  }

}
