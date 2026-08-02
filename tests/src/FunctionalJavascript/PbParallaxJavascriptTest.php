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
 * Drives the parallax bundle's scroll-reveal behaviour in a real browser.
 *
 * Requires a WebDriver (chromedriver/selenium). The parallax background offset
 * is scroll-position math (not deterministically assertable); the browser-
 * observable state contract is the scroll-reveal class: a parallax section in
 * the viewport gets `pb-is-visible` on its inner. This builds a parallax
 * section on a node and asserts the JS behaviour attaches and reveals it.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbParallaxJavascriptTest extends WebDriverTestBase {

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
    'paragraph_bundle_parallax',
  ];

  /**
   * A parallax section in the viewport receives the reveal state class.
   */
  public function testParallaxSectionRevealsWhenInViewport(): void {
    // A minimal install grants anonymous no permissions; allow viewing content
    // so the node renders without a login step.
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('access content')
      ->save();

    $section = Paragraph::create([
      'type' => 'parallax_section_bundle',
      'pb_content_title' => 'Parallax section',
    ]);
    $section->save();

    $parallax = Paragraph::create([
      'type' => 'parallax_bundle',
      'pb_content_parallax_section' => [
        [
          'target_id' => $section->id(),
          'target_revision_id' => $section->getRevisionId(),
        ],
      ],
    ]);
    $parallax->save();

    [$nodeType, $fieldName] = $this->discoverParagraphHost();
    $node = $this->drupalCreateNode([
      'type' => $nodeType,
      $fieldName => [
        [
          'target_id' => $parallax->id(),
          'target_revision_id' => $parallax->getRevisionId(),
        ],
      ],
    ]);

    $this->drupalGet($node->toUrl());
    $assert = $this->assertSession();

    // The section renders and the behaviour reveals it while in the viewport.
    $assert->waitForElement('css', '.pb-parallax-section');
    $this->assertJsCondition(
      "document.querySelector('.pb-parallax-section__inner').classList.contains('pb-is-visible')",
    );

    // No serious/critical accessibility violations in the rendered widget.
    $this->assertNoAxeViolations('.pb-parallax-section');
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
