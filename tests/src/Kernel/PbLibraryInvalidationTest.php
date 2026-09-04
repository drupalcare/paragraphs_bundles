<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Library info is invalidated only when a paragraph's CSS actually changes.
 *
 * Every paragraph save used to invalidate the site-wide `library_info` tag,
 * so saving a node with 60 paragraphs threw away the whole library registry 60
 * times — and did it even when no display setting had changed. CSS filenames
 * are content-addressed, so an unchanged paragraph produces the same filename,
 * the same library definition, and nothing anyone needs to rebuild.
 *
 * Wall-clock benchmarks cannot show this: the cost lands on *other* requests,
 * as cache misses.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbLibraryInvalidationTest extends KernelTestBase {

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
    'options',
    'link',
    'entity_reference_revisions',
    'paragraphs',
    'field_group',
    'paragraphs_bundles',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['system']);
    $this->container->get('config.installer')->installOptionalConfig();
  }

  /**
   * Saves only bust the library registry when the CSS file moves.
   */
  public function testLibraryInfoInvalidatedOnlyOnRealChange(): void {
    $paragraph = Paragraph::create([
      'type' => 'simple_bundle',
      'pb_display_bg' => '#1a2b3c',
    ]);

    $before = $this->checksum();
    $paragraph->save();
    $afterCreate = $this->checksum();
    $this->assertNotSame(
      $before,
      $afterCreate,
      'Creating a paragraph with display colours registers a new library.',
    );

    // Re-save with nothing changed: same CSS, same content-addressed filename,
    // same library definition — nothing to rebuild.
    $paragraph->save();
    $this->assertSame(
      $afterCreate,
      $this->checksum(),
      'Re-saving an unchanged paragraph must not invalidate library_info.',
    );

    // A real change moves the filename, so the library definition changes.
    $paragraph->set('pb_display_bg', '#ff0000')->save();
    $afterChange = $this->checksum();
    $this->assertNotSame(
      $afterCreate,
      $afterChange,
      'Changing a display colour must invalidate library_info.',
    );

    // Deleting removes the library.
    $paragraph->delete();
    $this->assertNotSame(
      $afterChange,
      $this->checksum(),
      'Deleting a paragraph with a CSS file must invalidate library_info.',
    );
  }

  /**
   * Deleting a paragraph that never had CSS invalidates nothing.
   */
  public function testDeletingParagraphWithoutCssDoesNotInvalidate(): void {
    $paragraph = Paragraph::create(['type' => 'simple_bundle']);
    $paragraph->save();

    $before = $this->checksum();
    $paragraph->delete();
    $this->assertSame(
      $before,
      $this->checksum(),
      'A paragraph with no CSS file has no library to invalidate.',
    );
  }

  /**
   * Current cache-tag checksum for `library_info`.
   *
   * @return int
   *   Checksum, which changes whenever the tag is invalidated.
   */
  private function checksum(): int {
    return (int) $this->container->get('cache_tags.invalidator.checksum')
      ->getCurrentChecksum(['library_info']);
  }

}
