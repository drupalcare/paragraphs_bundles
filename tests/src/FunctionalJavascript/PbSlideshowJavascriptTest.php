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
 * Drives the slideshow bundle's next/prev navigation in a real browser.
 *
 * Requires a WebDriver (chromedriver/selenium). Builds a three-slide slideshow
 * on a node with autoplay disabled, then asserts the navigation contract:
 * slide one is active on load; clicking Next activates slide two; clicking
 * Previous returns to slide one.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbSlideshowJavascriptTest extends WebDriverTestBase {

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
    'paragraph_bundle_slideshow',
  ];

  /**
   * Next advances the active slide by one; Previous steps it back.
   */
  public function testSlideshowNextPrevAdvancesSlide(): void {
    // A minimal install grants anonymous no permissions; allow viewing content
    // so the node renders without a login step.
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('access content')
      ->save();

    $sections = [];
    foreach (['Slide one', 'Slide two', 'Slide three'] as $label) {
      $section = Paragraph::create([
        'type' => 'slideshow_section_bundle',
        'pb_content_slideshow_title' => $label,
      ]);
      $section->save();
      $sections[] = [
        'target_id' => $section->id(),
        'target_revision_id' => $section->getRevisionId(),
      ];
    }

    $slideshow = Paragraph::create([
      'type' => 'slideshow_bundle',
      'pb_content_slideshow_section' => $sections,
      // Deterministic: arrows on, autoplay off (no auto-advance mid-test).
      'pb_display_slide_prev_next' => 1,
      'pb_display_slide_play_stop' => 0,
    ]);
    $slideshow->save();

    [$nodeType, $fieldName] = $this->discoverParagraphHost();
    $node = $this->drupalCreateNode([
      'type' => $nodeType,
      $fieldName => [
        [
          'target_id' => $slideshow->id(),
          'target_revision_id' => $slideshow->getRevisionId(),
        ],
      ],
    ]);

    $this->drupalGet($node->toUrl());
    $assert = $this->assertSession();

    // Slides render; the first is active on load, the second is not.
    $assert->waitForElement('css', '.pb-slideshow__slide');
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-slideshow__slide')[0].classList.contains('pb-is-active')",
    );
    $this->assertJsCondition(
      "!document.querySelectorAll('.pb-slideshow__slide')[1].classList.contains('pb-is-active')",
    );

    // Clicking Next advances the active slide to the second.
    $this->getSession()->getPage()->find('css', '.pb-nav__next')->click();
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-slideshow__slide')[1].classList.contains('pb-is-active')",
    );
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-slideshow__slide')[1].getAttribute('aria-hidden') === 'false'",
    );
    $this->assertJsCondition(
      "!document.querySelectorAll('.pb-slideshow__slide')[0].classList.contains('pb-is-active')",
    );

    // Clicking Previous returns the active slide to the first.
    $this->getSession()->getPage()->find('css', '.pb-nav__prev')->click();
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-slideshow__slide')[0].classList.contains('pb-is-active')",
    );
    $this->assertJsCondition(
      "!document.querySelectorAll('.pb-slideshow__slide')[1].classList.contains('pb-is-active')",
    );

    // No serious/critical accessibility violations in the rendered widget.
    $this->assertNoAxeViolations('.pb-slideshow');
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
