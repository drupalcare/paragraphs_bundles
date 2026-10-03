<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The Paragraphs List button for bulk deletion asks first; cancel deletes none.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class ParagraphsListConfirmJavascriptTest extends WebDriverTestBase {

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
   * Cancelling the dialog deletes nothing; accepting it deletes the row.
   */
  public function testConfirmCancelAndAccept(): void {
    ParagraphsType::create(['id' => 'test_a', 'label' => 'Type A'])->save();
    $paragraph = Paragraph::create(['type' => 'test_a']);
    $paragraph->save();
    $id = $paragraph->id();
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));

    $storage = $this->container->get('entity_type.manager')->getStorage('paragraph');
    $this->drupalGet('admin/content/paragraphs/paragraphs-list');
    $session = $this->getSession();
    $page = $session->getPage();

    // The behavior must have attached; no inline handler is involved.
    $button = $page->findButton('Delete Selected');
    $this->assertNotNull($button);
    $this->assertFalse($button->hasAttribute('onclick'));
    $this->assertJsCondition("document.querySelector('[data-once~=\"pb-paragraphs-list-confirm\"]') !== null");

    $page->checkField('paragraphs_table[' . $id . ']');
    $button->click();
    $this->assertSame(
      'Are you sure you want to delete the selected paragraphs?',
      $session->getDriver()->getWebDriverSession()->alert()->getText(),
    );
    $session->getDriver()->getWebDriverSession()->alert()->dismiss();
    $this->assertNotNull($storage->loadUnchanged($id), 'Cancel deleted nothing.');
    $this->assertSession()->pageTextNotContains('Deleted 1 paragraph(s).');

    $page->findButton('Delete Selected')->click();
    $session->getDriver()->getWebDriverSession()->alert()->accept();
    $this->assertSession()->waitForText('Deleted 1 paragraph(s).');
    $this->assertNull($storage->loadUnchanged($id), 'OK deleted the row.');
  }

}
