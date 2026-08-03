<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * A slideshow slide is a tabpanel only when there are tabs to own it.
 *
 * The bottom-navigation field has five values, and only four of them render a
 * `role="tablist"` of `role="tab"` buttons. The slides carried
 * `role="tabpanel"` unconditionally, so the two remaining configurations —
 * `play`, and the **unset default** most slideshows ship with — put tabpanels
 * on the page with no tab and no tablist anywhere. `aria-labelledby` had the
 * same shape of bug one step further on: it was gated on the field merely
 * being set, so `play` emitted references to nav-button ids that are never
 * rendered.
 *
 * Both are invisible to a test that renders one configuration, which is why
 * this walks all six.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbSlideshowAriaTest extends KernelTestBase {

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
   * Every bottom-navigation value, plus the unset default.
   *
   * @return array<string, array{string|null, bool}>
   *   Field value (NULL = unset), and whether it renders a tablist.
   */
  public static function bottomNavProvider(): array {
    return [
      // The default. This is the configuration the bug shipped in.
      'unset (default)' => [NULL, FALSE],
      'play' => ['play', FALSE],
      'dot' => ['dot', TRUE],
      'dot_play' => ['dot_play', TRUE],
      'nav' => ['nav', TRUE],
      'nav_play' => ['nav_play', TRUE],
    ];
  }

  /**
   * Tabpanels and tabs appear together, and never one without the other.
   *
   * @param string|null $bottomNav
   *   Bottom-navigation field value, or NULL to leave it unset.
   * @param bool $expectsTablist
   *   Whether this configuration renders a tablist.
   */
  #[DataProvider('bottomNavProvider')]
  public function testSlideRolesMatchTheNavigation(?string $bottomNav, bool $expectsTablist): void {
    $html = $this->renderSlideshow($bottomNav, 2);

    $tabpanels = substr_count($html, 'role="tabpanel"');
    $tabs = substr_count($html, 'role="tab"');
    $tablists = substr_count($html, 'role="tablist"');

    if ($expectsTablist) {
      $this->assertSame(1, $tablists, 'One tablist.');
      $this->assertSame(2, $tabs, 'One tab per slide.');
      $this->assertSame(2, $tabpanels, 'One tabpanel per slide.');
      return;
    }

    $this->assertSame(0, $tablists, 'No tablist in this configuration.');
    $this->assertSame(0, $tabs, 'No tabs in this configuration.');
    $this->assertSame(
      0,
      $tabpanels,
      'A tabpanel with no tab and no tablist is an orphan — slides must not '
      . 'claim the role when nothing owns them.',
    );
    // They are still announced as slides, per the ARIA APG carousel pattern.
    $this->assertSame(
      2,
      substr_count($html, 'aria-roledescription="slide"'),
      'Slides without tabs are groups with a slide role description.',
    );
  }

  /**
   * No aria-labelledby ever points at an id that is not on the page.
   *
   * @param string|null $bottomNav
   *   Bottom-navigation field value, or NULL to leave it unset.
   */
  #[DataProvider('bottomNavProvider')]
  public function testNoDanglingAriaLabelledby(?string $bottomNav): void {
    $html = $this->renderSlideshow($bottomNav, 2);

    $dangling = [];
    if (preg_match_all('/aria-labelledby="([^"]+)"/', $html, $matches) > 0) {
      foreach ($matches[1] as $reference) {
        foreach (preg_split('/\s+/', trim($reference)) ?: [] as $id) {
          if ($id !== '' && !str_contains($html, 'id="' . $id . '"')) {
            $dangling[] = $id;
          }
        }
      }
    }

    $this->assertSame([], $dangling, sprintf(
      'aria-labelledby points at ids that are never rendered: %s',
      implode(', ', $dangling),
    ));
  }

  /**
   * Renders one slideshow with the given navigation setting.
   *
   * @param string|null $bottomNav
   *   Bottom-navigation field value, or NULL to leave it unset.
   * @param int $slides
   *   How many section paragraphs to nest.
   *
   * @return string
   *   Rendered markup.
   */
  private function renderSlideshow(?string $bottomNav, int $slides): string {
    $sections = [];
    for ($i = 1; $i <= $slides; $i++) {
      $section = Paragraph::create(['type' => 'slideshow_section_bundle']);
      $section->save();
      // entity_reference_revisions needs both ids; handing it the entity
      // stores a target_id with no revision and $item->entity comes back NULL,
      // so the slideshow renders with no slides and no error anywhere.
      $sections[] = [
        'target_id' => $section->id(),
        'target_revision_id' => $section->getRevisionId(),
      ];
    }

    $values = [
      'type' => 'slideshow_bundle',
      'pb_content_slideshow_section' => $sections,
      // Non-zero, so the play button renders and the `play` case is real.
      'pb_display_slide_time' => 5000,
    ];
    if ($bottomNav !== NULL) {
      $values['pb_display_bottom_navigation'] = $bottomNav;
    }
    $paragraph = Paragraph::create($values);
    $paragraph->save();

    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('paragraph')
      ->view($paragraph, 'default');

    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

}
