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
 * Drives the modal bundle's open/close behaviour in a real browser.
 *
 * Requires a WebDriver (chromedriver/selenium). Builds a modal on a node, then
 * asserts the dialog contract: the trigger opens the dialog (pb-is-open,
 * aria-modal, aria-expanded), Escape closes it and restores the trigger, and
 * the close (x) button closes it too.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbModalJavascriptTest extends WebDriverTestBase {

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
    'paragraph_bundle_modal',
  ];

  /**
   * The trigger opens the dialog; Escape and the close button both dismiss it.
   */
  public function testModalOpensAndCloses(): void {
    // A minimal install grants anonymous no permissions; allow viewing content
    // so the node renders without a login step.
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('access content')
      ->save();

    $modal = Paragraph::create([
      'type' => 'modal_bundle',
      'pb_content_modal_button' => 'Open dialog',
      'pb_content_modal_title' => 'Greetings',
    ]);
    $modal->save();

    [$nodeType, $fieldName] = $this->discoverParagraphHost();
    $node = $this->drupalCreateNode([
      'type' => $nodeType,
      $fieldName => [
        [
          'target_id' => $modal->id(),
          'target_revision_id' => $modal->getRevisionId(),
        ],
      ],
    ]);

    $this->drupalGet($node->toUrl());
    $assert = $this->assertSession();

    // The trigger renders; the dialog starts hidden and collapsed.
    $openButton = $assert->waitForElement('css', '.pb-modal__button .pb-ui-btn');
    $this->assertNotNull($openButton, 'The modal open button rendered.');
    $this->assertSame('false', $openButton->getAttribute('aria-expanded'));
    $this->assertJsCondition(
      "document.querySelector('.pb-modal__dialog').getAttribute('aria-hidden') === 'true'",
    );

    // Clicking the trigger opens the dialog.
    $openButton->click();
    $this->assertJsCondition(
      "document.querySelector('.pb-modal__dialog').classList.contains('pb-is-open')",
    );
    $this->assertJsCondition(
      "document.querySelector('.pb-modal__dialog').getAttribute('aria-modal') === 'true'",
    );
    $this->assertJsCondition(
      "document.querySelector('.pb-modal__button .pb-ui-btn').getAttribute('aria-expanded') === 'true'",
    );

    // Pressing Escape closes it and restores the trigger state.
    $this->getSession()->executeScript(
      "document.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape', bubbles: true}));",
    );
    $this->assertJsCondition(
      "!document.querySelector('.pb-modal__dialog').classList.contains('pb-is-open')",
    );
    $this->assertJsCondition(
      "document.querySelector('.pb-modal__dialog').getAttribute('aria-hidden') === 'true'",
    );
    $this->assertJsCondition(
      "document.querySelector('.pb-modal__button .pb-ui-btn').getAttribute('aria-expanded') === 'false'",
    );

    // Re-opening then clicking the close (x) button also dismisses it.
    $openButton->click();
    $this->assertJsCondition(
      "document.querySelector('.pb-modal__dialog').classList.contains('pb-is-open')",
    );
    $closeButton = $this->getSession()->getPage()
      ->find('css', '.pb-modal__header .pb-ui-btn');
    $this->assertNotNull($closeButton, 'The modal close button rendered.');
    $closeButton->click();
    $this->assertJsCondition(
      "!document.querySelector('.pb-modal__dialog').classList.contains('pb-is-open')",
    );

    // No serious/critical accessibility violations in the rendered widget.
    $this->assertNoAxeViolations('.pb-modal');
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
