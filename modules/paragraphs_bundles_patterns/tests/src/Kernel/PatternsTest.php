<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_patterns\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs_bundles_patterns\PatternInserter;
use Drupal\paragraphs_bundles_patterns\PatternRepository;
use Drupal\paragraphs_bundles_patterns\PatternTreeBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Pattern definitions, insert, the loaded look, uninstall and the picker cards.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PatternsTest extends KernelTestBase {

  /**
   * Bundles with Patterns that have no contrib dependency beyond the harness.
   */
  private const BUNDLES = [
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
    'paragraph_bundle_3d_carousel',
    'paragraph_bundle_3d_flip_box',
    'paragraph_bundle_carousel',
    'paragraph_bundle_image_background',
    'paragraph_bundle_image_grid',
    'paragraph_bundle_image_overlay',
    'paragraph_bundle_parallax',
    'paragraph_bundle_slideshow',
  ];

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
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('media');
    $this->installConfig(['system']);
    // The field storages PB's own module ships, as a real site has them.
    $this->container->get('config.installer')->installOptionalConfig();
    // Patterns first, bundles after: the order that once left bundles without
    // their Pattern list.
    $this->container->get('module_installer')->install(['paragraphs_bundles_patterns']);
    $this->container->get('module_installer')->install(self::BUNDLES);
  }

  /**
   * Every definition names real fields, values and files.
   */
  public function testDefinitionsAreValid(): void {
    $repository = $this->container->get(PatternRepository::class);
    $fields = $this->container->get('entity_field.manager');
    $checked = 0;
    foreach ($repository->all() as $bundle => $patterns) {
      if (!isset($this->container->get('entity_type.bundle.info')->getBundleInfo('paragraph')[$bundle])) {
        continue;
      }
      $definitions = $fields->getFieldDefinitions('paragraph', $bundle);
      foreach ($patterns as $name => $pattern) {
        $checked++;
        $this->assertNotSame([], $pattern['css'], "$bundle/$name has CSS");
        foreach ($pattern['settings'] as $field => $value) {
          $this->assertArrayHasKey($field, $definitions, "$bundle/$name setting $field exists");
        }
        $this->assertSame($bundle, $pattern['sample']['bundle'] ?? NULL, "$bundle/$name sample is of its own type");
        foreach (array_keys($pattern['sample']) as $field) {
          if ($field !== 'bundle') {
            $this->assertArrayHasKey($field, $definitions, "$bundle/$name sample field $field exists");
          }
        }
      }
    }
    $this->assertGreaterThan(50, $checked);
  }

  /**
   * A paragraph with no Pattern renders with no Pattern class or library.
   */
  public function testNoPatternNoEffect(): void {
    $build = $this->renderSimple(NULL);
    $this->assertStringNotContainsString('pb-pattern', $build['html']);
    $this->assertNotContains('paragraphs_bundles_patterns/base', $build['libraries']);
  }

  /**
   * A section carrying a Pattern's class prints it and loads that Pattern's look.
   */
  public function testPatternRenders(): void {
    $build = $this->renderSimple('lead');
    $this->assertStringContainsString('pb-pattern--simple-lead', $build['html']);
    $this->assertContains('paragraphs_bundles_patterns/pattern.simple_bundle.lead', $build['libraries']);
  }

  /**
   * A class that names no Pattern loads no Pattern look and breaks nothing.
   */
  public function testUnknownPatternClassLoadsNothing(): void {
    $build = $this->renderSimple('gone');
    $this->assertNotContains('paragraphs_bundles_patterns/pattern.simple_bundle.gone', $build['libraries']);
    $this->assertSame([], array_filter($build['libraries'], static fn (string $library): bool => str_starts_with($library, 'paragraphs_bundles_patterns/pattern.')));
  }

  /**
   * Content that no longer fits its Pattern is saved and shown, never refused.
   */
  public function testChangedContentIsNeverRefused(): void {
    $paragraph = Paragraph::create([
      'type' => 'simple_bundle',
      PatternTreeBuilder::CLASS_FIELD => 'pb-pattern pb-pattern--simple-lead',
      'pb_content_title' => 'T',
      'pb_content_body' => ['value' => 'Short.', 'format' => 'plain_text'],
    ]);
    $this->assertSame(SAVED_NEW, $paragraph->save());
    $build = $this->renderParagraph($paragraph);
    // The harness has no text formats installed, so the body prints empty here;
    // the title shows that the section rendered.
    $this->assertStringContainsString('<div>T</div>', $build['html']);
    $this->assertStringContainsString('pb-pattern--simple-lead', $build['html']);
  }

  /**
   * Insert builds the section with the sample words, the settings and the class.
   */
  public function testTreeBuilderWritesSamplesSettingsAndClass(): void {
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $host = Node::create(['type' => 'page', 'title' => 'Host']);
    $pattern = $this->container->get(PatternRepository::class)->get('simple_bundle', 'pull_quote');
    $this->assertNotNull($pattern);
    $built = $this->container->get(PatternTreeBuilder::class)->build($pattern, $host);
    $paragraph = $built['paragraph'];
    $this->assertSame('simple_bundle', $paragraph->bundle());
    $this->assertSame('Dana Reyes, Founder', $paragraph->get('pb_content_title')->value);
    $this->assertSame('div', $paragraph->get('pb_content_title_tag')->value, 'The Pattern setting is written.');
    $this->assertSame('pb-pattern pb-pattern--simple-pull-quote', $paragraph->get(PatternTreeBuilder::CLASS_FIELD)->value);
    $this->assertStringContainsString('They understood what we needed', (string) $paragraph->get('pb_content_body')->value);
    $this->assertNull($paragraph->id(), 'Building does not save.');
  }

  /**
   * A card whose bundle module is off names that module and cannot be picked.
   */
  public function testCardNamesTheMissingModule(): void {
    $account = $this->container->get('current_user');
    $cards = $this->container->get(PatternInserter::class)->cards([], $account);
    $this->assertSame([], $cards['simple_bundle.lead']['missing']);
    $this->assertNotSame([], $cards['video_bundle.slate']['missing'], 'Video is not enabled here.');
    $this->assertNull($cards['video_bundle.slate']['field']);
  }

  /**
   * Uninstalling keeps the paragraph, its content and its class.
   */
  public function testUninstallKeepsContent(): void {
    $paragraph = Paragraph::create([
      'type' => 'simple_bundle',
      PatternTreeBuilder::CLASS_FIELD => 'pb-pattern pb-pattern--simple-lead',
      'pb_content_title' => 'Kept title',
    ]);
    $paragraph->save();
    $this->container->get('module_installer')->uninstall(['paragraphs_bundles_patterns']);
    $stored = $this->container->get('entity_type.manager')->getStorage('paragraph')->loadUnchanged($paragraph->id());
    $this->assertSame('Kept title', $stored->get('pb_content_title')->value);
    $html = $this->renderParagraph($stored)['html'];
    $this->assertStringContainsString('Kept title', $html);
  }

  /**
   * Renders a Simple paragraph.
   *
   * @return array{html: string, libraries: list<string>}
   *   Markup and attached libraries.
   */
  private function renderSimple(?string $pattern): array {
    $paragraph = Paragraph::create([
      'type' => 'simple_bundle',
      PatternTreeBuilder::CLASS_FIELD => $pattern === NULL ? NULL : 'pb-pattern pb-pattern--simple-' . $pattern,
      'pb_content_title' => 'Title',
      'pb_content_body' => ['value' => str_repeat('A full opening sentence. ', 8), 'format' => 'plain_text'],
    ]);
    $paragraph->save();
    return $this->renderParagraph($paragraph);
  }

  /**
   * Renders a paragraph.
   *
   * @return array{html: string, libraries: list<string>}
   *   Markup and attached libraries.
   */
  private function renderParagraph(Paragraph $paragraph): array {
    $build = $this->container->get('entity_type.manager')->getViewBuilder('paragraph')->view($paragraph);
    $html = (string) $this->container->get('renderer')->renderInIsolation($build);
    return ['html' => $html, 'libraries' => $build['#attached']['library'] ?? []];
  }

}
