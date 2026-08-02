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
 * Drives the image-grid bundle's lightbox in a real browser.
 *
 * Requires a WebDriver (chromedriver/selenium). Builds a three-item image grid
 * on a node, then asserts: the wrapper gets the JS item-count class
 * (pb-image-grid--count-3); the lightbox starts hidden; clicking an item's
 * expand button opens the lightbox (aria-hidden/aria-modal, expand
 * aria-expanded); and the close button dismisses it. The grid sections carry no
 * uploaded image — the expand trigger and lightbox render regardless.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbImageGridJavascriptTest extends WebDriverTestBase {

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
    'paragraph_bundle_image_grid',
  ];

  /**
   * The expand button opens the lightbox; the close button dismisses it.
   */
  public function testImageGridLightboxOpensAndCloses(): void {
    // A minimal install grants anonymous no permissions; allow viewing content
    // so the node renders without a login step.
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('access content')
      ->save();

    $sections = [];
    foreach (range(1, 3) as $delta) {
      $section = Paragraph::create([
        'type' => 'image_grid_section_bundle',
        'pb_content_title' => "Item $delta",
      ]);
      $section->save();
      $sections[] = [
        'target_id' => $section->id(),
        'target_revision_id' => $section->getRevisionId(),
      ];
    }

    $grid = Paragraph::create([
      'type' => 'image_grid_bundle',
      'pb_content_image_grid_image' => $sections,
    ]);
    $grid->save();

    [$nodeType, $fieldName] = $this->discoverParagraphHost();
    $node = $this->drupalCreateNode([
      'type' => $nodeType,
      $fieldName => [
        [
          'target_id' => $grid->id(),
          'target_revision_id' => $grid->getRevisionId(),
        ],
      ],
    ]);

    $this->drupalGet($node->toUrl());
    $assert = $this->assertSession();

    // The grid renders; JS adds the item-count class; the lightbox is hidden.
    $assert->waitForElement('css', '.pb-grid__expand');
    $this->assertJsCondition(
      "document.querySelector('.pb-image-grid__wrapper').classList.contains('pb-image-grid--count-3')",
    );
    $this->assertJsCondition(
      "document.querySelector('.pb-grid__lightbox').getAttribute('aria-hidden') === 'true'",
    );

    // Clicking an item's expand button opens the lightbox. The button is an
    // absolutely-positioned overlay with zero box size when the demo section
    // carries no image, so dispatch the click via JS to exercise the handler.
    $this->getSession()->executeScript(
      "document.querySelector('.pb-grid__expand').click();",
    );
    $this->assertJsCondition(
      "document.querySelector('.pb-grid__lightbox').getAttribute('aria-hidden') === 'false'",
    );
    $this->assertJsCondition(
      "document.querySelector('.pb-grid__lightbox').getAttribute('aria-modal') === 'true'",
    );
    $this->assertJsCondition(
      "document.querySelector('.pb-grid__expand').getAttribute('aria-expanded') === 'true'",
    );

    // The close button dismisses the lightbox and resets the expand trigger.
    $this->getSession()->executeScript(
      "document.querySelector('.pb-grid__close').click();",
    );
    $this->assertJsCondition(
      "document.querySelector('.pb-grid__lightbox').getAttribute('aria-hidden') === 'true'",
    );
    $this->assertJsCondition(
      "document.querySelector('.pb-grid__expand').getAttribute('aria-expanded') === 'false'",
    );

    // No serious/critical accessibility violations in the rendered widget.
    $this->assertNoAxeViolations('.pb-image-grid');
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
