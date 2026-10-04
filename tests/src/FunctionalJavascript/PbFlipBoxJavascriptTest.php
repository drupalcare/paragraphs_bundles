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
   * The flip control turns the box, and the face turned away goes inert.
   *
   * This used to focus the scene and expect a flip, because the scene carried
   * `tabindex="0"`. That was finding F-1 in the accessibility audit: a `<div>`
   * with no role, no accessible name and no key handler, which a keyboard user
   * could land on while nothing responded. The tabindex is gone and a real
   * `<button>` does the flipping, so the old assertion was asserting the
   * defect.
   */
  public function testFlipBoxFlipsFromItsControl(): void {
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

    // The scene must not be a focus target any more (F-1).
    $this->assertJsCondition(
      "document.querySelector('.pb-3d-flip-box__scene').getAttribute('tabindex') === null",
    );

    // The control ships `hidden` and the behaviour reveals it, so a page
    // without the script never shows a control that cannot work. Waiting on
    // that is also how we know the behaviour attached.
    $assert->waitForElement('css', '.pb-3d-flip-box__toggle:not([hidden])');
    $this->assertJsCondition(
      "document.querySelector('.pb-3d-flip-box__toggle').getAttribute('aria-expanded') === 'false'",
    );

    // Before the flip, the back face is the one turned away (F-2, F-3).
    $this->assertJsCondition(
      "document.querySelector('.pb-3d-flip-box__back').hasAttribute('inert') === true"
      . " && document.querySelector('.pb-3d-flip-box__front').hasAttribute('inert') === false",
    );

    // Activate the control without the pointer. A real WebDriver click parks
    // the cursor inside the scene, and hovering the scene shows the back face
    // in CSS on its own — so a mouse-driven toggle-off cannot be told apart
    // from "still hovered". Driving the button directly keeps both directions
    // deterministic; the real click is exercised further down.
    $press = "document.querySelector('.pb-3d-flip-box__toggle').click();";

    $this->getSession()->executeScript($press);
    $this->assertJsCondition(
      "Math.round(new DOMMatrixReadOnly(getComputedStyle(document.querySelector('.pb-3d-flip-box__inner')).transform).m11) === -1",
    );
    $this->assertJsCondition(
      "document.querySelector('.pb-3d-flip-box__toggle').getAttribute('aria-expanded') === 'true'",
    );

    // …and the isolation follows the presented face rather than staying put:
    // the front is now the one turned away.
    $this->assertJsCondition(
      "document.querySelector('.pb-3d-flip-box__front').hasAttribute('inert') === true"
      . " && document.querySelector('.pb-3d-flip-box__back').hasAttribute('inert') === false",
    );

    // Pressing it again turns the box back and restores the isolation.
    $this->getSession()->executeScript($press);
    $this->assertJsCondition(
      "Math.round(new DOMMatrixReadOnly(getComputedStyle(document.querySelector('.pb-3d-flip-box__inner')).transform).m11) === 1",
    );
    $this->assertJsCondition(
      "document.querySelector('.pb-3d-flip-box__back').hasAttribute('inert') === true"
      . " && document.querySelector('.pb-3d-flip-box__front').hasAttribute('inert') === false",
    );

    // A genuine click reaches the handler too. Only `aria-expanded` is
    // asserted here: the cursor now rests on the control, so the scene is
    // hovered and the back face is presented for that reason as well.
    //
    // Enter/Space are deliberately not asserted: the control is a native
    // <button>, so the browser guarantees them, and Mink's synthetic key
    // events do not reliably activate one. That the element IS a button is
    // the thing worth holding, and PbFlipBoxAriaTest holds it on the markup.
    $toggle = $this->getSession()->getPage()->find('css', '.pb-3d-flip-box__toggle');
    $this->assertNotNull($toggle, 'The flip control is on the page.');

    // The pointer click needs a scene that has a height. The faces are
    // absolutely positioned, so the scene's only height is the generated
    // per-paragraph stylesheet (`pb_display_3d_height`, 350px by default), and
    // the scene clips with `overflow: hidden`. With no height the control is
    // clipped away entirely, and WebDriver reports exactly what drupal.org's
    // phpunit job reported: `ElementClickIntercepted ... not clickable at
    // point (990, 8). Other element would receive the click: <html>` - that is
    // the top edge of a zero-height scene 8px (body margin) from the viewport
    // top, 990px across a 1024px window. MEASURED, not guessed: Chrome
    // headless-shell 127 + chromedriver 127 at 1024x768 reproduced that exact
    // message by emptying the height, and passes with the default height.
    // So wait for the geometry the click depends on, and assert the control is
    // what is under its own centre, instead of letting WebDriver guess.
    $this->assertJsCondition(
      "document.querySelector('.pb-3d-flip-box__scene').getBoundingClientRect().height > 0",
      10000,
      'The flip box scene has no height: its generated stylesheet (pb_display_3d_height) did not apply, so the control is clipped and cannot be clicked.',
    );
    $this->getSession()->executeScript(
      "document.querySelector('.pb-3d-flip-box__toggle').scrollIntoView({block: 'center', behavior: 'instant'});",
    );
    // The two flips above leave the inner's transform transition and the
    // icon's rotation running; let them finish so the point WebDriver
    // computes is the point the control occupies when the click lands.
    $this->assertJsCondition(
      "document.getAnimations().every(function (a) { return a.playState !== 'running'; })",
    );
    $this->assertJsCondition(
      "(function () { var t = document.querySelector('.pb-3d-flip-box__toggle'), r = t.getBoundingClientRect(); return document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2) === t; })()",
      10000,
      'The flip control is not the element at its own centre point.',
    );
    $toggle->click();
    $this->assertJsCondition(
      "document.querySelector('.pb-3d-flip-box__toggle').getAttribute('aria-expanded') === 'true'",
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
