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
 * Drives the 3D flip-box bundle's focus flip in a real browser.
 *
 * The flip box has no JavaScript: the flip is a pure-CSS :focus-within (and
 * :hover) rotation of the inner element. This test drives the keyboard path —
 * focusing the scene rotates the inner 180 degrees (rotateY, m11 goes 1 to -1),
 * and blurring returns it — which is the browser-observable interaction
 * contract. Requires a WebDriver (chromedriver/selenium).
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbFlipBoxJavascriptTest extends WebDriverTestBase {

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
    'paragraph_bundle_3d_flip_box',
  ];

  /**
   * Focusing the scene flips the inner 180 degrees; blurring flips it back.
   */
  public function testFlipBoxFlipsOnFocus(): void {
    // A minimal install grants anonymous no permissions; allow viewing content
    // so the node renders without a login step.
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('access content')
      ->save();

    $flipBox = Paragraph::create([
      'type' => '3d_flip_box_bundle',
      // 'flip_y' engages the horizontal (rotateY) flip modifier class.
      'pb_display_flip_xy' => 'flip_y',
    ]);
    $flipBox->save();

    [$nodeType, $fieldName] = $this->discoverParagraphHost();
    $node = $this->drupalCreateNode([
      'type' => $nodeType,
      $fieldName => [
        [
          'target_id' => $flipBox->id(),
          'target_revision_id' => $flipBox->getRevisionId(),
        ],
      ],
    ]);

    $this->drupalGet($node->toUrl());
    $assert = $this->assertSession();

    // The inner starts flat (rotateY 0, m11 rounds to 1).
    $assert->waitForElement('css', '.pb-3d-flip-box__inner');
    $this->assertJsCondition(
      "Math.round(new DOMMatrixReadOnly(getComputedStyle(document.querySelector('.pb-3d-flip-box__inner')).transform).m11) === 1",
    );

    // Focusing the scene engages :focus-within: the inner rotates 180 (m11 -1).
    $this->getSession()->executeScript(
      "document.querySelector('.pb-3d-flip-box__scene').focus();",
    );
    $this->assertJsCondition(
      "Math.round(new DOMMatrixReadOnly(getComputedStyle(document.querySelector('.pb-3d-flip-box__inner')).transform).m11) === -1",
    );

    // Blurring the scene flips it back to flat.
    $this->getSession()->executeScript(
      "document.querySelector('.pb-3d-flip-box__scene').blur();",
    );
    $this->assertJsCondition(
      "Math.round(new DOMMatrixReadOnly(getComputedStyle(document.querySelector('.pb-3d-flip-box__inner')).transform).m11) === 1",
    );

    // No serious/critical accessibility violations in the rendered widget.
    $this->assertNoAxeViolations('.pb-3d-flip-box');
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
