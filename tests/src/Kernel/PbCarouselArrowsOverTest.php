<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The carousel's Arrows Over The Slides option sets its class (P7).
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbCarouselArrowsOverTest extends KernelTestBase {

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
    'paragraph_bundle_carousel',
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
   * The class appears only with the option on and arrows in the navigation.
   */
  public function testArrowsOverClass(): void {
    $cases = [
      'default' => [[], FALSE],
      'off' => [['pb_display_carousel_arrows_over' => 0], FALSE],
      'on' => [['pb_display_carousel_arrows_over' => 1], TRUE],
      'on, dots only' => [['pb_display_carousel_arrows_over' => 1, 'pb_display_navigation' => 'dots'], FALSE],
    ];
    foreach ($cases as $label => [$values, $expected]) {
      $html = $this->renderParagraph(['type' => 'carousel_bundle'] + $values);
      $this->assertStringContainsString('pb-carousel', $html, "$label renders the carousel.");
      $this->assertSame($expected, str_contains($html, 'pb-has-arrows-over'), "$label: pb-has-arrows-over.");
    }
  }

}
