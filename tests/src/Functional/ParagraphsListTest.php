<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Functional;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The Paragraphs List: list, filter, edit, save-back-to-list and bulk delete.
 *
 * The feature moved here from pb_import and must behave as it did there, so
 * the visible strings asserted below are the ones pb_import showed.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class ParagraphsListTest extends BrowserTestBase {

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
    foreach (['test_a' => 'Type A', 'test_b' => 'Type B'] as $id => $label) {
      ParagraphsType::create(['id' => $id, 'label' => $label])->save();
    }
  }

  /**
   * Creates paragraphs of one type and returns their ids.
   *
   * @return list<int|string>
   *   The new paragraph ids.
   */
  private function createParagraphs(string $type, int $count): array {
    $ids = [];
    for ($i = 0; $i < $count; $i++) {
      $paragraph = Paragraph::create(['type' => $type]);
      $paragraph->save();
      $ids[] = $paragraph->id();
    }
    return $ids;
  }

  /**
   * Logs in an administrator, who may use the list, edit and delete.
   *
   * The administrator role bypasses every check; the permission-by-permission
   * cases live in ParagraphsListAccessTest.
   */
  private function loginAdmin(): void {
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
  }

  /**
   * Counts the data rows in the list table.
   */
  private function rowCount(): int {
    return count($this->getSession()->getPage()->findAll('css', '#paragraphs-table tbody tr'));
  }

  /**
   * The page shows the summary, the seven columns and the filter controls.
   */
  public function testListPageStructure(): void {
    $this->createParagraphs('test_a', 2);
    $this->createParagraphs('test_b', 1);
    $this->loginAdmin();

    $this->drupalGet(self::LIST_PATH);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->elementTextContains('css', 'h1', 'Paragraphs List');
    $this->assertSession()->pageTextContains('Displaying 3 out of 3 paragraphs.');
    foreach (['PID', 'Type', 'NID', 'Content title', 'Label', 'Parent', 'Edit'] as $heading) {
      $this->assertSession()->elementExists('xpath', '//table[@id="paragraphs-table"]//th[starts-with(normalize-space(.), "' . $heading . '")]');
    }
    $this->assertSession()->fieldExists('Filter by type');
    $this->assertSession()->optionExists('Filter by type', '- All -');
    $this->assertSession()->optionExists('Filter by type', 'Type A');
    $this->assertSession()->buttonExists('Filter');
    $this->assertSession()->buttonExists('Reset');
    $this->assertSession()->buttonExists('Delete Selected');
    $this->assertSession()->pageTextContains('No Parent');
    $this->assertSame(3, $this->rowCount());
  }

  /**
   * An empty site says so.
   */
  public function testEmptyList(): void {
    $this->loginAdmin();
    $this->drupalGet(self::LIST_PATH);
    $this->assertSession()->pageTextContains('Displaying 0 out of 0 paragraphs.');
    $this->assertSession()->pageTextContains('No paragraphs found.');
  }

  /**
   * Filter narrows the list to one type; Reset clears it.
   */
  public function testFilterAndReset(): void {
    $this->createParagraphs('test_a', 2);
    $this->createParagraphs('test_b', 1);
    $this->loginAdmin();

    $this->drupalGet(self::LIST_PATH);
    $this->submitForm(['type' => 'test_a'], 'Filter');
    $this->assertSession()->addressEquals(self::LIST_PATH . '?type=test_a');
    $this->assertSession()->pageTextContains('Displaying 2 out of 2 paragraphs.');
    $this->assertSame(2, $this->rowCount());

    $this->submitForm([], 'Reset');
    $this->assertSession()->addressEquals(self::LIST_PATH);
    $this->assertSession()->pageTextContains('Displaying 3 out of 3 paragraphs.');
    $this->assertSame(3, $this->rowCount());
  }

  /**
   * The page size is 50, and Next / Previous lead to the rest.
   *
   * The list pages by keyset (the 50 after a key), not by page number, so the
   * old `?page=1` pager is gone; the visible page size is unchanged.
   */
  public function testPagination(): void {
    $this->createParagraphs('test_a', 51);
    $this->loginAdmin();

    $this->drupalGet(self::LIST_PATH);
    $this->assertSession()->pageTextContains('Displaying 50 out of 51 paragraphs.');
    $this->assertSame(50, $this->rowCount());
    $this->assertSession()->linkExists('Next');
    $this->assertSession()->linkNotExists('Previous');

    $this->clickLink('Next');
    $this->assertSame(1, $this->rowCount());
    $this->assertSession()->pageTextContains('Displaying 1 out of 51 paragraphs.');
    $this->assertSession()->linkExists('Previous');
    $this->assertSession()->linkNotExists('Next');
  }

  /**
   * Edit opens the form; saving returns to the list with a message.
   */
  public function testEditSavesAndReturnsToList(): void {
    $ids = $this->createParagraphs('test_a', 1);
    $this->loginAdmin();

    $this->drupalGet(self::LIST_PATH);
    $this->clickLink('Edit');
    $this->assertSession()->addressEquals('admin/content/paragraph/' . $ids[0] . '/edit');
    $this->assertSession()->elementTextContains('css', 'h1', 'Edit Paragraph');

    $this->submitForm([], 'Save');
    $this->assertSession()->addressEquals(self::LIST_PATH);
    $this->assertSession()->pageTextContains('has been saved.');
  }

  /**
   * Delete Selected removes only the ticked rows and reports the count.
   */
  public function testBulkDeleteRemovesOnlyTheSelectedRows(): void {
    $ids = $this->createParagraphs('test_a', 3);
    $this->loginAdmin();

    $this->drupalGet(self::LIST_PATH);
    $this->submitForm([
      'paragraphs_table[' . $ids[0] . ']' => TRUE,
      'paragraphs_table[' . $ids[1] . ']' => TRUE,
    ], 'Delete Selected');

    $this->assertSession()->addressEquals(self::LIST_PATH);
    $this->assertSession()->pageTextContains('Deleted 2 paragraph(s).');
    $this->assertSession()->pageTextContains('Displaying 1 out of 1 paragraphs.');
    $storage = $this->container->get('entity_type.manager')->getStorage('paragraph');
    $this->assertNull($storage->load($ids[0]));
    $this->assertNull($storage->load($ids[1]));
    $this->assertNotNull($storage->load($ids[2]));
  }

  /**
   * Delete Selected with nothing ticked warns and deletes nothing.
   */
  public function testBulkDeleteWithNothingSelectedWarns(): void {
    $ids = $this->createParagraphs('test_a', 2);
    $this->loginAdmin();

    $this->drupalGet(self::LIST_PATH);
    $this->submitForm([], 'Delete Selected');

    $this->assertSession()->pageTextContains('No paragraphs selected for deletion.');
    $this->assertSession()->pageTextContains('Displaying 2 out of 2 paragraphs.');
    $this->assertNotNull($this->container->get('entity_type.manager')->getStorage('paragraph')->load($ids[0]));
  }

  /**
   * Every route refuses anonymous visitors and users without the permission.
   */
  public function testAccessIsDenied(): void {
    $ids = $this->createParagraphs('test_a', 1);
    $paths = [
      self::LIST_PATH,
      'admin/content/paragraph/' . $ids[0] . '/edit',
      'admin/content/paragraphs/delete/' . $ids[0],
    ];

    foreach ($paths as $path) {
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals(403);
    }

    $this->drupalLogin($this->drupalCreateUser(['access content']));
    foreach ($paths as $path) {
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals(403);
    }
  }

  /**
   * The list needs `access paragraphs list`; administering the site is not it.
   */
  public function testListNeedsAccessParagraphsList(): void {
    $this->createParagraphs('test_a', 1);

    $this->drupalLogin($this->drupalCreateUser(['access content', 'administer site configuration']));
    $this->drupalGet(self::LIST_PATH);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser(['access paragraphs list']));
    $this->drupalGet(self::LIST_PATH);
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * The delete route renders the same list and is not a deletion by itself.
   */
  public function testKeptDeleteRouteRendersTheList(): void {
    $ids = $this->createParagraphs('test_a', 1);
    $this->loginAdmin();

    $this->drupalGet('admin/content/paragraphs/delete/' . $ids[0]);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Displaying 1 out of 1 paragraphs.');
    $this->assertNotNull($this->container->get('entity_type.manager')->getStorage('paragraph')->load($ids[0]));
  }

}
