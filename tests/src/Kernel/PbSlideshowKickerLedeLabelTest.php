<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Slideshow: Kicker (G1), Lede (G2), visible Play/Pause text (G11), 9002.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbSlideshowKickerLedeLabelTest extends KernelTestBase {

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
    'node',
    'entity_reference_revisions',
    'paragraphs',
    'field_group',
    'paragraphs_bundles',
    'paragraph_bundle_image',
    'paragraph_bundle_slideshow',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('media');
    $this->installConfig(['system', 'filter']);
    $this->container->get('config.installer')->installOptionalConfig();
  }

  /**
   * Renders one autoplaying slideshow with one slide.
   *
   * @param array<string, mixed> $values
   *   Extra field values.
   */
  private function renderParagraph(array $values): string {
    $section = Paragraph::create(['type' => 'slideshow_section_bundle']);
    $section->save();
    $paragraph = Paragraph::create([
      'type' => 'slideshow_bundle',
      'pb_content_title' => 'Section title',
      'pb_display_slide_play_stop' => 3000,
      'pb_content_slideshow_section' => [[
        'target_id' => $section->id(),
        'target_revision_id' => $section->getRevisionId(),
      ],
      ],
    ] + $values);
    $paragraph->save();
    $build = $this->container->get('entity_type.manager')->getViewBuilder('paragraph')->view($paragraph, 'default');
    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

  /**
   * Empty fields add nothing; filled ones print around the title.
   */
  public function testKickerAndLedeOnlyWhenFilled(): void {
    $empty = $this->renderParagraph([]);
    $this->assertStringNotContainsString('pb-content__kicker', $empty);
    $this->assertStringNotContainsString('pb-content__lede', $empty);

    $filled = $this->renderParagraph([
      'pb_content_kicker' => 'Kicker <b>text</b>',
      'pb_content_body' => ['value' => '<p>Lede words</p>', 'format' => 'plain_text'],
    ]);
    $this->assertStringContainsString('<p class="pb-content__kicker">Kicker &lt;b&gt;text&lt;/b&gt;</p>', $filled);
    $this->assertStringContainsString('pb-content__lede', $filled);
    $kicker = strpos($filled, 'pb-content__kicker');
    $title = strpos($filled, 'Section title');
    $lede = strpos($filled, 'pb-content__lede');
    $this->assertLessThan($title, $kicker);
    $this->assertGreaterThan($title, $lede);
  }

  /**
   * The Play/Pause text option: off = icon only, on = words in the icon spans.
   */
  public function testPlayLabel(): void {
    $off = $this->renderParagraph([]);
    $this->assertStringContainsString('pb-nav__play', $off);
    $this->assertStringNotContainsString('pb-nav__play--labelled', $off);
    $this->assertStringNotContainsString('pb-nav__play-text', $off);

    $on = $this->renderParagraph(['pb_display_slide_play_label' => 1]);
    $this->assertStringContainsString('pb-nav__play--labelled', $on);
    $this->assertMatchesRegularExpression('#<span class="pb-nav__play-icon" hidden>.*?<span class="pb-nav__play-text">Play</span></span>#s', $on);
    $this->assertMatchesRegularExpression('#<span class="pb-nav__pause-icon">.*?<span class="pb-nav__pause-text">Pause</span></span>#s', $on);
    // The accessible name is still the button's aria-label, which contains
    // the visible word (WCAG 2.5.3).
    $this->assertStringContainsString('aria-label="Pause slideshow"', $on);
  }

  /**
   * Update 9002 adds the fields to a site without them, and runs twice safely.
   */
  public function testUpdate9002IsIdempotent(): void {
    $etm = $this->container->get('entity_type.manager');
    foreach (['pb_content_kicker', 'pb_content_body', 'pb_display_slide_play_label'] as $field) {
      $etm->getStorage('field_config')->load('paragraph.slideshow_bundle.' . $field)->delete();
    }
    $this->container->get('module_handler')->loadInclude('paragraph_bundle_slideshow', 'install');
    $first = paragraph_bundle_slideshow_update_9002();
    $this->assertStringContainsString('Added', $first);

    $form = $etm->getStorage('entity_form_display')->load('paragraph.slideshow_bundle.default');
    $this->assertSame(4, $form->getComponent('pb_content_kicker')['weight']);
    $this->assertSame(5, $form->getComponent('pb_content_body')['weight']);
    $this->assertGreaterThan(5, $form->getComponent('pb_content_slideshow_section')['weight']);
    $children = $form->getThirdPartySetting('field_group', 'group_pb_tab_content')['children'];
    $expected = [
      'pb_content_title',
      'pb_content_title_tag',
      'pb_content_title_alignment',
      'pb_content_kicker',
      'pb_content_body',
    ];
    $this->assertSame($expected, array_slice($children, 0, 5));
    $this->assertContains('pb_display_slide_play_label', $form->getThirdPartySetting('field_group', 'group_pb_display_extra')['children']);

    $second = paragraph_bundle_slideshow_update_9002();
    $this->assertStringContainsString('already present', $second);
  }

}
