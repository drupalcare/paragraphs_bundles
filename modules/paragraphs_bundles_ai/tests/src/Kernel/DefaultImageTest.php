<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\MediaType;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs_bundles_ai\Service\BundleProfilerInterface;
use Drupal\paragraphs_bundles_ai\Service\DefaultImageInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The module supplies a picture for the fields no model can fill.
 *
 * WHAT THIS IS GUARDING, AND WHY IT IS NOT A NICETY. Measured on sts.local
 * 2026-09-17: a real draft of a parallax produced two children with genuine
 * marketing copy in `pb_content_body` and nothing in `pb_content_image`, which
 * is REQUIRED — and `refusals` came back empty, so the person was told
 * nothing. Eight of PB's bundles have a required image. The maintainer's
 * words: a hero without an image is useless.
 *
 * THE TWO SHAPES ARE BOTH TESTED, because a site has only one of them. PB's
 * own bundles reference a media item and need the Media module; this module's
 * `ai_image_bundle` uses a plain image field for sites without it. A supplier
 * that handled one would work on half the sites and look fine in a test that
 * only covered that half.
 *
 * @group paragraphs_bundles
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class DefaultImageTest extends KernelTestBase {

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
    'image',
    'media',
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
  private DefaultImageInterface $images;

  /**
   * Reads the fields, so the facts under test are the real ones.
   */
  private BundleProfilerInterface $profiler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('paragraph');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'field', 'filter', 'image', 'media']);

    // A media type holding an image, the way PB's pb_media_image does.
    $type = MediaType::create([
      'id' => 'pb_media_image',
      'label' => 'Image',
      'source' => 'image',
    ]);
    $type->save();
    $source = $type->getSource()->createSourceField($type);
    $source->getFieldStorageDefinition()->save();
    $source->save();
    $type->set('source_configuration', ['source_field' => $source->getName()])->save();

    ParagraphsType::create(['id' => 'hero_bundle', 'label' => 'Hero'])->save();
    ParagraphsType::create(['id' => 'ai_image_bundle', 'label' => 'AI image'])->save();
    ParagraphsType::create(['id' => 'simple_bundle', 'label' => 'Simple'])->save();

    // The PB shape: a REQUIRED reference to a media item.
    $this->mediaReference('hero_bundle', 'pb_content_image', 'Image', TRUE);
    // The media-less shape: a REQUIRED plain image field.
    // THE `pb_content_` PREFIX IS LOAD-BEARING, not decoration. BundleProfiler
    // classifies a field by its form group first and that prefix second, so a
    // field named `ai_content_image` was reported as `unclassified` and the
    // bundle looked as though it had no content fields at all. The `ai` stays
    // in the middle of the name, where it still says which module owns it.
    $this->imageField('ai_image_bundle', 'pb_content_ai_image', 'Image', TRUE);
    // Neither, and the control: a picture must never land here.
    $this->stringField('simple_bundle', 'pb_content_title', 'Title');

    $this->images = $this->container->get(DefaultImageInterface::class);
    $this->profiler = $this->container->get(BundleProfilerInterface::class);
  }

  /**
   * A plain image field gets the shipped picture, with its alt text.
   */
  public function testPlainImageFieldGetsTheShippedPicture(): void {
    $profile = $this->profiler->profile('ai_image_bundle');
    self::assertNotNull($profile);

    $fact = $profile->field('pb_content_ai_image');
    self::assertNotNull($fact);
    self::assertTrue($fact->isPicture(), 'A plain image field is a picture.');

    $value = $this->images->valueFor($fact);

    self::assertIsArray($value);
    self::assertArrayHasKey('target_id', $value);
    self::assertNotEmpty($value['alt'] ?? '', 'pb_content_ai_image requires alt text, so a value without it does not save.');

    $file = $this->container->get('entity_type.manager')->getStorage('file')->load($value['target_id']);
    self::assertNotNull($file, 'The managed file was created.');
    self::assertStringEndsWith('pb-ai-placeholder.png', (string) $file->getFileUri());
  }

  /**
   * A media reference gets a media item holding the shipped picture.
   */
  public function testMediaReferenceGetsMediaItem(): void {
    $profile = $this->profiler->profile('hero_bundle');
    self::assertNotNull($profile);

    $fact = $profile->field('pb_content_image');
    self::assertNotNull($fact);
    self::assertSame('media', $fact->targetType, 'The profiler must report what a reference points at.');
    self::assertTrue($fact->isPicture(), 'A media reference is a picture.');

    $value = $this->images->valueFor($fact);

    self::assertIsArray($value);
    $media = $this->container->get('entity_type.manager')->getStorage('media')->load($value['target_id']);
    self::assertNotNull($media, 'The media item was created.');
    self::assertSame('pb_media_image', $media->bundle());
  }

  /**
   * Asking twice reuses one file and one media item.
   *
   * A page of twelve sections must add ONE picture to the person's library,
   * not twelve.
   */
  public function testTheSamePictureIsReusedNotDuplicated(): void {
    $profile = $this->profiler->profile('hero_bundle');
    self::assertNotNull($profile);
    $fact = $profile->field('pb_content_image');
    self::assertNotNull($fact);

    $first = $this->images->valueFor($fact);
    $second = $this->images->valueFor($fact);

    self::assertSame($first, $second, 'The same media item is handed out again.');
    self::assertSame($this->images->fileId(), $this->images->fileId());

    $files = $this->container->get('entity_type.manager')->getStorage('file')->loadMultiple();
    self::assertCount(1, $files, 'One file, however many times it was asked for.');

    $media = $this->container->get('entity_type.manager')->getStorage('media')->loadMultiple();
    self::assertCount(1, $media, 'One media item, however many times it was asked for.');
  }

  /**
   * A field that is not a picture is refused, not filled.
   *
   * THE CONTROL, AND THE REASON THE PREDICATE EXISTS. `pb_content_image` is a
   * plain entity_reference, exactly like the required `pb_content_webform` and
   * `pb_content_views`. Measured 2026-09-17: 8 required image references and
   * 11 required non-image ones. Anything filling pictures by field type alone
   * would put one in a webform field.
   */
  public function testNonPictureFieldIsRefused(): void {
    $profile = $this->profiler->profile('simple_bundle');
    self::assertNotNull($profile);

    $fact = $profile->field('pb_content_title');
    self::assertNotNull($fact);
    self::assertFalse($fact->isPicture());
    self::assertNull($this->images->valueFor($fact), 'A title is not a picture.');
  }

  /**
   * Adds a required-or-not reference to a media item.
   *
   * @param string $bundle
   *   Paragraph bundle.
   * @param string $name
   *   Field machine name.
   * @param string $label
   *   Human label.
   * @param bool $required
   *   Whether the section will not save without it.
   */
  private function mediaReference(string $bundle, string $name, string $label, bool $required): void {
    if (FieldStorageConfig::loadByName('paragraph', $name) === NULL) {
      FieldStorageConfig::create([
        'field_name' => $name,
        'entity_type' => 'paragraph',
        'type' => 'entity_reference',
        'settings' => ['target_type' => 'media'],
      ])->save();
    }

    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $bundle,
      'label' => $label,
      'required' => $required,
      'settings' => [
        'handler' => 'default:media',
        'handler_settings' => ['target_bundles' => ['pb_media_image' => 'pb_media_image']],
      ],
    ])->save();
  }

  /**
   * Adds a plain image field.
   *
   * @param string $bundle
   *   Paragraph bundle.
   * @param string $name
   *   Field machine name.
   * @param string $label
   *   Human label.
   * @param bool $required
   *   Whether the section will not save without it.
   */
  private function imageField(string $bundle, string $name, string $label, bool $required): void {
    if (FieldStorageConfig::loadByName('paragraph', $name) === NULL) {
      FieldStorageConfig::create([
        'field_name' => $name,
        'entity_type' => 'paragraph',
        'type' => 'image',
      ])->save();
    }

    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $bundle,
      'label' => $label,
      'required' => $required,
      'settings' => ['alt_field' => TRUE, 'alt_field_required' => TRUE],
    ])->save();
  }

  /**
   * Adds a plain text field.
   *
   * @param string $bundle
   *   Paragraph bundle.
   * @param string $name
   *   Field machine name.
   * @param string $label
   *   Human label.
   */
  private function stringField(string $bundle, string $name, string $label): void {
    if (FieldStorageConfig::loadByName('paragraph', $name) === NULL) {
      FieldStorageConfig::create([
        'field_name' => $name,
        'entity_type' => 'paragraph',
        'type' => 'string',
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
