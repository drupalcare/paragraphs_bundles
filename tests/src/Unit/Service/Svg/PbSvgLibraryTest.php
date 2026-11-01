<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit\Service\Svg;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\paragraphs_bundles\Service\Svg\PbSvgCleaner;
use Drupal\paragraphs_bundles\Service\Svg\PbSvgLibrary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * The library only ever reads pb-svg-icons inside the default theme chain.
 *
 * Runs on real files in a temporary folder: real paths and symlinks are what
 * the rules are about.
 */
#[CoversClass(PbSvgLibrary::class)]
#[Group('paragraphs_bundles')]
final class PbSvgLibraryTest extends UnitTestCase {

  /**
   * The temporary application root.
   */
  private string $tempRoot;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $root = realpath(sys_get_temp_dir());
    $this->tempRoot = $root . '/pb_svg_' . bin2hex(random_bytes(6));
    $drawing = '<svg viewBox="0 0 1 1"><path d="M0 0"/></svg>';
    $this->put('themes/child/images/pb-svg-icons/cars/sports/coupe.svg', $drawing);
    $this->put('themes/child/images/pb-svg-icons/plain.svg', $drawing);
    $this->put('themes/child/images/pb-svg-icons/notes.txt', 'text');
    $this->put('themes/child/images/pb-svg-icons/bad$name.svg', $drawing);
    $this->put('themes/parent/images/pb-svg-icons/arrow.svg', $drawing);
    $this->put('themes/other/images/pb-svg-icons/other.svg', $drawing);
    $this->put('themes/child/secret.svg', $drawing);
    $this->put('outside/out.svg', $drawing);
    $this->put('themes/child/child.info.yml', '');
    $this->put('themes/parent/parent.info.yml', '');
    $this->put('themes/other/other.info.yml', '');
    symlink($this->tempRoot . '/outside/out.svg', $this->tempRoot . '/themes/child/images/pb-svg-icons/link.svg');
    symlink($this->tempRoot . '/outside', $this->tempRoot . '/themes/child/images/pb-svg-icons/linkdir');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->tempRoot, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
      assert($file instanceof \SplFileInfo);
      if ($file->isDir() && !$file->isLink()) {
        rmdir($file->getPathname());
      }
      else {
        unlink($file->getPathname());
      }
    }
    rmdir($this->tempRoot);
    parent::tearDown();
  }

  /**
   * Writes a file below the root.
   */
  private function put(string $path, string $content): void {
    $full = $this->tempRoot . '/' . $path;
    if (!is_dir(dirname($full))) {
      mkdir(dirname($full), 0777, TRUE);
    }
    file_put_contents($full, $content);
  }

  /**
   * A library whose default theme is `child`, based on `parent`.
   */
  private function library(string $default = 'child', ?MemoryBackend $cache = NULL): PbSvgLibrary {
    $child = new Extension($this->tempRoot, 'theme', 'themes/child/child.info.yml');
    $child->info = ['name' => 'Child'];
    $child->base_themes = ['parent' => 'Parent'];
    $parent = new Extension($this->tempRoot, 'theme', 'themes/parent/parent.info.yml');
    $parent->info = ['name' => 'Parent'];
    $other = new Extension($this->tempRoot, 'theme', 'themes/other/other.info.yml');
    $other->info = ['name' => 'Other'];
    $themes = ['child' => $child, 'parent' => $parent, 'other' => $other];

    $handler = $this->createStub(ThemeHandlerInterface::class);
    $handler->method('listInfo')->willReturn($themes);
    $handler->method('themeExists')->willReturnCallback(static fn (string $name): bool => isset($themes[$name]));
    $handler->method('getTheme')->willReturnCallback(static fn (string $name): Extension => $themes[$name]);
    $urls = $this->createStub(FileUrlGeneratorInterface::class);
    $urls->method('generateString')->willReturnCallback(static fn (string $uri): string => '/' . $uri);
    return new PbSvgLibrary(
      $handler,
      $this->getConfigFactoryStub(['system.theme' => ['default' => $default]]),
      $cache ?? new MemoryBackend($this->createStub(TimeInterface::class)),
      $urls,
      new PbSvgCleaner(),
      $this->tempRoot,
    );
  }

  /**
   * Only good files of the chain are offered, default theme first.
   */
  public function testScan(): void {
    $items = $this->library()->items();
    self::assertSame(['child:cars/sports/coupe', 'child:plain', 'parent:arrow'], array_keys($items));
    self::assertSame('cars / sports', $items['child:cars/sports/coupe']['group']);
    self::assertSame('Child', $items['child:plain']['theme_label']);
    self::assertSame('Child / cars / sports / coupe', $this->library()->options()['child:cars/sports/coupe']);
  }

  /**
   * Values that must never resolve.
   *
   * @return array<string, array{string}>
   *   The stored value.
   */
  public static function refusedProvider(): array {
    return [
      'no theme' => ['plain'],
      'empty' => [''],
      'unknown theme' => ['nope:plain'],
      'theme outside the chain' => ['other:other'],
      'traversal' => ['child:../../secret'],
      'traversal encoded' => ['child:%2e%2e/secret'],
      'absolute' => ['child:/etc/passwd'],
      'extension included' => ['child:plain.svg'],
      'missing' => ['child:missing'],
      'symlinked file' => ['child:link'],
      'symlinked folder' => ['child:linkdir/out'],
      'bad character' => ['child:bad$name'],
      'null byte' => ["child:plain\0"],
    ];
  }

  /**
   * Each refused value resolves to nothing and renders nothing.
   */
  #[DataProvider('refusedProvider')]
  public function testRefused(string $value): void {
    $library = $this->library();
    self::assertNull($library->resolve($value));
    self::assertArrayNotHasKey('#value', $library->build($value, 'art'));
  }

  /**
   * A pb-svg-icons folder that is itself a symlink is not used at all.
   */
  public function testSymlinkedFolderRefused(): void {
    $folder = $this->tempRoot . '/themes/parent/images/pb-svg-icons';
    unlink($folder . '/arrow.svg');
    rmdir($folder);
    symlink($this->tempRoot . '/outside', $folder);
    $library = $this->library();
    self::assertNull($library->resolve('parent:out'));
    self::assertArrayNotHasKey('parent:out', $library->items());
  }

  /**
   * A theme outside the chain stops being printable when the site switches.
   */
  public function testChainFollowsDefaultTheme(): void {
    self::assertNull($this->library('other')->resolve('child:plain'));
    self::assertNotNull($this->library('other')->resolve('other:other'));
    self::assertSame(['parent:arrow'], array_keys($this->library('parent')->items()));
  }

  /**
   * New files appear without a cache clear, through the same cache bin.
   */
  public function testNewFileRefreshesTheList(): void {
    $cache = new MemoryBackend($this->createStub(TimeInterface::class));
    self::assertArrayNotHasKey('child:cars/new', $this->library('child', $cache)->items());
    // Filesystem times have one second resolution: move the folder's time.
    $this->put('themes/child/images/pb-svg-icons/cars/new.svg', '<svg></svg>');
    touch($this->tempRoot . '/themes/child/images/pb-svg-icons/cars', time() + 5);
    self::assertArrayHasKey('child:cars/new', $this->library('child', $cache)->items());
  }

  /**
   * Icons and backgrounds are decorative; a titled illustration is named.
   */
  public function testAccessibility(): void {
    $this->put('themes/child/images/pb-svg-icons/named.svg', '<svg><title>A "car"</title></svg>');
    $library = $this->library();
    self::assertSame('true', $library->build('child:named', 'icon')['#attributes']['aria-hidden']);
    self::assertSame('true', $library->build('child:named', 'background')['#attributes']['aria-hidden']);
    $art = $library->build('child:named', 'art');
    self::assertSame('img', $art['#attributes']['role']);
    self::assertSame('A "car"', $art['#attributes']['aria-label']);
    self::assertSame('true', $library->build('child:plain', 'art')['#attributes']['aria-hidden']);
  }

}
