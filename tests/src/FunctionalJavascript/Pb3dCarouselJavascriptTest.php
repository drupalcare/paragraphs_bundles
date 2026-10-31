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
 * Drives the 3D ring carousel's next/prev rotation in a real browser.
 *
 * Requires a WebDriver (chromedriver/selenium). Builds a three-slide 3D
 * carousel on a node with autoplay disabled, then asserts the rotation
 * contract via the active-state class: slide one is active on load; clicking
 * Next rotates the ring so slide two becomes active; clicking Previous returns
 * to slide one. Slides are arbitrary lightweight child paragraphs (accordion
 * sections) — only the carousel ring and its JS matter here.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class Pb3dCarouselJavascriptTest extends WebDriverTestBase {

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
    'paragraph_bundle_3d_carousel',
    'paragraph_bundle_accordion',
  ];

  /**
   * Next rotates the ring to the following slide; Previous rotates it back.
   */
  public function testRingRotatesOnNextPrev(): void {
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
      'type' => '3d_carousel_bundle',
      'pb_content_3d_car_body' => $slides,
      // Disable autoplay so the active slide only changes on our clicks.
      'pb_display_3d_play_stop' => 0,
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
    $assert->waitForElement('css', '.pb-3d-carousel__slide');
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-3d-carousel__slide')[0].classList.contains('pb-is-active')",
    );
    $this->assertJsCondition(
      "!document.querySelectorAll('.pb-3d-carousel__slide')[1].classList.contains('pb-is-active')",
    );

    // Clicking Next rotates the ring: the second slide becomes active.
    $this->getSession()->getPage()->find('css', '.pb-nav__next')->click();
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-3d-carousel__slide')[1].classList.contains('pb-is-active')",
    );
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-3d-carousel__slide')[1].getAttribute('aria-hidden') === 'false'",
    );
    $this->assertJsCondition(
      "!document.querySelectorAll('.pb-3d-carousel__slide')[0].classList.contains('pb-is-active')",
    );

    // Clicking Previous rotates back: the first slide becomes active again.
    $this->getSession()->getPage()->find('css', '.pb-nav__prev')->click();
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-3d-carousel__slide')[0].classList.contains('pb-is-active')",
    );
    $this->assertJsCondition(
      "!document.querySelectorAll('.pb-3d-carousel__slide')[1].classList.contains('pb-is-active')",
    );

    // No serious/critical accessibility violations in the rendered widget.
    $this->assertNoAxeViolations('.pb-3d-carousel');
  }

  /**
   * The ring stops at its ends: the arrow there is disabled, never hidden.
   *
   * Owner decision 2026-10-30. The arrow keeps focus, a click on it does
   * nothing, and it carries `aria-disabled="true"` plus `pb-is-disabled`.
   */
  public function testRingDisablesTheArrowAtEachEnd(): void {
    $this->drupalGet($this->create3dCarouselNode([
      'pb_display_3d_play_stop' => 0,
      'pb_display_3d_car_style' => 'ring',
    ]));
    $this->assertSession()->waitForElement('css', '.pb-3d-carousel__slide');
    $active = "document.querySelectorAll('.pb-3d-carousel__slide')[%d].classList.contains('pb-is-active')";
    $page = $this->getSession()->getPage();

    $this->assertArrowDisabled('.pb-3d-carousel .pb-nav__prev', TRUE);
    $page->find('css', '.pb-3d-carousel .pb-nav__prev')->click();
    $this->assertJsCondition(sprintf($active, 0));

    $next = $page->find('css', '.pb-3d-carousel .pb-nav__next');
    $next->click();
    $next->click();
    $this->assertJsCondition(sprintf($active, 2));
    $this->assertArrowDisabled('.pb-3d-carousel .pb-nav__next', TRUE);
    $this->assertArrowDisabled('.pb-3d-carousel .pb-nav__prev', FALSE);
    $this->assertJsCondition("document.activeElement === document.querySelector('.pb-3d-carousel .pb-nav__next')");
    $next->click();
    $this->assertJsCondition(sprintf($active, 2));
  }

  /**
   * The coverflow wraps, so neither arrow is ever disabled.
   */
  public function testCoverflowNeverDisablesArrows(): void {
    $this->drupalGet($this->create3dCarouselNode([
      'pb_display_3d_play_stop' => 0,
      'pb_display_3d_car_style' => 'coverflow',
    ]));
    $this->assertSession()->waitForElement('css', '.pb-3d-carousel__slide');
    $this->assertArrowDisabled('.pb-3d-carousel .pb-nav__prev', FALSE);
    $this->getSession()->getPage()->find('css', '.pb-3d-carousel .pb-nav__prev')->click();
    $this->assertJsCondition("document.querySelectorAll('.pb-3d-carousel__slide')[2].classList.contains('pb-is-active')");
    $this->assertArrowDisabled('.pb-3d-carousel .pb-nav__prev', FALSE);
    $this->assertArrowDisabled('.pb-3d-carousel .pb-nav__next', FALSE);
  }

  /**
   * Builds a node holding one 3d_carousel_bundle of three slides, autoplay off.
   *
   * @param array<string, mixed> $display
   *   Extra display field values for the 3d_carousel_bundle paragraph.
   *
   * @return string
   *   The node URL.
   */
  private function create3dCarouselNode(array $display): string {
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
    $carousel = Paragraph::create(['type' => '3d_carousel_bundle', 'pb_content_3d_car_body' => $slides] + $display);
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
    return $node->toUrl()->toString();
  }

  /**
   * Asserts an arrow's disabled state, by class and by aria-disabled.
   *
   * @param string $selector
   *   The arrow selector.
   * @param bool $disabled
   *   The expected state.
   */
  private function assertArrowDisabled(string $selector, bool $disabled): void {
    $element = "document.querySelector('" . $selector . "')";
    $this->assertJsCondition($disabled
      ? $element . ".getAttribute('aria-disabled') === 'true' && " . $element . ".classList.contains('pb-is-disabled')"
      : "!" . $element . ".hasAttribute('aria-disabled') && !" . $element . ".classList.contains('pb-is-disabled')");
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
