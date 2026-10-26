<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Parallax section link (G4), link item classes (G7), stacked tabs (G13).
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbParallaxLinkTabsFieldsTest extends KernelTestBase {

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
    'link_attributes',
    'options',
    'views',
    'media',
    'media_library',
    'entity_reference_revisions',
    'paragraphs',
    'field_group',
    'paragraphs_bundles',
    // Provides media.type.pb_media_image, which the parallax image field and
    // so the parallax view display depend on.
    'paragraph_bundle_image',
    'paragraph_bundle_parallax',
    'paragraph_bundle_link',
    'paragraph_bundle_tabs',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('media');
    $this->installConfig(['system', 'filter']);
    $this->container->get('config.installer')->installOptionalConfig();
  }

  /**
   * Saves and renders one paragraph.
   *
   * @param array<string, mixed> $values
   *   Paragraph values, including the type.
   */
  private function renderParagraph(array $values): string {
    $paragraph = Paragraph::create($values);
    $paragraph->save();
    $build = $this->container->get('entity_type.manager')->getViewBuilder('paragraph')->view($paragraph);
    return (string) $this->container->get('renderer')->renderRoot($build);
  }

  /**
   * G4: no link when empty; one link with its text when filled.
   */
  public function testParallaxSectionLink(): void {
    $body = ['value' => '<p>Body</p>', 'format' => 'plain_text'];
    $empty = $this->renderParagraph(['type' => 'parallax_section_bundle', 'pb_content_body' => $body]);
    $this->assertStringNotContainsString('field--name-pb-content-link', $empty);
    $this->assertStringNotContainsString('<a ', $empty);

    $filled = $this->renderParagraph([
      'type' => 'parallax_section_bundle',
      'pb_content_body' => $body,
      'pb_content_link' => ['uri' => 'https://example.com/go', 'title' => 'Go there'],
    ]);
    $this->assertStringContainsString('href="https://example.com/go"', $filled);
    $this->assertStringContainsString('Go there', $filled);
    $this->assertSame(1, substr_count($filled, '<a '));
  }

  /**
   * G7: item classes unchanged when empty; sanitised class when set.
   */
  public function testLinkItemClass(): void {
    $base = [
      'type' => 'link_section_bundle',
      'pb_content_link_section_title' => 'Docs',
      'pb_content_link_section_url' => ['uri' => 'https://example.com/docs', 'title' => 'Docs'],
    ];
    $empty = $this->renderParagraph($base);
    $this->assertStringNotContainsString('pb-i-docs', $empty);

    $filled = $this->renderParagraph($base + ['pb_display_css_classes' => 'pb-i-docs "><script>']);
    $this->assertStringContainsString('pb-i-docs', $filled);
    $this->assertStringNotContainsString('<script>', $filled);
  }

  /**
   * G13: off adds nothing; on with vertical adds the class and orientation.
   */
  public function testTabsStackNarrow(): void {
    $section = Paragraph::create(['type' => 'tab_section_bundle', 'pb_content_tab_name' => 'One']);
    $section->save();
    $base = ['type' => 'tabs_bundle', 'pb_display_tab_vertical_tabs' => 1, 'pb_content_tab_section' => [$section]];

    $off = $this->renderParagraph($base);
    $this->assertStringContainsString('pb-u-vertical-tabs', $off);
    $this->assertStringNotContainsString('pb-u-vertical-stack', $off);
    $this->assertStringNotContainsString('aria-orientation', $off);

    $on = $this->renderParagraph($base + ['pb_display_tab_stack_narrow' => 1]);
    $this->assertStringContainsString('pb-u-vertical-stack', $on);
    $this->assertStringContainsString('aria-orientation="vertical"', $on);
    $this->assertStringContainsString('role="tablist"', $on);

    // Without vertical tabs the option does nothing.
    $horizontal = $this->renderParagraph([
      'type' => 'tabs_bundle',
      'pb_display_tab_stack_narrow' => 1,
      'pb_content_tab_section' => [$section],
    ]);
    $this->assertStringNotContainsString('pb-u-vertical-stack', $horizontal);
  }

}
