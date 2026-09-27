<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs_bundles_ai\Service\BundleProfilerInterface;
use Drupal\paragraphs_bundles_ai\Service\SectionFeaturesInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Covers the features a new section is switched on with, and their note.
 *
 * Maintainer, 2026-09-27: *"if you dont show the user the capablities of each
 * bundle they will never know."* The values are held to the real field before
 * they are written, and described in the words the field's own form uses.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class SectionFeaturesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'options',
    'file',
    'entity_reference_revisions',
    'paragraphs',
    'key',
    'ai',
    'paragraphs_bundles',
    'paragraphs_bundles_ai',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['system', 'field', 'filter']);

    ParagraphsType::create(['id' => 'show_bundle', 'label' => 'Show'])->save();
    $this->field('pb_content_title', 'string', 'Title');
    $this->field('pb_display_nav', 'list_string', 'Navigation', [
      'allowed_values' => ['arrows' => 'Arrows', 'both' => 'Arrows and Dots'],
    ]);
    $this->field('pb_display_loop', 'boolean', 'Activate Looping');
    $this->field('pb_display_cols', 'integer', 'Number Of Columns');
    $this->field('pb_display_colour', 'string', 'Active Colour');

    $this->config('paragraphs_bundles_ai.briefs')->set('briefs', [
      'show_bundle' => [
        'category' => 'Interactive sections',
        'good_for' => 'Testing.',
        'practices' => 'None.',
        'features' => [
          'pb_display_nav' => 'both',
          'pb_display_loop' => '1',
          'pb_display_cols' => '3',
          // Each of these must be dropped, for a different reason.
          'pb_display_colour' => '#ffffff',
          'pb_display_gone' => 'x',
          'pb_content_title' => 'A content field is not a feature',
        ],
      ],
    ])->save();
  }

  /**
   * Only real display settings survive, each typed for its field.
   */
  public function testFeaturesAreHeldToTheRealFields(): void {
    $profile = $this->container->get(BundleProfilerInterface::class)->profile('show_bundle');
    self::assertNotNull($profile);

    self::assertSame(
      ['pb_display_nav' => 'both', 'pb_display_loop' => TRUE, 'pb_display_cols' => 3],
      $this->container->get(SectionFeaturesInterface::class)->valuesFor($profile),
    );
  }

  /**
   * An option is named by its label, a boolean as on or off.
   */
  public function testTheNoteUsesTheFieldsOwnWords(): void {
    $profile = $this->container->get(BundleProfilerInterface::class)->profile('show_bundle');
    self::assertNotNull($profile);
    $features = $this->container->get(SectionFeaturesInterface::class);

    self::assertSame(
      ['Navigation: Arrows and Dots', 'Activate Looping: on', 'Number Of Columns: 3'],
      $features->describe($profile, $features->valuesFor($profile)),
    );
  }

  /**
   * An invalid list value is dropped, not saved.
   */
  public function testValueTheListDoesNotOfferIsDropped(): void {
    $this->config('paragraphs_bundles_ai.briefs')
      ->set('briefs.show_bundle.features', ['pb_display_nav' => 'bothh'])
      ->save();
    $profile = $this->container->get(BundleProfilerInterface::class)->profile('show_bundle');
    self::assertNotNull($profile);

    self::assertSame([], $this->container->get(SectionFeaturesInterface::class)->valuesFor($profile));
  }

  /**
   * Adds a field to the test bundle.
   *
   * @param string $name
   *   Field machine name.
   * @param string $type
   *   Field type.
   * @param string $label
   *   Label.
   * @param array<string, mixed> $settings
   *   Storage settings.
   */
  private function field(string $name, string $type, string $label, array $settings = []): void {
    if (FieldStorageConfig::loadByName('paragraph', $name) === NULL) {
      FieldStorageConfig::create([
        'field_name' => $name,
        'entity_type' => 'paragraph',
        'type' => $type,
        'settings' => $settings,
      ])->save();
    }
    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => 'show_bundle',
      'label' => $label,
    ])->save();
  }

}
