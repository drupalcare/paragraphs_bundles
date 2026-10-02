<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The Paragraphs List "Columns" control hides and shows columns, and remembers.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class ParagraphsListColumnsJavascriptTest extends WebDriverTestBase {

  private const LIST_PATH = 'admin/content/paragraphs/paragraphs-list';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

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
   * Whether a body cell of the column is currently hidden.
   */
  private function isHidden(string $column): bool {
    return (bool) $this->getSession()->evaluateScript(
      'document.querySelector("#paragraphs-table tbody td[data-pb-col=\"' . $column . '\"]").hidden'
    );
  }

  /**
   * Unticking a column hides it, header and cells; ticking shows it again.
   */
  public function testHideShowAndRemember(): void {
    $this->drupalGet(self::LIST_PATH);
    $page = $this->getSession()->getPage();
    $this->assertJsCondition('document.querySelector(".pb-paragraphs-list__columns") !== null');
    $this->assertFalse($this->isHidden('label'), 'Every column starts shown.');

    // The checkboxes are always visible: nothing to open.
    $this->assertNull($page->find('css', 'details.pb-paragraphs-list__columns'));
    $page->uncheckField('Label');
    $this->assertTrue($this->isHidden('label'));
    $this->assertTrue((bool) $this->getSession()->evaluateScript('document.querySelector("#paragraphs-table thead th[data-pb-col=\"label\"]").hidden'));
    $this->assertFalse($this->isHidden('type'), 'Other columns are untouched.');

    // The PID and the select checkbox are not offered, so they cannot vanish.
    $this->assertNull($page->findField('PID'));
    $this->assertNull($page->findField('Edit'));
    $this->assertSame(0, (int) $this->getSession()->evaluateScript('document.querySelectorAll(".pb-paragraphs-list__columns input").length - document.querySelectorAll("#paragraphs-table thead th[data-pb-col]").length'));

    // Remembered after a reload.
    $this->drupalGet(self::LIST_PATH);
    $this->assertJsCondition('document.querySelector(".pb-paragraphs-list__columns") !== null');
    $this->assertTrue($this->isHidden('label'), 'The choice survived the reload.');
    $page = $this->getSession()->getPage();
    $this->assertFalse($page->findField('Label')->isChecked());

    $page->checkField('Label');
    $this->assertFalse($this->isHidden('label'));
    $this->drupalGet(self::LIST_PATH);
    $this->assertJsCondition('document.querySelector(".pb-paragraphs-list__columns") !== null');
    $this->assertFalse($this->isHidden('label'), 'Showing it again was remembered too.');
  }

  /**
   * With storage blocked every column shows and nothing throws.
   */
  public function testStorageBlockedShowsEveryColumn(): void {
    $this->drupalGet(self::LIST_PATH);
    $this->assertJsCondition('document.querySelector(".pb-paragraphs-list__columns") !== null');
    // Replace localStorage with one that throws, as a browser with storage
    // disabled does, then run the behavior again on a fresh table.
    $this->getSession()->executeScript(
      'Object.defineProperty(window, "localStorage", {get: function () { throw new Error("blocked"); }});'
    );
    $this->drupalGet(self::LIST_PATH);
    $this->getSession()->executeScript(
      'Object.defineProperty(window, "localStorage", {get: function () { throw new Error("blocked"); }});' .
      'document.querySelector(".pb-paragraphs-list__columns").remove();' .
      'document.querySelector("#paragraphs-table").removeAttribute("data-once");' .
      'Drupal.attachBehaviors(document, drupalSettings);'
    );
    $this->assertJsCondition('document.querySelector(".pb-paragraphs-list__columns") !== null');
    foreach (['type', 'nid', 'node_title', 'label', 'parent'] as $column) {
      $this->assertFalse($this->isHidden($column), "$column is shown.");
    }
    // Changing a box still works for the page, and still throws nothing.
    $page = $this->getSession()->getPage();
    $page->uncheckField('Parent');
    $this->assertTrue($this->isHidden('parent'));
  }

}
