<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\paragraphs_bundles\Traits\ListIndexHostTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Who may see, edit and delete from the Paragraphs List.
 *
 * Page A (type `page`) the editor may edit; page B (type `article`) they may
 * not. The list permission is the gate; Paragraphs' own access handler asks the
 * host entity for the rest.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class ParagraphsListAccessTest extends BrowserTestBase {

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
   * Paragraph id on page A, which the editor may edit.
   */
  private int $pidA;

  /**
   * Paragraph id on page B, which the editor may not edit.
   */
  private int $pidB;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createListIndexHosts();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    FieldConfig::create([
      'field_name' => 'field_paragraphs',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Paragraphs field',
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => ['negate' => 0, 'target_bundles' => NULL],
      ],
    ])->save();

    $a = Paragraph::create(['type' => 'leaf']);
    $this->saveNode('Page A', [$a]);
    $b = Paragraph::create(['type' => 'leaf']);
    Node::create(['type' => 'article', 'title' => 'Article B', 'field_paragraphs' => [$b]])->save();
    $this->pidA = (int) $a->id();
    $this->pidB = (int) $b->id();
  }

  /**
   * An editor with the list permission and edit rights on A only.
   */
  private function loginEditor(): void {
    $this->drupalLogin($this->drupalCreateUser(['access paragraphs list', 'access content', 'edit any page content']));
  }

  /**
   * A row's select box exists and is usable only where the user may update.
   */
  public function testControlsFollowHostEditAccess(): void {
    $this->loginEditor();
    $this->drupalGet(self::LIST_PATH);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Displaying 2 out of 2 paragraphs.');

    $this->assertSession()->elementExists('css', 'input[name="paragraphs_table[' . $this->pidA . ']"]:not([disabled])');
    $this->assertSession()->elementExists('css', 'input[name="paragraphs_table[' . $this->pidB . ']"][disabled]');
    $this->assertSession()->linkByHrefExists('/admin/content/paragraph/' . $this->pidA . '/edit');
    $this->assertSession()->linkByHrefNotExists('/admin/content/paragraph/' . $this->pidB . '/edit');
  }

  /**
   * The edit route follows the same access: A is open, B is 403.
   */
  public function testEditRouteFollowsHostEditAccess(): void {
    $this->loginEditor();
    $this->drupalGet('admin/content/paragraph/' . $this->pidA . '/edit');
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet('admin/content/paragraph/' . $this->pidB . '/edit');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('admin/content/paragraphs/delete/' . $this->pidB);
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * A paragraph nothing uses is editable with the list permission alone.
   */
  public function testOrphanIsEditableWithTheListPermission(): void {
    $orphan = Paragraph::create(['type' => 'leaf']);
    $orphan->save();
    $this->drupalLogin($this->drupalCreateUser(['access paragraphs list']));

    $this->drupalGet(self::LIST_PATH);
    $this->assertSession()->elementExists('css', 'input[name="paragraphs_table[' . $orphan->id() . ']"]:not([disabled])');
    $this->assertSession()->linkByHrefExists('/admin/content/paragraph/' . $orphan->id() . '/edit');
    $this->drupalGet('admin/content/paragraph/' . $orphan->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Without the permission every route is closed, however much else is granted.
   */
  public function testNoPermissionNoAccess(): void {
    $paths = [
      self::LIST_PATH,
      'admin/content/paragraph/' . $this->pidA . '/edit',
      'admin/content/paragraphs/delete/' . $this->pidA,
    ];
    foreach ($paths as $path) {
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals(403);
    }
    $this->drupalLogin($this->drupalCreateUser([
      'access content',
      'edit any page content',
      'administer site configuration',
    ]));
    foreach ($paths as $path) {
      $this->drupalGet($path);
      $this->assertSession()->statusCodeEquals(403);
    }
  }

  /**
   * A forged POST naming B's paragraph deletes only what the user may delete.
   */
  public function testForgedBulkDeleteOnlyDeletesAllowedParagraphs(): void {
    $this->loginEditor();
    $this->drupalGet(self::LIST_PATH);
    $page = $this->getSession()->getPage();
    $params = [
      'form_build_id' => $page->find('css', 'input[name="form_build_id"]')->getValue(),
      'form_token' => $page->find('css', 'input[name="form_token"]')->getValue(),
      'form_id' => 'paragraphs_bundles_paragraphs_list_form',
      'paragraphs_table' => [$this->pidA => $this->pidA, $this->pidB => $this->pidB],
      'op' => 'Delete Selected',
    ];
    $this->getSession()->getDriver()->getClient()->request('POST', $this->buildUrl(self::LIST_PATH), $params);

    $storage = $this->container->get('entity_type.manager')->getStorage('paragraph');
    $storage->resetCache();
    $this->assertNotNull($storage->load($this->pidB), "B's paragraph survived the forged request.");
    $this->assertNull($storage->load($this->pidA), "A's paragraph, which the editor may change, was deleted.");
    $this->assertSession()->pageTextContains('Deleted 1 paragraph(s).');
    $this->assertSession()->pageTextContains('Skipped 1 paragraph(s) you may not change.');
  }

}
