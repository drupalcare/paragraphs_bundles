<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs_bundles_ai\Service\BundleProfilerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * What a bundle is made of: content, styling, a title, and children.
 *
 * WHAT THESE TESTS ARE GUARDING. Every tool in this module is about to stop
 * guessing at field names and read this service instead, so a wrong answer here
 * is wrong everywhere at once. The two failures that matter are silent ones: a
 * styling control offered to an editor as content, and a bundle whose title
 * field is not `pb_content_title` being treated as having no title.
 *
 * THE ORDER OF THE TWO SOURCES IS ITSELF UNDER TEST. The field groups decide,
 * and the name prefix only covers what the groups do not mention — so the first
 * test here puts a field with NO pb_ prefix inside the Display group, and a
 * field with a `pb_display_` prefix in no group at all, and asserts both land
 * in the same place.
 *
 * @group paragraphs_bundles
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class BundleProfilerTest extends KernelTestBase {

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
    'field_group',
    'entity_reference_revisions',
    'paragraphs',
    'paragraphs_bundles_ai',
  ];

  /**
   * The service under test.
   */
  private BundleProfilerInterface $profiler;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['system', 'field', 'filter']);

    ParagraphsType::create(['id' => 'slideshow_bundle', 'label' => 'Slideshow'])->save();
    ParagraphsType::create(['id' => 'slideshow_section_bundle', 'label' => 'Slideshow section'])->save();
    ParagraphsType::create(['id' => 'tab_section_bundle', 'label' => 'Tab section'])->save();
    ParagraphsType::create(['id' => 'carousel_3d_bundle', 'label' => '3D carousel'])->save();

    // The realistic bundle: a title, a child field, styling, and two fields
    // that exist to test the classification rules rather than the module.
    $this->field('slideshow_bundle', 'pb_content_title', 'string', 'Title');
    $this->field('slideshow_bundle', 'pb_content_title_tag', 'string', 'Title tag');
    $this->field('slideshow_bundle', 'pb_display_bg', 'string', 'Background');
    // No pb_ prefix at all, and grouped under Display: the group must win.
    $this->field('slideshow_bundle', 'site_builder_toggle', 'string', 'Site toggle');
    // Prefixed, and in NO group: the prefix must catch it.
    $this->field('slideshow_bundle', 'pb_display_shadow', 'string', 'Shadow');
    // Neither prefixed nor grouped: it must be reported, not assigned.
    $this->field('slideshow_bundle', 'legacy_note', 'string', 'Legacy note');
    $this->reference('slideshow_bundle', 'pb_content_slideshow_section', 'Slides', ['slideshow_section_bundle' => 'slideshow_section_bundle']);

    $this->groupedDisplay('slideshow_bundle');

    // A section bundle: content only, styled through its parent, and its title
    // is not called "title".
    $this->field('tab_section_bundle', 'pb_content_tab_name', 'string', 'Tab name');
    $this->reference('tab_section_bundle', 'pb_content_tab_body', 'Body', []);

    // A bundle with no title at all — only a setting ABOUT a title.
    $this->field('carousel_3d_bundle', 'pb_content_title_tag', 'string', 'Title tag');

    $this->profiler = $this->container->get(BundleProfilerInterface::class);
  }

  /**
   * The groups decide, and the prefix fills the gaps they leave.
   */
  public function testGroupsDecideAndThePrefixFillsTheGaps(): void {
    $profile = $this->profiler->profile('slideshow_bundle');

    self::assertNotNull($profile);
    self::assertContains(
      'site_builder_toggle',
      $profile->displayFields,
      'A field with no pb_ prefix is styling because the Display group holds it.',
    );
    self::assertContains(
      'pb_display_shadow',
      $profile->displayFields,
      'A prefixed field in no group still lands on Display.',
    );
    self::assertContains('pb_content_title', $profile->contentFields);
    self::assertNotContains('site_builder_toggle', $profile->contentFields);
    self::assertTrue($profile->hasDisplayTab);
  }

  /**
   * A field the groups and the prefix both miss is REPORTED, never assigned.
   *
   * Guessing would put an unknown control in front of an editor as content, and
   * nothing downstream would catch it.
   */
  public function testAnUnknownFieldIsReportedNotGuessed(): void {
    $profile = $this->profiler->profile('slideshow_bundle');

    self::assertNotNull($profile);
    self::assertSame(['legacy_note'], $profile->unclassified);
    self::assertNotContains('legacy_note', $profile->contentFields);
    self::assertNotContains('legacy_note', $profile->displayFields);
  }

  /**
   * The nested groups are followed all the way down.
   *
   * PB nests three deep — wrapper, tab, then a group per concern — so a reader
   * that looks one level down finds group names and no fields whatsoever.
   */
  public function testNestedGroupsAreFollowed(): void {
    $profile = $this->profiler->profile('slideshow_bundle');

    self::assertNotNull($profile);
    // pb_display_bg sits two groups below the Display tab.
    self::assertContains('pb_display_bg', $profile->displayFields);
  }

  /**
   * The title is found by name, and a setting ABOUT the title is not it.
   */
  public function testTitleFieldIsFoundAndSettingsAreNot(): void {
    $slideshow = $this->profiler->profile('slideshow_bundle');
    $section = $this->profiler->profile('tab_section_bundle');
    $carousel = $this->profiler->profile('carousel_3d_bundle');

    self::assertNotNull($slideshow);
    self::assertNotNull($section);
    self::assertNotNull($carousel);

    self::assertSame('pb_content_title', $slideshow->titleField);
    self::assertSame(
      'pb_content_tab_name',
      $section->titleField,
      'Nine field names mean "the title" across the shipped bundles.',
    );
    self::assertNull(
      $carousel->titleField,
      'pb_content_title_tag is a setting about a title, not a title.',
    );
  }

  /**
   * A bundle with no styling is styled through its parent, and says so.
   */
  public function testSectionBundleHasNoDisplayTab(): void {
    $profile = $this->profiler->profile('tab_section_bundle');

    self::assertNotNull($profile);
    self::assertFalse($profile->hasDisplayTab);
    self::assertSame([], $profile->displayFields);
    self::assertStringContainsString('styled through its parent', $profile->line());
  }

  /**
   * Child fields are found, and an empty allow-list means ANY, not NONE.
   *
   * Drupal stores "every bundle is allowed" as an EMPTY target_bundles. Reading
   * that as "nothing is allowed" is the trap this assertion pins down.
   */
  public function testChildFieldsAndTheEmptyMeansAllTrap(): void {
    $slideshow = $this->profiler->profile('slideshow_bundle');
    $section = $this->profiler->profile('tab_section_bundle');

    self::assertNotNull($slideshow);
    self::assertNotNull($section);

    self::assertTrue($slideshow->nests());
    self::assertSame(
      ['slideshow_section_bundle'],
      $slideshow->childFields['pb_content_slideshow_section'],
    );
    self::assertSame(
      [],
      $section->childFields['pb_content_tab_body'],
      'An empty list means any bundle is accepted, never none.',
    );
  }

  /**
   * An unknown bundle is NULL, and every real one is profiled.
   */
  public function testUnknownBundleIsNullAndAllListsTheRest(): void {
    self::assertNull($this->profiler->profile('no_such_bundle'));
    self::assertNull($this->profiler->profile('  '));

    $all = $this->profiler->all();

    self::assertCount(4, $all);
    self::assertSame(
      ['carousel_3d_bundle', 'slideshow_bundle', 'slideshow_section_bundle', 'tab_section_bundle'],
      array_keys($all),
      'Sorted, so the answer reads the same on every site and every run.',
    );
  }

  /**
   * Adds a simple field to a paragraph bundle.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   * @param string $name
   *   Field machine name.
   * @param string $type
   *   Field type.
   * @param string $label
   *   Human label.
   */
  private function field(string $bundle, string $name, string $type, string $label): void {
    if (FieldStorageConfig::loadByName('paragraph', $name) === NULL) {
      FieldStorageConfig::create([
        'field_name' => $name,
        'entity_type' => 'paragraph',
        'type' => $type,
      ])->save();
    }

    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $bundle,
      'label' => $label,
    ])->save();
  }

  /**
   * Adds a paragraph reference field to a bundle.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   * @param string $name
   *   Field machine name.
   * @param string $label
   *   Human label.
   * @param array<string, string> $targets
   *   Allowed bundles, or an empty array for "any".
   */
  private function reference(string $bundle, string $name, string $label, array $targets): void {
    if (FieldStorageConfig::loadByName('paragraph', $name) === NULL) {
      FieldStorageConfig::create([
        'field_name' => $name,
        'entity_type' => 'paragraph',
        'type' => 'entity_reference_revisions',
        'cardinality' => -1,
        'settings' => ['target_type' => 'paragraph'],
      ])->save();
    }

    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $bundle,
      'label' => $label,
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => ['target_bundles' => $targets],
      ],
    ])->save();
  }

  /**
   * Builds a form display grouped the way PB groups one.
   *
   * Three levels deep on purpose: a tabs wrapper, the two tabs, and a group per
   * concern inside Display. `pb_display_shadow` and `legacy_note` are left out
   * of every group deliberately — they are the fallback cases.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   */
  private function groupedDisplay(string $bundle): void {
    $display = EntityFormDisplay::create([
      'targetEntityType' => 'paragraph',
      'bundle' => $bundle,
      'mode' => 'default',
      'status' => TRUE,
    ]);

    foreach ([
      'pb_content_title',
      'pb_content_title_tag',
      'pb_content_slideshow_section',
      'pb_display_bg',
      'site_builder_toggle',
      'pb_display_shadow',
      'legacy_note',
    ] as $weight => $field) {
      $display->setComponent($field, ['weight' => $weight]);
    }

    $group = static fn(array $children, string $parent, string $label, string $format): array => [
      'children' => $children,
      'parent_name' => $parent,
      'label' => $label,
      'region' => 'content',
      'weight' => 0,
      'format_type' => $format,
      'format_settings' => [],
    ];

    $display->setThirdPartySetting('field_group', 'group_pb_tabs_wrapper', $group(
      ['group_pb_tab_content', 'group_pb_tab_display'],
      '',
      'Tabs Wrapper',
      'tabs',
    ));
    $display->setThirdPartySetting('field_group', 'group_pb_tab_content', $group(
      ['pb_content_title', 'pb_content_title_tag', 'pb_content_slideshow_section'],
      'group_pb_tabs_wrapper',
      'Content',
      'tab',
    ));
    $display->setThirdPartySetting('field_group', 'group_pb_tab_display', $group(
      ['group_pb_display_colors', 'site_builder_toggle'],
      'group_pb_tabs_wrapper',
      'Display',
      'tab',
    ));
    $display->setThirdPartySetting('field_group', 'group_pb_display_colors', $group(
      ['pb_display_bg'],
      'group_pb_tab_display',
      'Colors',
      'details',
    ));

    $display->save();
  }

}
