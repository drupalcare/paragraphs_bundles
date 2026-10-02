<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The Paragraphs List layout holds under Claro: measured geometry, not markup.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class ParagraphsListLayoutJavascriptTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'claro';

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = ['paragraphs_bundles'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    ParagraphsType::create(['id' => 'test_a', 'label' => 'Type A'])->save();
    Paragraph::create(['type' => 'test_a'])->save();
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
  }

  /**
   * Returns the bounding rectangle of the first match, as an array.
   *
   * @return array<string, float|int>
   *   Keys top, bottom, left, right.
   */
  private function rect(string $selector): array {
    $rect = $this->getSession()->evaluateScript(
      '(function () { var r = document.querySelector(' . json_encode($selector) . ').getBoundingClientRect();' .
      ' return {top: r.top, bottom: r.bottom, left: r.left, right: r.right}; })()'
    );
    $this->assertIsArray($rect, "$selector exists.");
    return $rect;
  }

  /**
   * Opens the list at a given window width and waits for the Columns control.
   */
  private function openAt(int $width): void {
    $this->getSession()->resizeWindow($width, 900);
    $this->drupalGet('admin/content/paragraphs/paragraphs-list');
    $this->assertJsCondition('document.querySelector(".pb-paragraphs-list__columns") !== null');
    // resize_window can report success without resizing; read it back.
    $inner = (int) $this->getSession()->evaluateScript('window.innerWidth');
    $this->assertEqualsWithDelta($width, $inner, 40, 'The window really is about that wide.');
  }

  /**
   * Wide: row 1 is summary + checkboxes; row 2 is the filters, in order.
   */
  public function testWideLayout(): void {
    $this->openAt(1400);

    // Row 1: the summary on the left, the Columns checkboxes on the right.
    $summary = $this->rect('.paragraph-summary');
    $columns = $this->rect('.pb-paragraphs-list__columns');
    $table = $this->rect('.pb-paragraphs-list__scroll');
    $this->assertLessThan($columns['bottom'], $summary['top'], 'Summary and checkboxes overlap vertically: one row.');
    $this->assertLessThan($summary['bottom'], $columns['top'], 'Summary and checkboxes overlap vertically: one row.');
    $this->assertLessThan($columns['left'], $summary['right'], 'The summary is left of the checkboxes.');
    $this->assertEqualsWithDelta($table['right'], $columns['right'], 4, 'The checkboxes end at the right edge of the table.');
    $this->assertLessThanOrEqual($table['top'], $columns['bottom'], 'The checkboxes are above the table.');
    $labels = $this->getSession()->evaluateScript(
      'Array.from(document.querySelectorAll(".pb-paragraphs-list__columns > label")).map(function (l) { return l.textContent.trim(); })'
    );
    $this->assertSame(['Type', 'NID', 'Content title', 'Label', 'Parent'], $labels, 'Always visible, in this order.');

    // Row 2: type, match, title, label, then the buttons, left to right, with
    // their bottoms on one line.
    $items = [
      $this->rect('#edit-type'),
      $this->rect('#edit-title-mode'),
      $this->rect('#edit-title'),
      $this->rect('#edit-label'),
      $this->rect('#edit-filter--2'),
      $this->rect('#edit-reset'),
    ];
    $filters = $this->rect('.pb-paragraphs-list__filters');
    for ($i = 1; $i < count($items); $i++) {
      $this->assertGreaterThanOrEqual($items[$i - 1]['right'] - 1, $items[$i]['left'], "Item $i is right of item " . ($i - 1) . '.');
      $this->assertEqualsWithDelta($items[0]['bottom'], $items[$i]['bottom'], 3, "Item $i is bottom-aligned with the first.");
    }
    foreach ($items as $item) {
      $this->assertGreaterThanOrEqual($filters['left'] - 1, $item['left']);
      $this->assertLessThanOrEqual($filters['right'] + 1, $item['right']);
    }
    $this->assertLessThan($filters['top'] + 1, $summary['bottom'], 'Row 1 is above row 2.');
    $this->assertTrue(
      (bool) $this->getSession()->evaluateScript('document.getElementById("edit-filter--2").parentElement.classList.contains("pb-paragraphs-list__filter-actions")'),
      'Claro did not move the buttons out of the filter container.'
    );
  }

  /**
   * Narrow: summary above checkboxes, filters wrap in order, no page scroll.
   */
  public function testNarrowLayout(): void {
    $this->openAt(480);

    $summary = $this->rect('.paragraph-summary');
    $columns = $this->rect('.pb-paragraphs-list__columns');
    $this->assertLessThanOrEqual($columns['top'] + 1, $summary['bottom'], 'The summary is above the checkboxes.');
    $this->assertEqualsWithDelta($summary['left'], $columns['left'], 4, 'Both are left-aligned.');

    $ids = ['#edit-type', '#edit-title-mode', '#edit-title', '#edit-label', '#edit-filter--2', '#edit-reset'];
    $previous = NULL;
    foreach ($ids as $id) {
      $rect = $this->rect($id);
      if ($previous !== NULL) {
        $after_on_same_row = $rect['left'] >= $previous['right'] - 1 && abs($rect['bottom'] - $previous['bottom']) <= 3;
        $on_a_later_row = $rect['top'] >= $previous['bottom'] - 1;
        $this->assertTrue($after_on_same_row || $on_a_later_row, "$id keeps its place in the order when the row wraps.");
      }
      $previous = $rect;
    }

    $scroll_width = (int) $this->getSession()->evaluateScript('document.documentElement.scrollWidth');
    $inner = (int) $this->getSession()->evaluateScript('window.innerWidth');
    $this->assertLessThanOrEqual($inner + 1, $scroll_width, 'The page itself does not scroll sideways.');

    $region = $this->rect('.pb-paragraphs-list__scroll');
    $this->assertLessThanOrEqual($inner + 1, $region['right'], 'The table stays inside its own scroll region.');
    $this->assertSame('auto', $this->getSession()->evaluateScript('getComputedStyle(document.querySelector(".pb-paragraphs-list__scroll")).overflowX'));
  }

}
