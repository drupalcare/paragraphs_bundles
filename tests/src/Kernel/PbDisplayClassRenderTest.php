<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Display-field values reach the rendered inner wrapper, for every bundle.
 *
 * The mapping from a stored display value to a CSS class used to be rebuilt by
 * an identical Twig loop in each of 31 templates. It now lives in
 * PbDisplayClassResolver and arrives as `pb_display_classes`. This asserts the
 * two ends still meet: whatever the resolver returns for a bundle must land
 * together on one element of that bundle's rendered output.
 *
 * The other render tests create paragraphs with no field values at all, so
 * without this a template could stop emitting display classes entirely and
 * every existing test would still pass.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbDisplayClassRenderTest extends KernelTestBase {

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
    // Bundle family whose deps ship in the harness (excludes link/views/
    // webform/contact_form, which need external contrib modules).
    'paragraph_bundle_content',
    'paragraph_bundle_image',
    'paragraph_bundle_hero',
    'paragraph_bundle_layout',
    'paragraph_bundle_grid',
    'paragraph_bundle_card',
    'paragraph_bundle_accordion',
    'paragraph_bundle_tabs',
    'paragraph_bundle_alert',
    'paragraph_bundle_icon',
    'paragraph_bundle_modal',
    'paragraph_bundle_block',
    'paragraph_bundle_block_content',
    'paragraph_bundle_3d_carousel',
    'paragraph_bundle_3d_flip_box',
    'paragraph_bundle_carousel',
    'paragraph_bundle_image_background',
    'paragraph_bundle_image_grid',
    'paragraph_bundle_image_overlay',
    'paragraph_bundle_node_reference',
    'paragraph_bundle_parallax',
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
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('media');
    $this->installConfig(['system']);
    $this->container->get('config.installer')->installOptionalConfig();
  }

  /**
   * Every bundle renders the display classes its resolver reports.
   */
  public function testDisplayClassesReachTheInnerWrapper(): void {
    $resolver = $this->container->get('paragraphs_bundles.display_class_resolver');
    $view_builder = $this->container->get('entity_type.manager')
      ->getViewBuilder('paragraph');
    $renderer = $this->container->get('renderer');

    $type_ids = array_keys(
      $this->container->get('entity_type.manager')
        ->getStorage('paragraphs_type')
        ->loadMultiple(),
    );
    sort($type_ids);
    $this->assertGreaterThan(10, count($type_ids));

    $asserted = 0;
    foreach ($type_ids as $type_id) {
      $paragraph = Paragraph::create($this->populatedValues($type_id));
      $paragraph->save();

      $expected = $resolver->innerClasses($paragraph);
      if ($expected === []) {
        // Section wrappers carry no display fields of their own.
        continue;
      }

      $html = (string) $renderer->renderRoot($view_builder->view($paragraph));
      $this->assertTrue(
        $this->hasElementWithAllClasses($html, $expected),
        sprintf(
          'Bundle %s renders %s together on one element.',
          $type_id,
          implode(' ', $expected),
        ),
      );
      $asserted++;
    }

    // Guard against the loop silently skipping everything.
    $this->assertGreaterThan(10, $asserted);
  }

  /**
   * Builds paragraph values with every display list field set.
   *
   * @param string $type_id
   *   Paragraph bundle.
   *
   * @return array<string, mixed>
   *   Entity values.
   */
  private function populatedValues(string $type_id): array {
    $values = ['type' => $type_id];
    $definitions = $this->container->get('entity_field.manager')
      ->getFieldDefinitions('paragraph', $type_id);

    foreach ($definitions as $name => $definition) {
      if (!str_starts_with($name, 'pb_display_') || $definition->getType() !== 'list_string') {
        continue;
      }
      $storage = FieldStorageConfig::loadByName('paragraph', $name);
      $allowed = $storage?->getSetting('allowed_values');
      if (!is_array($allowed) || $allowed === []) {
        continue;
      }
      $first = reset($allowed);
      $values[$name] = is_array($first) && isset($first['value'])
        ? $first['value']
        : (string) array_key_first($allowed);
    }
    return $values;
  }

  /**
   * Whether one element's class attribute carries all the given classes.
   *
   * @param string $html
   *   Rendered markup.
   * @param list<string> $classes
   *   Classes that must appear together.
   *
   * @return bool
   *   TRUE when a single class attribute contains all of them.
   */
  private function hasElementWithAllClasses(string $html, array $classes): bool {
    preg_match_all('/class="([^"]*)"/', $html, $matches);
    foreach ($matches[1] as $attribute) {
      $present = preg_split('/\s+/', trim($attribute)) ?: [];
      if (array_diff($classes, $present) === []) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
