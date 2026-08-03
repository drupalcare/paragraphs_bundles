<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Nested section bodies render, and carry their cacheability with them.
 *
 * The tabs, accordion and slideshow bundles nest a body field inside a section
 * paragraph. `PbBundleRenderKernelTest` renders every bundle empty, so nothing
 * covered the populated path — which is the one that used to empty `#cache`
 * and lose the body paragraph's cache tags.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbSectionBodyRenderTest extends KernelTestBase {

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
    'paragraph_bundle_content',
    'paragraph_bundle_accordion',
    'paragraph_bundle_tabs',
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
   * The three bundles that nest bodies inside sections.
   *
   * @return list<array{string, string, string, string}>
   *   Wrapper bundle, section reference field, section bundle, body field.
   */
  public static function sectionBundleProvider(): array {
    return [
      'tabs' => [
        'tabs_bundle',
        'pb_content_tab_section',
        'tab_section_bundle',
        'pb_content_tab_body',
      ],
      'accordion' => [
        'accordion_bundle',
        'pb_content_accordion_section',
        'accordion_section_bundle',
        'pb_content_accordion_body',
      ],
      'slideshow' => [
        'slideshow_bundle',
        'pb_content_slideshow_section',
        'slideshow_section_bundle',
        'pb_content_slideshow_body',
      ],
    ];
  }

  /**
   * Body text reaches the page, and the body's cache tag reaches the build.
   *
   * @param string $wrapperBundle
   *   Wrapper paragraph bundle.
   * @param string $sectionField
   *   Field on the wrapper referencing sections.
   * @param string $sectionBundle
   *   Section paragraph bundle.
   * @param string $bodyField
   *   Field on the section referencing body paragraphs.
   */
  #[DataProvider('sectionBundleProvider')]
  public function testSectionBodiesRenderWithCacheability(
    string $wrapperBundle,
    string $sectionField,
    string $sectionBundle,
    string $bodyField,
  ): void {
    $types = $this->container->get('entity_type.manager')
      ->getStorage('paragraphs_type')
      ->loadMultiple();
    foreach ([$wrapperBundle, $sectionBundle, 'simple_bundle'] as $required) {
      $this->assertArrayHasKey($required, $types, "Bundle $required is installed.");
    }

    $body = Paragraph::create([
      'type' => 'simple_bundle',
      'pb_content_body' => [
        'value' => '<p>Nested section body marker.</p>',
        'format' => 'plain_text',
      ],
    ]);
    $body->save();

    // The section is deliberately created with **no title**. That is
    // load-bearing, not incidental: `entity.field.value` on an empty field
    // resolves to `getValue()` and returns an array, so any string filter
    // chained off it fatals. That is exactly how the untitled-accordion fatal
    // shipped. Keeping the title empty here is what covers the same expression
    // in the tabs (`pb_content_tab_name`) and slideshow
    // (`pb_content_slideshow_title`) templates. Do not populate it.
    $section = Paragraph::create([
      'type' => $sectionBundle,
      $bodyField => [$body],
    ]);
    $section->save();

    $wrapper = Paragraph::create([
      'type' => $wrapperBundle,
      $sectionField => [$section],
    ]);
    $wrapper->save();

    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('paragraph')
      ->view($wrapper);
    $html = (string) $this->container->get('renderer')->renderRoot($build);

    $this->assertStringContainsString(
      'Nested section body marker.',
      $html,
      "$wrapperBundle renders its nested body paragraph.",
    );
    $this->assertContains(
      'paragraph:' . $body->id(),
      $build['#cache']['tags'] ?? [],
      "$wrapperBundle bubbles the nested body paragraph's cache tag, so editing "
      . 'the body invalidates the page that shows it.',
    );
  }

}
