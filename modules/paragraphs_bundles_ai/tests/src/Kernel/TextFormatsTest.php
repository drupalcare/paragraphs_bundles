<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\filter\FilterFormatRepositoryInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs_bundles_ai\Service\TextFormatsInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * A rewrite keeps the format it was authored in.
 *
 * THE DEFECT THIS EXISTS TO CATCH, measured on sts.local 2026-09-17 against a
 * real stored value before anything was changed:
 *
 *   as stored             format: 'basic_html'
 *   after set('words')    format: NULL
 *   Paragraph::create()   format: NULL
 *
 * A NULL format is not "unfiltered": `ProcessedText` falls back to
 * `filter.settings.fallback_format`, normally `plain_text`, so a body written
 * with markup is escaped and shown as source to visitors. Nothing errored and
 * nothing was refused, which is why it survived — the section saved cleanly
 * and simply rendered wrong.
 *
 * @group paragraphs_bundles
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class TextFormatsTest extends KernelTestBase {

  use UserCreationTrait;

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
    'key',
    'ai',
    'paragraphs_bundles',
    'paragraphs_bundles_ai',
  ];

  /**
   * The service under test.
   */
  private TextFormatsInterface $formats;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['system', 'field', 'filter']);

    FilterFormat::create(['format' => 'rich_text', 'name' => 'Rich text', 'weight' => -10])->save();
    FilterFormat::create(['format' => 'tight', 'name' => 'Tight', 'weight' => 10])->save();

    ParagraphsType::create(['id' => 'worded', 'label' => 'Worded'])->save();

    $this->textField('body_text', 'text_long');
    $this->textField('plain_line', 'string');

    // Permitted to use both formats, so the default is a real choice rather
    // than the only one available.
    $this->setUpCurrentUser(permissions: [
      'use text format rich_text',
      'use text format tight',
    ]);

    $this->formats = $this->container->get(TextFormatsInterface::class);
  }

  /**
   * Control: a field with no format is handed back the plain string.
   *
   * Without this, every assertion below could pass because the helper wraps
   * everything in an item array regardless.
   */
  public function testPlainStringFieldIsUnchanged(): void {
    $paragraph = Paragraph::create(['type' => 'worded']);
    $value = $this->formats->valueFor($paragraph->get('plain_line'), 'Just words.');

    self::assertSame('Just words.', $value);
  }

  /**
   * THE REGRESSION: an existing format survives a rewrite.
   */
  public function testAnExistingFormatIsKept(): void {
    $paragraph = Paragraph::create([
      'type' => 'worded',
      'body_text' => ['value' => '<p>As authored.</p>', 'format' => 'tight'],
    ]);

    $paragraph->set(
      'body_text',
      $this->formats->valueFor($paragraph->get('body_text'), 'Rewritten words.'),
    );

    // `tight` has no filter that keeps line breaks, so the words arrive as a
    // paragraph (see testLineBreaksSurviveFormatWithoutAutop()).
    self::assertSame('<p>Rewritten words.</p>', $paragraph->get('body_text')->value);
    self::assertSame(
      'tight',
      $paragraph->get('body_text')->format,
      'The format a person chose must survive a rewording of the words.',
    );
  }

  /**
   * Written lines stay lines where the format would run them together.
   *
   * Measured 2026-09-27 on sts.local: `basic_html` has no `filter_autop`, so
   * "three short lines" from the model rendered as one line. Text is escaped
   * too: a model's angle brackets are words, not markup.
   */
  public function testLineBreaksSurviveFormatWithoutAutop(): void {
    $paragraph = Paragraph::create([
      'type' => 'worded',
      'body_text' => ['value' => 'old', 'format' => 'tight'],
    ]);

    $value = $this->formats->valueFor(
      $paragraph->get('body_text'),
      "Tuesday to Sunday.\n9am to 4pm.\n\nClosed <b>Mondays</b>.",
    );

    self::assertSame(
      [
        'value' => "<p>Tuesday to Sunday.<br>\n9am to 4pm.</p>\n<p>Closed &lt;b&gt;Mondays&lt;/b&gt;.</p>",
        'format' => 'tight',
      ],
      $value,
    );
  }

  /**
   * A format that already turns newlines into markup gets the text as written.
   */
  public function testFormatThatKeepsBreaksGetsTextUntouched(): void {
    $autop = FilterFormat::create([
      'format' => 'autop_text',
      'name' => 'Autop',
      'filters' => ['filter_autop' => ['status' => TRUE]],
    ]);
    $autop->save();
    $paragraph = Paragraph::create([
      'type' => 'worded',
      'body_text' => ['value' => 'old', 'format' => 'autop_text'],
    ]);

    self::assertSame(
      ['value' => "One.\nTwo.", 'format' => 'autop_text'],
      $this->formats->valueFor($paragraph->get('body_text'), "One.\nTwo."),
    );
  }

  /**
   * The bare-string set() this replaces really does drop the format.
   *
   * Asserting the BUG as well as the fix. Without this, a future refactor
   * could quietly reintroduce `set($name, $string)` and the test above would
   * keep passing on the helper alone while the real call site rotted.
   */
  public function testTheBareSetStillLosesItWhichIsWhyTheHelperExists(): void {
    $paragraph = Paragraph::create([
      'type' => 'worded',
      'body_text' => ['value' => '<p>As authored.</p>', 'format' => 'tight'],
    ]);

    $paragraph->set('body_text', 'Rewritten words.');

    self::assertNull(
      $paragraph->get('body_text')->format,
      'If this ever stops being NULL, core changed and TextFormats can be revisited.',
    );
  }

  /**
   * A new section gets a real format rather than NULL.
   */
  public function testNewValueIsGivenTheUsersDefaultFormat(): void {
    $paragraph = Paragraph::create(['type' => 'worded']);

    $paragraph->set(
      'body_text',
      $this->formats->valueFor($paragraph->get('body_text'), 'Words from the assistant.'),
    );

    $format = $paragraph->get('body_text')->format;

    self::assertNotNull($format, 'A created section must not be left with a NULL format.');

    // Through the repository, not filter_default_format(): the deprecated
    // function is what this service exists to stop calling, and a test that
    // called it would reintroduce the deprecation it is meant to prove gone.
    $expected = $this->container
      ->get(FilterFormatRepositoryInterface::class)
      ->getDefaultFormat($this->container->get('current_user'))
      ->id();

    self::assertSame(
      $expected,
      $format,
      "The person's own default format applies, not the site's fallback.",
    );
  }

  /**
   * Adds a field to the test bundle.
   *
   * @param string $name
   *   Field machine name.
   * @param string $type
   *   Field type.
   */
  private function textField(string $name, string $type): void {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'type' => $type,
    ])->save();

    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => 'worded',
      'label' => ucfirst(str_replace('_', ' ', $name)),
    ])->save();
  }

}
