<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraph_bundle_video\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\Media;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The Video bundle installs correctly and refuses content that cannot work.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbVideoTest extends KernelTestBase {

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
    'file',
    'image',
    'link',
    'options',
    'views',
    'media',
    'media_library',
    'block',
    'block_content',
    'menu_ui',
    'node',
    'entity_reference_revisions',
    'paragraphs',
    'field_group',
    'paragraphs_bundles',
    'paragraph_bundle_image',
    'paragraph_bundle_video',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('media');
    $this->installConfig(['system']);
    $this->container->get('config.installer')->installOptionalConfig();
  }

  /**
   * The ratio keys stay strings the display code can match.
   */
  public function testRatioKeysAreStrings(): void {
    $storage = FieldStorageConfig::loadByName('paragraph', 'pb_display_video_ratio');
    $this->assertNotNull($storage);
    $this->assertSame(['16_9', '4_3', '1_1', '9_16'], array_map('strval', array_keys($storage->getSetting('allowed_values'))));
  }

  /**
   * Background mode refuses a remote video.
   */
  public function testBackgroundRefusesRemote(): void {
    $media = Media::create([
      'bundle' => 'pb_media_remote_video',
      'name' => 'Remote',
      'pb_video_url' => 'https://example.com/v',
    ]);
    $paragraph = Paragraph::create([
      'type' => 'video_bundle',
      'pb_content_video' => [$media],
      'pb_display_video_mode' => 'background',
    ]);
    $this->assertGreaterThan(0, count($paragraph->validate()->getByField('pb_content_video')));
  }

  /**
   * A self-hosted video needs a poster.
   */
  public function testSelfHostedNeedsPoster(): void {
    $media = Media::create(['bundle' => 'pb_media_video', 'name' => 'Own']);
    $paragraph = Paragraph::create([
      'type' => 'video_bundle',
      'pb_content_video' => [$media],
      'pb_display_video_mode' => 'player',
    ]);
    $this->assertGreaterThan(0, count($paragraph->validate()->getByField('pb_content_video')));
  }

}
