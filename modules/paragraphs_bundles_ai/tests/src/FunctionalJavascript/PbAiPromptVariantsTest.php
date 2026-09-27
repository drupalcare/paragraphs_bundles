<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The brief panel shows ONE example prompt at a time and never scrolls.
 *
 * THE ASSERTION THAT MATTERS IS THE SCROLL ONE, and it is deliberately first.
 * Measured 2026-09-20: Slideshow carries three example prompts, which made the
 * prompts 359px of a 532px body inside a card capped at 448px — 113px of the
 * brief sat out of sight. A test that merely counted the switches would have
 * passed on the broken version too, because the switches are the FIX, not the
 * defect. `scrollHeight <= clientHeight` is the condition that was false before
 * the change and true after it.
 *
 * WHY THIS NEEDS A REAL BROWSER. The panel is built entirely in JavaScript from
 * `drupalSettings.pbAi.briefs`; nothing about the one-at-a-time behaviour exists
 * in PHP, and geometry cannot be measured without layout. The project's own
 * pattern for client-side behaviour is WebDriverTestBase — see
 * tests/src/FunctionalJavascript/PbSlideshowJavascriptTest.php.
 *
 * NO AI PROVIDER IS CONTACTED. `PageSurface::decide()` gates the assistant on
 * `ai.settings` CONFIG only — a non-empty `default_providers.chat.provider_id`.
 * Nothing calls a model to render the panel, so this test needs no API key and
 * no network. An earlier reading of this class assumed otherwise and would have
 * skipped the coverage entirely.
 */
#[Group('paragraphs_bundles')]
#[Group('paragraphs_bundles_ai')]
#[RunTestsInSeparateProcesses]
final class PbAiPromptVariantsTest extends WebDriverTestBase {

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
    'paragraphs_bundles',
    'paragraph_bundle_content',
    'paragraph_bundle_slideshow',
    'paragraph_bundle_layout',
    'paragraphs_bundles_ai',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // THE ONLY THING THE ASSISTANT NEEDS FROM drupal/ai TO RENDER. decide()
    // reads this config and refuses when the chat provider is empty; it never
    // instantiates the provider to draw the bar. A made-up id is therefore
    // enough, and keeps the test offline.
    $this->config('ai.settings')
      ->set('default_providers.chat.provider_id', 'test_provider')
      ->set('default_providers.chat.model_id', 'test_model')
      ->save();
  }

  /**
   * Several prompts render one at a time; a single prompt draws no switches.
   */
  public function testOnePromptShowsAtOnceWithoutScrolling(): void {
    [$nodeType, $fieldName] = $this->discoverParagraphHost();

    if ($nodeType === NULL || $fieldName === NULL) {
      $this->fail('No node type with a paragraph reference field was found, so the assistant has nowhere to appear.');
    }

    // EDIT ACCESS, NOT VIEW ACCESS. decide() hides the assistant from anyone
    // who cannot edit the page, on the grounds that it only ever adds sections
    // to that page.
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'use paragraphs bundles ai assistant',
      'create ' . $nodeType . ' content',
      'edit any ' . $nodeType . ' content',
    ]));

    $node = $this->drupalCreateNode([
      'type' => $nodeType,
      $fieldName => [],
    ]);

    $this->drupalGet($node->toUrl());
    $assert = $this->assertSession();

    // The bar renders at the foot of the page.
    $assert->waitForElement('css', '.pb-ai-bar select[name="section"]');

    // Choose the one bundle that ships a NAMED SET of prompts. Driven through
    // a real change event, because that is the listener the panel binds.
    $this->getSession()->getPage()->selectFieldOption('section', 'slideshow_bundle');
    $assert->waitForElement('css', '.pb-ai-brief--open');

    // Three switches, one visible prompt.
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-ai-brief__variant').length === 3",
    );
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-ai-brief__prompt').length === 1",
    );

    // 🚨 THE REGRESSION GUARD. False before the one-at-a-time change (532 > 419),
    // true after it. Everything else in this test would have passed either way.
    $this->assertJsCondition(
      "(() => { const b = document.querySelector('.pb-ai-brief__body');"
      . " return b.scrollHeight <= b.clientHeight; })()",
    );

    // The first switch is the pressed one on open.
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-ai-brief__variant')[0].getAttribute('aria-pressed') === 'true'",
    );

    // Clicking the second switch swaps the prompt AND moves the pressed state.
    // Captured first so the comparison is against real text, not a guess.
    $before = $this->getSession()->evaluateScript(
      "document.querySelector('.pb-ai-brief__prompt-text').textContent.trim()",
    );

    $this->getSession()->getPage()
      ->findAll('css', '.pb-ai-brief__variant')[1]
      ->click();

    $this->assertJsCondition(
      "document.querySelectorAll('.pb-ai-brief__variant')[1].getAttribute('aria-pressed') === 'true'",
    );
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-ai-brief__variant')[0].getAttribute('aria-pressed') === 'false'",
    );

    $after = $this->getSession()->evaluateScript(
      "document.querySelector('.pb-ai-brief__prompt-text').textContent.trim()",
    );
    $this->assertNotSame($before, $after, 'Switching variant did not change the prompt text.');

    // Still one block, still no scrolling, after switching.
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-ai-brief__prompt').length === 1",
    );
    $this->assertJsCondition(
      "(() => { const b = document.querySelector('.pb-ai-brief__body');"
      . " return b.scrollHeight <= b.clientHeight; })()",
    );

    // THE COMMON CASE, which the change also had to leave alone. A bundle with
    // a single unnamed prompt draws NO switch row and no duplicate name label.
    $this->getSession()->getPage()->selectFieldOption('section', 'two_columns_bundle');
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-ai-brief__variant').length === 0",
    );
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-ai-brief__prompt').length === 1",
    );
    $this->assertJsCondition(
      "document.querySelectorAll('.pb-ai-brief__prompt-name').length === 0",
    );
  }

  /**
   * Hovering a section inside a section shows the OUTER section's id only.
   *
   * Maintainer, 2026-09-27: "when we hover we should get the first id of the
   * parent only". A slide, a column's Simple and a tab's body carry the same
   * class and the same kind of id as the page's own sections, so every one got
   * its own hover, and moving onto a child replaced the parent's id with the
   * child's. The note now shows exactly what "Or edit a section" offers.
   */
  public function testHoverShowsTheOuterSectionOnly(): void {
    [$nodeType, $fieldName] = $this->discoverParagraphHost();

    if ($nodeType === NULL || $fieldName === NULL) {
      $this->fail('No node type with a paragraph reference field was found.');
    }

    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'use paragraphs bundles ai assistant',
      'create ' . $nodeType . ' content',
      'edit any ' . $nodeType . ' content',
    ]));

    $inner = Paragraph::create(['type' => 'simple_bundle', 'pb_content_body' => 'Plant before the frost.']);
    $inner->save();
    $slide = Paragraph::create([
      'type' => 'slideshow_section_bundle',
      'pb_content_slideshow_title' => 'Tulips',
      'pb_content_slideshow_body' => [$inner],
    ]);
    $slide->save();
    $show = Paragraph::create(['type' => 'slideshow_bundle', 'pb_content_slideshow_section' => [$slide]]);
    $show->save();
    $node = $this->drupalCreateNode(['type' => $nodeType, $fieldName => [$show]]);

    $this->drupalGet($node->toUrl());
    $this->assertSession()->waitForElement('css', '.pb-ai-note');

    $shown = $this->getSession()->evaluateScript(<<<'JS'
      (() => {
        const note = document.querySelector('.pb-ai-note');
        const all = [...document.querySelectorAll('.paragraphs-bundles[id^="pb-"]')];
        const top = all.filter((el) => !el.parentElement.closest('.paragraphs-bundles[id^="pb-"]'));
        const parent = top[0];
        const child = parent.querySelector('.paragraphs-bundles[id^="pb-"]');
        parent.dispatchEvent(new MouseEvent('mouseenter'));
        const onParent = note.textContent;
        child.dispatchEvent(new MouseEvent('mouseenter'));
        return [parent.id, child ? child.id : '', onParent, note.textContent];
      })()
    JS);

    [$parentId, $childId, $onParent, $onChild] = $shown;
    self::assertNotSame('', $childId, 'The fixture has a section inside a section.');
    self::assertSame($parentId, $onParent);
    self::assertSame($parentId, $onChild, 'Hovering the child still shows the outer section.');
  }

  /**
   * Finds a node type + field that references paragraphs.
   *
   * Lifted from PbSlideshowJavascriptTest so both tests discover the host the
   * same way rather than hard-coding a bundle this module does not own.
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
