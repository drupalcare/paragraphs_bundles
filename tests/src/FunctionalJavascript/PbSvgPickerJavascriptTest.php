<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * An editor opens the picker, searches, picks a preview, saves and sees it.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbSvgPickerJavascriptTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'pb_svg_test_child';

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = ['node', 'paragraphs_bundles', 'paragraph_bundle_icon'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalCreateContentType(['type' => 'host', 'name' => 'Host']);
    FieldStorageConfig::create([
      'field_name' => 'field_sections',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'cardinality' => -1,
      'settings' => ['target_type' => 'paragraph'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_sections',
      'entity_type' => 'node',
      'bundle' => 'host',
      'label' => 'Sections',
      'settings' => ['handler' => 'default:paragraph', 'handler_settings' => []],
    ])->save();
    $repository = $this->container->get('entity_display.repository');
    $repository->getFormDisplay('node', 'host')
      ->setComponent('field_sections', ['type' => 'paragraphs'])
      ->save();
    $repository->getViewDisplay('node', 'host')
      ->setComponent('field_sections', ['type' => 'entity_reference_revisions_entity_view'])
      ->save();
    $this->drupalLogin($this->drupalCreateUser([], NULL, TRUE));
  }

  /**
   * The whole editor path, on the real form.
   */
  public function testPickSearchSaveRender(): void {
    $this->drupalGet('node/add/host');
    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();
    $page->fillField('title[0][value]', 'SVG host');
    $add = $assert->waitForElementVisible('css', 'input[name="field_sections_icon_bundle_add_more"], button[name="field_sections_icon_bundle_add_more"]');
    self::assertNotNull($add);
    $add->press();

    // On the icon the picker sits in the "Image Icon" tab.
    $tab = $assert->waitForElementVisible('css', 'a[href^="#edit-group-pb-image-icon"]');
    self::assertNotNull($tab);
    $tab->click();
    $trigger = $assert->waitForElementVisible('css', '.pb-svg-picker__trigger');
    self::assertNotNull($trigger, 'The picker replaced the radios.');
    self::assertSame('false', $trigger->getAttribute('aria-expanded'));
    $trigger->click();
    self::assertSame('true', $trigger->getAttribute('aria-expanded'));

    $search = $assert->waitForElementVisible('css', '.pb-svg-picker__search-input');
    self::assertNotNull($search);
    // Both themes of the chain are offered, plus "No SVG"; the text file is not.
    $values = $this->getSession()->evaluateScript('Array.from(document.querySelectorAll(".pb-svg-picker input[type=radio]")).map((el) => el.value).sort().join("|")');
    self::assertSame('_none|pb_svg_test_child:cars/sedan|pb_svg_test_child:hostile|pb_svg_test_parent:arrow', $values);
    $search->setValue('sedan');
    $assert->assertWaitOnAjaxRequest();
    $visible = $this->getSession()->evaluateScript('Array.from(document.querySelectorAll(".pb-svg-picker__item")).filter((el) => el.offsetParent !== null).map((el) => el.dataset.pbSvgSearch).join("|")');
    self::assertIsString($visible);
    self::assertStringContainsString('sedan', $visible);
    self::assertStringNotContainsString('arrow', $visible);

    $tile = $assert->waitForElementVisible('css', '.pb-svg-picker__item[data-pb-svg-search*="sedan"] label');
    self::assertNotNull($tile);
    $tile->click();
    $checked = $this->getSession()->evaluateScript('document.querySelector(".pb-svg-picker__item input:checked")?.value');
    self::assertSame('pb_svg_test_child:cars/sedan', $checked);

    $page->pressButton('Save');
    self::assertNotNull($assert->waitForText('SVG host has been created.'));
    // Asked of the browser: Mink's CSS lookup cannot see into SVG elements.
    self::assertTrue($this->getSession()->evaluateScript('document.querySelector(".pb-svg--icon svg #pb-sedan") !== null'));
    self::assertFalse($this->getSession()->evaluateScript('document.querySelector(".pb-svg--icon script") !== null'));
  }

}
