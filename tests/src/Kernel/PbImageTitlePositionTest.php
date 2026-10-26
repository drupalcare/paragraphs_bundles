<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The image bundles print the title above or below the picture (P6).
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbImageTitlePositionTest extends KernelTestBase {

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
    'entity_reference_revisions',
    'paragraphs',
    'field_group',
    'paragraphs_bundles',
    'paragraph_bundle_image',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('media');
    $this->installConfig(['system']);
    $this->container->get('config.installer')->installOptionalConfig();
  }

  /**
   * Renders one paragraph of the given type and values.
   *
   * @param array<string, mixed> $values
   *   Field values.
   */
  private function renderParagraph(array $values): string {
    $paragraph = Paragraph::create($values);
    $paragraph->save();
    $build = $this->container->get('entity_type.manager')->getViewBuilder('paragraph')->view($paragraph);
    return (string) $this->container->get('renderer')->renderRoot($build);
  }

  /**
   * Default and `above` keep the title first; `below` puts it after.
   */
  public function testTitlePosition(): void {
    foreach (['image_bundle', 'image_narrow_bundle', 'image_wide_bundle'] as $type) {
      foreach (['' => 'before', 'above' => 'before', 'below' => 'after'] as $position => $expected) {
        $values = ['type' => $type, 'pb_content_title' => 'Shelf title'];
        if ($position !== '') {
          $values['pb_content_title_position'] = $position;
        }
        $html = $this->renderParagraph($values);
        $title = strpos($html, 'pb-content__title ');
        $picture = strpos($html, 'pb-image__wrapper');
        $this->assertNotFalse($title, "$type/$position prints the title.");
        $this->assertNotFalse($picture, "$type/$position prints the picture wrapper.");
        $this->assertSame($expected, $title < $picture ? 'before' : 'after', "$type/$position title order.");
        $this->assertSame($position === 'below', str_contains($html, 'pb-content__title--below'), "$type/$position modifier class.");
        // The flat top corners join a title above; a title below must not
        // flatten them.
        $this->assertSame($position !== 'below', str_contains($html, 'pb-u-dis-top-left-right'), "$type/$position corner class.");
      }
    }
  }

}
