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
