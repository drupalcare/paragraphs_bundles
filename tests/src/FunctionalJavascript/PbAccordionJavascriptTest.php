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
 * Drives the accordion bundle's toggle behaviour in a real browser.
 *
 * Requires a WebDriver (chromedriver/selenium). Builds a two-section accordion
 * on a node, then asserts the JS toggle contract: clicking a section button
 * flips aria-expanded, reveals the pane (aria-hidden), and marks the item
 * active; clicking again collapses it.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbAccordionJavascriptTest extends WebDriverTestBase {

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
    'paragraph_bundle_accordion',
  ];

  /**
   * Clicking a section button expands and collapses it, updating ARIA.
   */
  public function testAccordionTogglesSection(): void {
    // A minimal install grants anonymous no permissions; allow viewing content
    // so the node renders without a login step.
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('access content')
      ->save();

    $sectionOne = Paragraph::create([
      'type' => 'accordion_section_bundle',
      'pb_content_accordion_title' => 'First section',
    ]);
    $sectionOne->save();
    $sectionTwo = Paragraph::create([
      'type' => 'accordion_section_bundle',
      'pb_content_accordion_title' => 'Second section',
    ]);
    $sectionTwo->save();

    $accordion = Paragraph::create([
      'type' => 'accordion_bundle',
      // "Expand First Accordion Item" defaults to on; force it off so the
      // section starts collapsed and the toggle contract is deterministic.
      'pb_display_accor_open_first' => 0,
      'pb_content_accordion_section' => [
        [
          'target_id' => $sectionOne->id(),
          'target_revision_id' => $sectionOne->getRevisionId(),
        ],
        [
          'target_id' => $sectionTwo->id(),
          'target_revision_id' => $sectionTwo->getRevisionId(),
        ],
      ],
    ]);
    $accordion->save();

    [$nodeType, $fieldName] = $this->discoverParagraphHost();
    $node = $this->drupalCreateNode([
      'type' => $nodeType,
      $fieldName => [
        [
          'target_id' => $accordion->id(),
          'target_revision_id' => $accordion->getRevisionId(),
        ],
      ],
    ]);

    $this->drupalGet($node->toUrl());
    $assert = $this->assertSession();

    // The first section button renders and starts collapsed.
    $button = $assert->waitForElement('css', '.pb-accordion__button');
    $this->assertNotNull($button, 'The accordion section button rendered.');
    $this->assertSame('false', $button->getAttribute('aria-expanded'));

    // Clicking expands: aria-expanded flips, the item is active, pane revealed.
    $button->click();
    $this->assertJsCondition(
      "document.querySelector('.pb-accordion__button').getAttribute('aria-expanded') === 'true'",
    );
    $this->assertJsCondition(
      "document.querySelector('.pb-accordion__item').classList.contains('pb-is-active')",
    );
    $this->assertJsCondition(
      "document.querySelector('.pb-accordion__pane').getAttribute('aria-hidden') === 'false'",
    );

    // Clicking again collapses it.
    $button->click();
    $this->assertJsCondition(
      "document.querySelector('.pb-accordion__button').getAttribute('aria-expanded') === 'false'",
    );
    $this->assertJsCondition(
      "document.querySelector('.pb-accordion__pane').getAttribute('aria-hidden') === 'true'",
    );

    // The sections list, items, buttons and expand-all row are grid/flex
    // containers: the base clearfix pseudo-elements must not become empty
    // items there.
    $this->assertJsCondition(
      "[...document.querySelectorAll('.pb-accordion__sections, .pb-accordion__item, .pb-accordion__button, .pb-grid__expand-button')]"
      . ".every((el) => ['::before', '::after']"
      . ".every((p) => getComputedStyle(el, p).content === 'none'))"
      . " && document.querySelectorAll('.pb-accordion__sections, .pb-accordion__item, .pb-accordion__button, .pb-grid__expand-button').length > 0",
      5000,
      'The flex/grid rows carry no clearfix pseudo-elements.',
    );

    // No serious/critical accessibility violations in the rendered widget.
    $this->assertNoAxeViolations('.pb-accordion');
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
