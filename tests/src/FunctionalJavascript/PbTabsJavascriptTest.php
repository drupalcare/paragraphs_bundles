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
 * Drives the tabs bundle's tab-switching behaviour in a real browser.
 *
 * Requires a WebDriver (chromedriver/selenium). Builds a three-tab set on a
 * node, then asserts the JS tablist contract: the first tab is active on load;
 * clicking another tab flips aria-selected, marks it active, reveals its panel
 * (aria-hidden) and hides the previously active one.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbTabsJavascriptTest extends WebDriverTestBase {

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
    'paragraph_bundle_tabs',
  ];

  /**
   * Clicking a tab activates it and its panel, deactivating the previous one.
   */
  public function testTabsSwitchActivePanel(): void {
    // A minimal install grants anonymous no permissions; allow viewing content
    // so the node renders without a login step.
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('access content')
      ->save();

    $sections = [];
    foreach (['First tab', 'Second tab', 'Third tab'] as $name) {
      $section = Paragraph::create([
        'type' => 'tab_section_bundle',
        'pb_content_tab_name' => $name,
      ]);
      $section->save();
      $sections[] = [
        'target_id' => $section->id(),
        'target_revision_id' => $section->getRevisionId(),
      ];
    }

    $tabs = Paragraph::create([
      'type' => 'tabs_bundle',
      'pb_content_tab_section' => $sections,
    ]);
    $tabs->save();

    [$nodeType, $fieldName] = $this->discoverParagraphHost();
    $node = $this->drupalCreateNode([
      'type' => $nodeType,
      $fieldName => [
        [
          'target_id' => $tabs->id(),
          'target_revision_id' => $tabs->getRevisionId(),
        ],
      ],
    ]);

    $this->drupalGet($node->toUrl());
    $assert = $this->assertSession();

    // Tabs render; the first tab is active on load, the second is not.
    $assert->waitForElement('css', '.pb-tabs__tab');
    $page = $this->getSession()->getPage();
    $tabButtons = $page->findAll('css', '.pb-tabs__tab');
    $this->assertGreaterThanOrEqual(2, count($tabButtons));
    $this->assertSame('true', $tabButtons[0]->getAttribute('aria-selected'));
    $this->assertSame('false', $tabButtons[1]->getAttribute('aria-selected'));

    // Clicking the second tab activates it and reveals its panel.
    $tabButtons[1]->click();
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-tabs__tab')[1].getAttribute('aria-selected') === 'true'",
    );
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-tabs__tab')[1].classList.contains('pb-is-active')",
    );
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-tabs__panel')[1].getAttribute('aria-hidden') === 'false'",
    );

    // The previously active first tab and its panel are now deactivated.
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-tabs__tab')[0].getAttribute('aria-selected') === 'false'",
    );
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-tabs__panel')[0].getAttribute('aria-hidden') === 'true'",
    );

    // The tab set and tab list are flex containers: the base clearfix
    // pseudo-elements must not become empty flex items there.
    $this->assertJsCondition(
      "[...document.querySelectorAll('.pb-tabs__tabset, .pb-tabs__tablist')]"
      . ".every((el) => ['::before', '::after']"
      . ".every((p) => getComputedStyle(el, p).content === 'none'))"
      . " && document.querySelectorAll('.pb-tabs__tabset, .pb-tabs__tablist').length > 0",
      5000,
      'The flex/grid rows carry no clearfix pseudo-elements.',
    );

    // No serious/critical accessibility violations in the rendered widget.
    $this->assertNoAxeViolations('.pb-tabs');
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
