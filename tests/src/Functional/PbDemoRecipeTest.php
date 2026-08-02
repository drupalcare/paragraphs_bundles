<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Functional;

use Drupal\FunctionalTests\Core\Recipe\RecipeTestTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Applies the paragraphs_bundles_demo recipe on a clean site.
 *
 * Verifies the recipe end-to-end: it enables the bundle modules, installs the
 * pb_content node type (which depends on menu_ui), imports the demo node, and
 * the resulting page renders. Runs on an isolated test install, so it proves
 * the recipe works from scratch without any pre-existing config.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbDemoRecipeTest extends BrowserTestBase {

  use RecipeTestTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The recipe installs pb_content and a browsable demo page.
   */
  public function testDemoRecipeApplies(): void {
    // Nothing bundle-related is installed on the bare test site.
    $this->assertFalse(
      \Drupal::moduleHandler()->moduleExists('paragraphs_bundles'),
      'The module is not installed before the recipe runs.',
    );

    // The recipe ships inside the module; resolve its path relative to root.
    $modulePath = \Drupal::service('extension.list.module')
      ->getPath('paragraphs_bundles');
    $this->applyRecipe($modulePath . '/recipes/paragraphs_bundles_demo');

    // The recipe enabled the module set (incl. menu_ui for the node type).
    $moduleHandler = \Drupal::moduleHandler();
    $this->assertTrue($moduleHandler->moduleExists('paragraphs_bundles'));
    $this->assertTrue($moduleHandler->moduleExists('menu_ui'));
    $this->assertTrue($moduleHandler->moduleExists('paragraph_bundle_accordion'));

    // The pb_content node type (optional config gated on menu_ui) installed.
    $nodeType = \Drupal::entityTypeManager()
      ->getStorage('node_type')->load('pb_content');
    $this->assertNotNull($nodeType, 'The pb_content node type was created.');

    // The demo node was imported.
    $nodes = \Drupal::entityTypeManager()->getStorage('node')
      ->loadByProperties(['type' => 'pb_content']);
    $this->assertNotEmpty($nodes, 'The demo node was imported.');
    $node = reset($nodes);

    // The demo page renders for a content viewer.
    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('access content')
      ->save();
    $this->drupalGet($node->toUrl());
    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    $assert->pageTextContains('Build rich Drupal pages with Paragraphs Bundles');
    // A couple of the demo bundles rendered their markup.
    $assert->elementExists('css', '.paragraphs-bundles.pb-accordion');
    $assert->elementExists('css', '.paragraphs-bundles.pb-tabs');
  }

}
