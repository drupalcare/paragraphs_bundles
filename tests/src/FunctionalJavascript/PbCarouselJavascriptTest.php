<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\paragraphs_bundles\Traits\AxeTestTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Drives the carousel bundle's next/prev navigation in a real browser.
 *
 * Requires a WebDriver (chromedriver/selenium). Builds a three-slide carousel
 * on a node with autoplay disabled and one slide per page, then asserts the
 * navigation contract: slide one is active on load; clicking Next activates
 * slide two; clicking Previous returns to slide one. Slides are arbitrary
 * lightweight child paragraphs (accordion sections) — only the carousel's own
 * slide wrappers and JS matter here.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbCarouselJavascriptTest extends WebDriverTestBase {

  use AxeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'node',
    'menu_ui',
    'block',
    'block_content',
    'paragraphs_bundles',
    'paragraph_bundle_content',
    'paragraph_bundle_carousel',
    'paragraph_bundle_accordion',
  ];

  /**
   * Next advances the active slide by one; Previous steps it back.
   */
  public function testCarouselNextPrevAdvancesSlide(): void {
    // A minimal install grants anonymous no permissions; allow viewing content
    // so the node renders without a login step.
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('access content')
      ->save();

    $slides = [];
    foreach (['Slide one', 'Slide two', 'Slide three'] as $label) {
      $slide = Paragraph::create([
        'type' => 'accordion_section_bundle',
        'pb_content_accordion_title' => $label,
      ]);
      $slide->save();
      $slides[] = [
        'target_id' => $slide->id(),
        'target_revision_id' => $slide->getRevisionId(),
      ];
    }

    $carousel = Paragraph::create([
      'type' => 'carousel_bundle',
      'pb_content_carousel_body' => $slides,
      // Deterministic: arrows visible, autoplay off, one slide per page.
      'pb_display_navigation' => 'arrows',
      'pb_display_carousel_play_stop' => 0,
      'pb_display_big_screen' => 1,
      'pb_display_small_screen' => 1,
    ]);
    $carousel->save();

    [$nodeType, $fieldName] = $this->discoverParagraphHost();
    $node = $this->drupalCreateNode([
      'type' => $nodeType,
      $fieldName => [
        [
          'target_id' => $carousel->id(),
          'target_revision_id' => $carousel->getRevisionId(),
        ],
      ],
    ]);

    $this->drupalGet($node->toUrl());
    $assert = $this->assertSession();

    // Slides render; the first is active on load, the second is not.
    $assert->waitForElement('css', '.pb-carousel__slide');
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-carousel__slide')[0].classList.contains('pb-is-active')",
    );
    $this->assertJsCondition(
      "!document.querySelectorAll('.pb-carousel__slide')[1].classList.contains('pb-is-active')",
    );

    // Clicking Next advances the active slide to the second.
    $this->getSession()->getPage()->find('css', '.pb-nav__next')->click();
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-carousel__slide')[1].classList.contains('pb-is-active')",
    );
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-carousel__slide')[1].getAttribute('aria-hidden') === 'false'",
    );
    $this->assertJsCondition(
      "!document.querySelectorAll('.pb-carousel__slide')[0].classList.contains('pb-is-active')",
    );

    // Clicking Previous returns the active slide to the first.
    $this->getSession()->getPage()->find('css', '.pb-nav__prev')->click();
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-carousel__slide')[0].classList.contains('pb-is-active')",
    );
    $this->assertJsCondition(
      "!document.querySelectorAll('.pb-carousel__slide')[1].classList.contains('pb-is-active')",
    );
  }

  /**
   * An autoplay carousel exposes a working play/pause toggle (WCAG 2.2.2).
   */
  public function testAutoplayCarouselTogglesPlayPause(): void {
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('access content')
      ->save();

    $slides = [];
    foreach (['Slide one', 'Slide two', 'Slide three'] as $label) {
      $slide = Paragraph::create([
        'type' => 'accordion_section_bundle',
        'pb_content_accordion_title' => $label,
      ]);
      $slide->save();
      $slides[] = [
        'target_id' => $slide->id(),
        'target_revision_id' => $slide->getRevisionId(),
      ];
    }

    $carousel = Paragraph::create([
      'type' => 'carousel_bundle',
      'pb_content_carousel_body' => $slides,
      // Autoplay on (data-slide-time > 0) renders the play/pause control.
      'pb_display_navigation' => 'arrows',
      'pb_display_carousel_play_stop' => 5000,
      'pb_display_big_screen' => 1,
      'pb_display_small_screen' => 1,
    ]);
    $carousel->save();

    [$nodeType, $fieldName] = $this->discoverParagraphHost();
    $node = $this->drupalCreateNode([
      'type' => $nodeType,
      $fieldName => [
        [
          'target_id' => $carousel->id(),
          'target_revision_id' => $carousel->getRevisionId(),
        ],
      ],
    ]);

    $this->drupalGet($node->toUrl());
    $assert = $this->assertSession();

    // The toggle renders and starts in the playing state.
    $toggle = $assert->waitForElement('css', '.pb-carousel__play-pause');
    $this->assertNotNull($toggle, 'The play/pause toggle rendered.');
    $this->assertSame('true', $toggle->getAttribute('aria-pressed'));
    $this->assertJsCondition(
      "document.querySelector('.pb-carousel__play-pause').classList.contains('pb-is-playing')",
    );

    // Clicking pauses autoplay and flips the control state.
    $toggle->click();
    $this->assertJsCondition(
      "document.querySelector('.pb-carousel__play-pause').getAttribute('aria-pressed') === 'false'",
    );
    $this->assertJsCondition(
      "document.querySelector('.pb-carousel__play-pause').classList.contains('pb-is-paused')",
    );

    // Clicking again resumes.
    $toggle->click();
    $this->assertJsCondition(
      "document.querySelector('.pb-carousel__play-pause').getAttribute('aria-pressed') === 'true'",
    );

    // No serious/critical accessibility violations in the rendered widget.
    $this->assertNoAxeViolations('.pb-carousel');
  }

  /**
   * A vertical carousel keeps one height while paging (no layout shift).
   *
   * The frame used to resize to the current page, so items of different
   * heights moved the content below on every page (and on every autoplay
   * tick). It now takes the height of the tallest page.
   */
  public function testVerticalCarouselKeepsOneHeight(): void {
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('access content')
      ->save();

    // The middle slide is much taller: its title wraps over several lines.
    $slides = [];
    foreach (['Slide one', str_repeat('Slide two is much taller. ', 9), 'Slide three'] as $label) {
      $slide = Paragraph::create([
        'type' => 'accordion_section_bundle',
        'pb_content_accordion_title' => $label,
      ]);
      $slide->save();
      $slides[] = [
        'target_id' => $slide->id(),
        'target_revision_id' => $slide->getRevisionId(),
      ];
    }

    $carousel = Paragraph::create([
      'type' => 'carousel_bundle',
      'pb_content_carousel_body' => $slides,
      'pb_display_vertical_carousel' => 'vertical',
      'pb_display_navigation' => 'arrows',
      'pb_display_carousel_play_stop' => 0,
      'pb_display_big_screen' => 1,
      'pb_display_small_screen' => 1,
    ]);
    $carousel->save();

    [$nodeType, $fieldName] = $this->discoverParagraphHost();
    $node = $this->drupalCreateNode([
      'type' => $nodeType,
      $fieldName => [
        [
          'target_id' => $carousel->id(),
          'target_revision_id' => $carousel->getRevisionId(),
        ],
      ],
    ]);

    $this->drupalGet($node->toUrl());
    $this->assertSession()->waitForElement('css', '.pb-carousel__wrapper.pb-is-visible');

    $session = $this->getSession();
    $height = static fn (): int => (int) $session->evaluateScript("return document.querySelector('.pb-carousel__wrapper').offsetHeight;");
    $tallest = (int) $session->evaluateScript("return Math.max(...[...document.querySelectorAll('.pb-carousel__slide')].map((s) => s.offsetHeight));");

    $first = $height();
    $this->assertSame($tallest, $first, 'The frame is as tall as the tallest page.');
    foreach ([1, 2] as $index) {
      $session->getPage()->find('css', '.pb-nav__next')->click();
      $this->assertJsCondition("document.querySelectorAll('.pb-carousel__slide')[$index].classList.contains('pb-is-active')");
      $this->assertSame($first, $height(), "The height is unchanged on page " . ($index + 1) . '.');
    }
  }

  /**
   * Paging stays aligned when the theme puts a margin on the slides.
   *
   * The same carousel code in vvjb was found drifting on a live site whose
   * theme gave every item a right margin: positions were added up from slide
   * widths and the gap setting only, so each page landed one margin per slide
   * further off. Every page must start where page one starts, whatever
   * spacing the theme adds.
   */
  public function testPagingStaysAlignedWithThemeSlideMargins(): void {
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('access content')
      ->save();

    $slides = [];
    foreach (range(1, 6) as $i) {
      $slide = Paragraph::create([
        'type' => 'accordion_section_bundle',
        'pb_content_accordion_title' => 'Slide ' . $i,
      ]);
      $slide->save();
      $slides[] = [
        'target_id' => $slide->id(),
        'target_revision_id' => $slide->getRevisionId(),
      ];
    }

    $carousel = Paragraph::create([
      'type' => 'carousel_bundle',
      'pb_content_carousel_body' => $slides,
      // Two slides per page at every width, arrows, autoplay off.
      'pb_display_navigation' => 'arrows',
      'pb_display_carousel_play_stop' => 0,
      'pb_display_big_screen' => 2,
      'pb_display_small_screen' => 2,
    ]);
    $carousel->save();

    [$nodeType, $fieldName] = $this->discoverParagraphHost();
    $node = $this->drupalCreateNode([
      'type' => $nodeType,
      $fieldName => [
        [
          'target_id' => $carousel->id(),
          'target_revision_id' => $carousel->getRevisionId(),
        ],
      ],
    ]);

    $this->drupalGet($node->toUrl());
    $this->assertSession()->waitForElement('css', '.pb-carousel__slide');

    // Fixed slide width plus a right margin, as a theme might add, then a
    // resize so the carousel lays itself out again.
    $this->getSession()->executeScript(
      "const style = document.createElement('style');"
      . " style.textContent = '.pb-carousel__slide { flex: 0 0 100px !important;"
      . " width: 100px !important; max-width: 100px !important;"
      . " margin-inline-end: 30px !important; }';"
      . " document.head.appendChild(style);"
      . " window.dispatchEvent(new Event('resize'));"
      . " window.pbMarginProbe = null;"
      . " setTimeout(() => {"
      . "   const slides = document.querySelectorAll('.pb-carousel__slide');"
      . "   const frame = slides[0].parentElement.parentElement.getBoundingClientRect();"
      . "   window.pbMarginProbe = Math.round(slides[0].getBoundingClientRect().left - frame.left);"
      . " }, 1000);"
    );
    $this->assertJsCondition('window.pbMarginProbe !== null', 5000);

    $this->getSession()->getPage()->find('css', '.pb-nav__next')->click();

    // The first slide of page two must sit where slide one sat on page one.
    // Before the fix it sat two margins (60px) further right.
    $this->assertJsCondition(
      "(function () {"
      . " const slides = [...document.querySelectorAll('.pb-carousel__slide')];"
      . " const frame = slides[0].parentElement.parentElement.getBoundingClientRect();"
      . " const first = slides.findIndex((s) => s.classList.contains('pb-is-active'));"
      . " if (first < 1) { return false; }"
      . " const left = Math.round(slides[first].getBoundingClientRect().left - frame.left);"
      . " return Math.abs(left - window.pbMarginProbe) <= 1;"
      . "})()",
      5000,
    );
  }

  /**
   * Finds a node type + field that references paragraphs.
   *
   * @return array{0: string|null, 1: string|null}
   *   The node bundle and paragraphs reference field name, or [NULL, NULL].
   */
  private function discoverParagraphHost(): array {
    $bundleInfo = $this->container->get('entity_type.bundle.info')
      ->getBundleInfo('node');
    $fieldManager = $this->container->get('entity_field.manager');
    foreach (array_keys($bundleInfo) as $nodeBundle) {
      foreach ($fieldManager->getFieldDefinitions('node', $nodeBundle) as $name => $def) {
        if ($def->getType() === 'entity_reference_revisions'
          && $def->getSetting('target_type') === 'paragraph') {
          return [$nodeBundle, $name];
        }
      }
    }
    return [NULL, NULL];
  }

}
