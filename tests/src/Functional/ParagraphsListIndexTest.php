<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Functional;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\paragraphs_bundles\Traits\ListIndexHostTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The Paragraphs List page driven by the index: columns, sort, search, paging.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class ParagraphsListIndexTest extends BrowserTestBase {

  use ListIndexHostTrait;

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
  protected static $modules = ['paragraphs_bundles', 'node', 'block_content'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createListIndexHosts();
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
  }

  /**
   * Reads one column of the table as plain text, in row order.
   *
   * @return list<string>
   *   The trimmed cell texts.
   */
  private function column(string $column): array {
    $values = [];
    foreach ($this->getSession()->getPage()->findAll('css', '#paragraphs-table tbody td.pb-col--' . $column) as $cell) {
      $values[] = trim($cell->getText());
    }
    return $values;
  }

  /**
   * Creates stand-alone (orphan) paragraphs of one type.
   *
   * @return list<int>
   *   The new paragraph ids.
   */
  private function orphans(string $type, int $count): array {
    $ids = [];
    for ($i = 0; $i < $count; $i++) {
      $paragraph = Paragraph::create(['type' => $type]);
      $paragraph->save();
      $ids[] = (int) $paragraph->id();
    }
    return $ids;
  }

  /**
   * Builds three nodes with one paragraph each, titled out of order.
   *
   * @return array<string, int>
   *   Node ids keyed by title.
   */
  private function threeNodes(): array {
    $nids = [];
    foreach (['Bravo page', 'Alpha page', 'The Alpha story'] as $title) {
      $nids[$title] = (int) $this->saveNode($title, [Paragraph::create(['type' => 'leaf'])])->id();
    }
    return $nids;
  }

  /**
   * The new columns show the NID and a linked content title; PID is the header.
   */
  public function testNodeColumnsAndLinks(): void {
    $nids = $this->threeNodes();
    $block = $this->saveBlock('Hero block', [Paragraph::create(['type' => 'leaf'])]);
    $orphan = $this->orphans('leaf', 1);

    $this->drupalGet(self::LIST_PATH);
    $this->assertSession()->elementExists('xpath', '//table[@id="paragraphs-table"]//th[contains(normalize-space(.), "PID")]');
    $this->assertSession()->elementNotExists('xpath', '//table[@id="paragraphs-table"]//th[normalize-space(.)="ID"]');
    $this->assertSession()->pageTextContains('Displaying 5 out of 5 paragraphs.');

    $nid_cells = $this->column('nid');
    $this->assertContains((string) $nids['Alpha page'], $nid_cells);
    $this->assertContains('block_content ' . $block->id(), $nid_cells);
    $this->assertContains('—', $nid_cells, 'The orphan shows a dash.');
    $this->assertContains((string) $orphan[0], $this->column('id'));
    $this->assertSession()->linkExists('Alpha page');
    $this->assertSession()->linkByHrefExists('/node/' . $nids['Alpha page']);
  }

  /**
   * Each indexed column sorts both ways, and the sorted header says so.
   */
  public function testSortingByEveryIndexedColumn(): void {
    $nids = $this->threeNodes();
    $this->orphans('container', 1);

    $this->drupalGet(self::LIST_PATH, ['query' => ['sort' => 'pid', 'dir' => 'desc']]);
    $pids = array_map('intval', $this->column('id'));
    $expected = $pids;
    rsort($expected);
    $this->assertSame($expected, $pids, 'PID descending.');
    $this->assertSession()->elementExists('css', 'th[aria-sort="descending"]');

    $this->drupalGet(self::LIST_PATH, ['query' => ['sort' => 'nid', 'dir' => 'asc']]);
    $nid_cells = $this->column('nid');
    // The orphan has no root: its empty root type sorts first.
    $this->assertSame('—', $nid_cells[0]);
    $this->assertSame(
      [(string) $nids['Bravo page'], (string) $nids['Alpha page'], (string) $nids['The Alpha story']],
      array_slice($nid_cells, 1),
      'Rooted rows follow, in node id order (created Bravo, Alpha, The Alpha).'
    );
    $this->assertSession()->elementExists('css', 'th[aria-sort="ascending"]');

    $this->drupalGet(self::LIST_PATH, ['query' => ['sort' => 'type', 'dir' => 'asc']]);
    $types = $this->column('type');
    $sorted = $types;
    sort($sorted);
    $this->assertSame($sorted, $types, 'Type ascending.');

    $this->drupalGet(self::LIST_PATH, ['query' => ['sort' => 'label', 'dir' => 'asc']]);
    $labels = $this->column('label');
    $this->assertSame('Alpha page > Paragraphs field', $labels[0]);
    $this->assertSession()->elementExists('css', 'th[aria-sort="ascending"]');
  }

  /**
   * Content title: "starts with" and "contains" find different things.
   */
  public function testTitleStartsWithVersusContains(): void {
    $this->threeNodes();

    $this->drupalGet(self::LIST_PATH, ['query' => ['title' => 'Alpha', 'title_mode' => 'starts']]);
    $this->assertSession()->pageTextContains('Displaying 1 out of 1 paragraphs.');
    $this->assertSession()->linkExists('Alpha page');
    $this->assertSession()->linkNotExists('The Alpha story');

    $this->drupalGet(self::LIST_PATH, ['query' => ['title' => 'Alpha', 'title_mode' => 'contains']]);
    $this->assertSession()->pageTextContains('Displaying 2 out of 2 paragraphs.');
    $this->assertSession()->linkExists('Alpha page');
    $this->assertSession()->linkExists('The Alpha story');
    $this->assertSession()->linkNotExists('Bravo page');

    // The form submits the same thing and keeps it in the address.
    $this->drupalGet(self::LIST_PATH);
    $this->submitForm(['title' => 'Bravo', 'title_mode' => 'starts'], 'Filter');
    $this->assertSession()->addressEquals(self::LIST_PATH . '?title=Bravo&title_mode=starts&sort=node_title&dir=asc');
    $this->assertSession()->pageTextContains('Displaying 1 out of 1 paragraphs.');
  }

  /**
   * Label search matches the text in the Label column, not the node title only.
   */
  public function testLabelContains(): void {
    $this->threeNodes();
    $this->orphans('container', 2);

    $this->drupalGet(self::LIST_PATH, ['query' => ['label' => 'Orphaned Container']]);
    $this->assertSession()->pageTextContains('Displaying 2 out of 2 paragraphs.');

    $this->drupalGet(self::LIST_PATH, ['query' => ['label' => 'Paragraphs field']]);
    $this->assertSession()->pageTextContains('Displaying 3 out of 3 paragraphs.');

    $this->drupalGet(self::LIST_PATH, ['query' => ['label' => 'nothing like this']]);
    $this->assertSession()->pageTextContains('No paragraphs found.');
  }

  /**
   * Next / Previous page by keyset, with a filter, and return to the same rows.
   */
  public function testKeysetPagingWithFilterAndSort(): void {
    $leaf_ids = $this->orphans('leaf', 120);
    $this->orphans('container', 5);

    $this->drupalGet(self::LIST_PATH, ['query' => ['type' => 'leaf', 'sort' => 'pid', 'dir' => 'desc']]);
    $this->assertSession()->pageTextContains('Displaying 50 out of 120 paragraphs.');
    $page1 = array_map('intval', $this->column('id'));
    $this->assertSame(array_slice(array_reverse($leaf_ids), 0, 50), $page1);
    $this->assertSession()->linkNotExists('Previous');

    $this->clickLink('Next');
    $page2 = array_map('intval', $this->column('id'));
    $this->assertSame(array_slice(array_reverse($leaf_ids), 50, 50), $page2, 'Page two continues where page one stopped, filter and sort kept.');
    $this->assertSession()->linkExists('Previous');
    $this->assertSession()->linkExists('Next');

    $this->clickLink('Next');
    $page3 = array_map('intval', $this->column('id'));
    $this->assertSame(array_slice(array_reverse($leaf_ids), 100), $page3);
    $this->assertCount(20, $page3);
    $this->assertSession()->linkNotExists('Next');

    $this->clickLink('Previous');
    $this->assertSame($page2, array_map('intval', $this->column('id')), 'Previous returns to the same rows.');
    $this->clickLink('Previous');
    $this->assertSame($page1, array_map('intval', $this->column('id')));
    $this->assertSession()->linkNotExists('Previous');
  }

  /**
   * Paging by a text column still visits every row once, in sorted order.
   */
  public function testKeysetPagingByLabelVisitsEveryRowOnce(): void {
    $this->orphans('leaf', 30);
    $this->orphans('container', 30);
    $this->threeNodes();

    $database = $this->container->get('database');
    $expected = $database->query('SELECT pid FROM {paragraphs_bundles_list_index} ORDER BY label ASC, pid ASC')->fetchCol();

    $this->drupalGet(self::LIST_PATH, ['query' => ['sort' => 'label', 'dir' => 'asc']]);
    $seen = array_map('intval', $this->column('id'));
    $this->clickLink('Next');
    $seen = array_merge($seen, array_map('intval', $this->column('id')));
    $this->assertSame(array_map('intval', $expected), $seen);
    $this->assertSame(count($seen), count(array_unique($seen)), 'No row appears twice.');
  }

  /**
   * Content title sorts both ways, from the index, with Next / Previous.
   */
  public function testSortingByContentTitle(): void {
    $this->threeNodes();
    $this->orphans('container', 1);

    $this->drupalGet(self::LIST_PATH, ['query' => ['sort' => 'node_title', 'dir' => 'asc']]);
    $this->assertSame(
      ['—', 'Alpha page', 'Bravo page', 'The Alpha story'],
      $this->column('node_title'),
      'The orphan (no title) first, then the titles in order.'
    );
    $this->assertSession()->elementExists('css', 'th[aria-sort="ascending"] a[href*="sort=node_title"]');

    $this->drupalGet(self::LIST_PATH, ['query' => ['sort' => 'node_title', 'dir' => 'desc']]);
    $this->assertSame(
      ['The Alpha story', 'Bravo page', 'Alpha page', '—'],
      $this->column('node_title')
    );
  }

  /**
   * Paging by Content title visits every row once, in title then pid order.
   */
  public function testKeysetPagingByContentTitleVisitsEveryRowOnce(): void {
    $this->orphans('leaf', 30);
    $this->threeNodes();
    for ($i = 0; $i < 30; $i++) {
      $this->saveNode('Bulk ' . $i, [Paragraph::create(['type' => 'leaf'])]);
    }
    $database = $this->container->get('database');

    foreach (['asc', 'desc'] as $dir) {
      $expected = array_map('intval', $database->query(
        'SELECT pid FROM {paragraphs_bundles_list_index} ORDER BY root_title ' . strtoupper($dir) . ', pid ' . strtoupper($dir)
      )->fetchCol());
      $this->drupalGet(self::LIST_PATH, ['query' => ['sort' => 'node_title', 'dir' => $dir]]);
      $seen = array_map('intval', $this->column('id'));
      $this->clickLink('Next');
      $seen = array_merge($seen, array_map('intval', $this->column('id')));
      $this->assertSame(array_slice($expected, 0, 100), $seen, "Two pages in $dir order match the index order.");
      $this->clickLink('Previous');
      $this->assertSame(array_slice($expected, 0, 50), array_map('intval', $this->column('id')), 'Previous returns to page one.');
    }
  }

  /**
   * A cursor or sort the page did not create is ignored, never an error.
   */
  public function testGarbageParametersFallBackToTheFirstPage(): void {
    $this->orphans('leaf', 3);
    $garbage = [
      'after' => 'not-a-cursor!!',
      'before' => 'WyJ4Il0',
      'sort' => 'DROP TABLE',
      'dir' => 'sideways',
      'title_mode' => 'regex',
    ];
    foreach ($garbage as $name => $value) {
      $this->drupalGet(self::LIST_PATH, ['query' => [$name => $value]]);
      $this->assertSession()->statusCodeEquals(200);
      $this->assertSession()->pageTextContains('Displaying 3 out of 3 paragraphs.');
    }
  }

  /**
   * The count is cached, and a new paragraph invalidates it.
   */
  public function testCountFollowsChanges(): void {
    $this->orphans('leaf', 2);
    $this->drupalGet(self::LIST_PATH);
    $this->assertSession()->pageTextContains('Displaying 2 out of 2 paragraphs.');
    $this->drupalGet(self::LIST_PATH);
    $this->assertSession()->pageTextContains('Displaying 2 out of 2 paragraphs.');
    $this->orphans('leaf', 1);
    $this->drupalGet(self::LIST_PATH);
    $this->assertSession()->pageTextContains('Displaying 3 out of 3 paragraphs.');
  }

  /**
   * Search, sort and paging addresses are as closed as the plain list.
   */
  public function testSearchAddressesAreAccessControlled(): void {
    $this->orphans('leaf', 1);
    $this->drupalLogout();
    $addresses = [
      ['title' => 'x'],
      ['label' => 'x'],
      ['sort' => 'nid', 'dir' => 'desc'],
      ['after' => 'WzFd'],
    ];
    foreach ($addresses as $query) {
      $this->drupalGet(self::LIST_PATH, ['query' => $query]);
      $this->assertSession()->statusCodeEquals(403);
    }
    $this->drupalLogin($this->drupalCreateUser(['access content']));
    foreach ($addresses as $query) {
      $this->drupalGet(self::LIST_PATH, ['query' => $query]);
      $this->assertSession()->statusCodeEquals(403);
    }
  }

  /**
   * The table is in a labelled, focusable scroll region and the filters stack.
   */
  public function testLayoutHooksForTheStylesheet(): void {
    $this->orphans('leaf', 1);
    $this->drupalGet(self::LIST_PATH);
    $this->assertSession()->elementExists('css', 'form.pb-paragraphs-list .pb-paragraphs-list__filters');
    $this->assertSession()->elementExists('css', '.pb-paragraphs-list__scroll[role="region"][tabindex="0"][aria-label]');
    $this->assertSession()->elementExists('css', 'table#paragraphs-table th[data-pb-col="label"][data-pb-col-label="Label"]');
    // Every body cell of a hideable column carries the same column id.
    $this->assertSession()->elementExists('css', 'table#paragraphs-table td[data-pb-col="parent"]');
    $this->assertSession()->responseContains('paragraphs-bundles-paragraphs-list.css');
  }

}
