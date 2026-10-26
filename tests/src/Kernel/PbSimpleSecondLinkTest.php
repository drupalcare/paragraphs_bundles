<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Simple section: Kicker storage, Second link field, and update 9013 (G5).
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbSimpleSecondLinkTest extends KernelTestBase {

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
   * Renders one Simple section.
   *
   * @param array<string, mixed> $values
   *   Field values.
   */
  private function renderParagraph(array $values): string {
    $paragraph = Paragraph::create(['type' => 'simple_bundle'] + $values);
    $paragraph->save();
    $build = $this->container->get('entity_type.manager')->getViewBuilder('paragraph')->view($paragraph);
    return (string) $this->container->get('renderer')->renderRoot($build);
  }

  /**
   * The second link prints only when filled, after the first link.
   */
  public function testSecondLinkOnlyWhenFilled(): void {
    $empty = $this->renderParagraph(['pb_content_link' => ['uri' => 'https://example.com/a', 'title' => 'First']]);
    $this->assertStringNotContainsString('pb-content-link-secondary', $empty);
    $this->assertStringContainsString('First', $empty);

    $filled = $this->renderParagraph([
      'pb_content_link' => ['uri' => 'https://example.com/a', 'title' => 'First'],
      'pb_content_link_secondary' => ['uri' => 'https://example.com/b', 'title' => 'Second'],
    ]);
    // Kernel tests render with no theme, so there is no field wrapper class to
    // match; the link itself is the evidence.
    $this->assertStringContainsString('href="https://example.com/b"', $filled);
    $this->assertGreaterThan(strpos($filled, 'First'), strpos($filled, 'Second'));
  }

  /**
   * Both storages ship, and the field sits in the Content tab at weight 4+.
   */
  public function testConfigShipsForNewSites(): void {
    $manager = $this->container->get('entity_field.manager');
    $storages = $manager->getFieldStorageDefinitions('paragraph');
    $this->assertArrayHasKey('pb_content_kicker', $storages);
    $this->assertArrayHasKey('pb_content_link_secondary', $storages);
    $display = $this->container->get('entity_type.manager')->getStorage('entity_form_display')->load('paragraph.simple_bundle.default');
    $component = $display->getComponent('pb_content_link_secondary');
    $this->assertNotNull($component);
    $this->assertGreaterThan(3, $component['weight']);
    $group = $display->getThirdPartySetting('field_group', 'group_pb_tab_content');
    $this->assertContains('pb_content_link_secondary', $group['children']);
  }

  /**
   * Update 9013 adds the field to a site without it, and runs twice safely.
   */
  public function testUpdate9013IsIdempotent(): void {
    $entity_type_manager = $this->container->get('entity_type.manager');
    $entity_type_manager->getStorage('field_config')->load('paragraph.simple_bundle.pb_content_link_secondary')->delete();
    $form = $entity_type_manager->getStorage('entity_form_display')->load('paragraph.simple_bundle.default');
    $this->assertNull($form->getComponent('pb_content_link_secondary'));

    $this->container->get('module_handler')->loadInclude('paragraphs_bundles', 'install');
    $first = paragraphs_bundles_update_9013();
    $this->assertStringContainsString('Added', $first);
    $this->assertNotNull($entity_type_manager->getStorage('field_config')->load('paragraph.simple_bundle.pb_content_link_secondary'));
    $second = paragraphs_bundles_update_9013();
    $this->assertStringContainsString('already present', $second);
  }

}
