<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel\Service;

use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs_bundles\Service\Css\PbCssFileManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Kernel tests for the content-addressed dynamic-CSS file manager.
 *
 * Exercises the real filesystem behaviour behind the "edit shows without a
 * cache clear" feature: changed content moves the hashed filename (busting the
 * aggregate) while unchanged content keeps it, and stale variants are pruned.
 * A real public:// directory is used because the manager relies on realpath()
 * and glob(), which do not work on the default vfsStream filesystem.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbCssFileManagerTest extends KernelTestBase {

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
    'entity_reference_revisions',
    'paragraphs',
    'field_group',
    'paragraphs_bundles',
  ];

  /**
   * The file manager under test.
   */
  private PbCssFileManagerInterface $fileManager;

  /**
   * {@inheritdoc}
   *
   * Use a real filesystem so realpath()/glob() behave as they do in production.
   */
  protected function setUpFilesystem(): void {
    $this->siteDirectory = sys_get_temp_dir() . '/pb-kernel-' . $this->databasePrefix;
    mkdir($this->siteDirectory . '/files/config/sync', 0775, TRUE);
    $settings = Settings::getInstance() ? Settings::getAll() : [];
    $settings['file_public_path'] = $this->siteDirectory . '/files';
    $settings['config_sync_directory'] = $this->siteDirectory . '/files/config/sync';
    new Settings($settings);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->fileManager = $this->container->get('paragraphs_bundles.css.file_manager');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (isset($this->siteDirectory) && is_dir($this->siteDirectory)) {
      $items = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($this->siteDirectory, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST,
      );
      foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
      }
      rmdir($this->siteDirectory);
    }
    parent::tearDown();
  }

  /**
   * A write produces a content-hashed file that exists and resolves.
   */
  public function testWriteProducesContentHashedFile(): void {
    $this->assertTrue($this->fileManager->write('simple', 42, '.pb-x{color:red}'));
    $this->assertTrue($this->fileManager->exists('simple', 42));
    $this->assertMatchesRegularExpression(
      '/^pb-simple-42\.[a-z0-9]+\.css$/',
      $this->fileManager->fileName('simple', 42),
    );
    $this->assertStringContainsString(
      'pb-simple-42.',
      $this->fileManager->fileUrl('simple', 42),
    );
  }

  /**
   * The dynamic library points at the public:// URI, never at a URL.
   *
   * The library parser strips the leading slash of a `/…` key without
   * removing the base path, so a URL key breaks on a site served from a
   * subdirectory (drupal.org's CI serves `/subdirectory`): the generated
   * stylesheet 404ed and the flip-box scene had no height. A stream-wrapper
   * URI is resolved to the right URL by the asset renderer instead. The
   * kernel base path is `/`, so the URL form would resolve to a bare
   * docroot path here — the assertion is on the URI shape, which is what
   * differs.
   */
  public function testDynamicLibraryUsesStreamWrapperUri(): void {
    $this->assertTrue($this->fileManager->write('simple', 42, '.pb-x{color:red}'));
    $library = $this->container->get('library.discovery')
      ->getLibraryByName('paragraphs_bundles', 'dynamic--simple-42');
    $this->assertIsArray($library, 'The per-paragraph library is registered.');
    $this->assertCount(1, $library['css']);
    $this->assertSame(
      $this->fileManager->fileUri('simple', 42),
      $library['css'][0]['data'],
    );
    $this->assertStringStartsWith('public://paragraphs_bundles/', $library['css'][0]['data']);
  }

  /**
   * Changed content moves the hashed filename and prunes the old one.
   */
  public function testRewriteMovesHashAndPrunesOld(): void {
    $this->fileManager->write('simple', 7, '.a{color:red}');
    $first = $this->fileManager->fileName('simple', 7);

    $this->fileManager->write('simple', 7, '.a{color:blue}');
    $second = $this->fileManager->fileName('simple', 7);

    $this->assertNotSame($first, $second, 'Changed content moves the filename.');

    $dir = $this->container->get('file_system')
      ->realpath(PbCssFileManagerInterface::DIRECTORY);
    $this->assertCount(
      1,
      glob($dir . '/pb-simple-7.*.css') ?: [],
      'Only the current-content file remains after a rewrite.',
    );
  }

  /**
   * Unchanged content keeps the same hashed filename.
   */
  public function testSameContentKeepsHash(): void {
    $this->fileManager->write('hero', 3, '.h{color:green}');
    $before = $this->fileManager->fileName('hero', 3);
    $this->fileManager->write('hero', 3, '.h{color:green}');
    $this->assertSame(
      $before,
      $this->fileManager->fileName('hero', 3),
      'Unchanged content keeps the same hash.',
    );
  }

  /**
   * Delete removes the file; listAll + deleteAll enumerate and wipe.
   */
  public function testDeleteAndEnumerate(): void {
    $this->fileManager->write('card', 9, '.c{}');
    $this->assertTrue($this->fileManager->delete('card', 9));
    $this->assertFalse($this->fileManager->exists('card', 9));

    $this->fileManager->write('alpha', 1, '.a{}');
    $this->fileManager->write('beta', 2, '.b{}');
    $this->assertGreaterThanOrEqual(2, count($this->fileManager->listAll()));
    $this->assertGreaterThanOrEqual(2, $this->fileManager->deleteAll());
    $this->assertSame([], $this->fileManager->listAll());
  }

}
