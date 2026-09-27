<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs_bundles_ai\Service\PageSurfaceInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * A page with two paragraph fields offers both, and never chooses in silence.
 *
 * THE DEFECT THIS EXISTS TO CATCH. Until 2026-09-17 the surface returned
 * `array_key_first()` of the displayed paragraph fields — one string — and the
 * bar wrote into it without ever naming it. On a content type with a body and
 * a footer, every section landed in whichever the field manager happened to
 * return first. Nothing refused it, because both fields are valid targets and
 * an unrestricted field accepts every bundle: the section was simply written
 * into the wrong half of the page, successfully.
 *
 * So the assertion is not "a field comes back" — that passed throughout. It is
 * that ALL of them come back, which is the only form the caller can offer a
 * person a choice from.
 *
 * @group paragraphs_bundles
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class MultipleParagraphFieldsTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'file',
    'entity_reference_revisions',
    'paragraphs',
    'key',
    'ai',
    'paragraphs_bundles',
    'paragraphs_bundles_ai',
  ];

  /**
   * The service under test.
   */
  private PageSurfaceInterface $surface;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'field', 'node']);

    ParagraphsType::create(['id' => 'simple_bundle', 'label' => 'Simple'])->save();

    // The surface refuses before it describes anything when no chat provider
    // is configured, so this has to be set or every case below would pass for
    // the wrong reason. The name only — no key is read, created or needed.
    $this->config('ai.settings')
      ->set('default_providers', ['chat' => ['provider_id' => 'test_provider']])
      ->save();

    $this->pageType('one_field_page', ['field_body_sections' => 'Body sections']);
    $this->pageType('two_field_page', [
      'field_body_sections' => 'Body sections',
      'field_footer_sections' => 'Footer sections',
    ]);

    $this->setUpCurrentUser(permissions: [
      'access content',
      'use paragraphs bundles ai assistant',
      'edit any one_field_page content',
      'edit any two_field_page content',
    ]);

    $this->surface = $this->container->get(PageSurfaceInterface::class);
  }

  /**
   * Control: one paragraph field still comes back, and the bar still shows.
   *
   * Without this, the two-field assertion could be passing because the surface
   * returns everything it finds regardless of whether it decided to show.
   */
  public function testOneFieldStillShowsAndReturnsThatField(): void {
    $node = $this->page('one_field_page');
    $decision = $this->surface->decide($node, 'full');

    self::assertTrue($decision->show, $decision->reason);
    self::assertSame(['field_body_sections'], array_keys($decision->fields));
  }

  /**
   * TWO fields come back as two, not as the first of them.
   *
   * This is the regression. `array_key_first()` would satisfy "a field is
   * returned" and fail here, which is the difference that matters.
   */
  public function testTwoFieldsBothComeBack(): void {
    $node = $this->page('two_field_page');
    $decision = $this->surface->decide($node, 'full');

    self::assertTrue($decision->show, $decision->reason);
    self::assertCount(2, $decision->fields, sprintf(
      'Both fields must be offered; got: %s',
      implode(', ', array_keys($decision->fields)),
    ));
    self::assertArrayHasKey('field_body_sections', $decision->fields);
    self::assertArrayHasKey('field_footer_sections', $decision->fields);
  }

  /**
   * The LABELS come back too, because a machine name is not a choice.
   *
   * "field_footer_sections" is not what the site builder called it, and it is
   * not what an editor should be asked to pick between. The map carries the
   * label the site builder actually wrote.
   */
  public function testTheFieldsCarryTheirHumanLabels(): void {
    $node = $this->page('two_field_page');
    $decision = $this->surface->decide($node, 'full');

    self::assertSame('Body sections', $decision->fields['field_body_sections']);
    self::assertSame('Footer sections', $decision->fields['field_footer_sections']);
  }

  /**
   * THE HIDE PATH RUNS AT ALL — which for twenty minutes it did not.
   *
   * When `PageDecision::$field` (string) became `$fields` (array), `hide()`
   * kept passing `''` and threw a TypeError. Every node rendered in any view
   * mode other than `full` — every teaser, every listing — went down with it,
   * while `php -l` stayed clean and all 53 tests passed, because not one of
   * them ever asked for a decision that hides.
   *
   * So this asserts the cheapest possible thing: that hiding returns.
   */
  public function testHidingDoesNotThrow(): void {
    $node = $this->page('two_field_page');

    // A teaser is the commonest hidden case and the one that took the site
    // down: it is rendered on every listing page.
    $decision = $this->surface->decide($node, 'teaser');

    self::assertFalse($decision->show);
    self::assertSame([], $decision->fields);
    self::assertNotSame('', $decision->reason, 'A hidden surface must say why.');
  }

  /**
   * Every OTHER way of hiding also returns rather than throwing.
   *
   * There are five hide() calls in decide(), and the test above only reaches
   * the first. A TypeError in a later branch would be just as fatal and just
   * as invisible.
   */
  public function testHidingWithoutPermissionAlsoReturns(): void {
    $this->setUpCurrentUser(permissions: ['access content']);
    $surface = $this->container->get(PageSurfaceInterface::class);

    $decision = $surface->decide($this->page('two_field_page'), 'full');

    self::assertFalse($decision->show);
    self::assertSame([], $decision->fields);
    self::assertNotSame('', $decision->reason);
  }

  /**
   * A field hidden in the display is not offered, even on a two-field type.
   *
   * Adding a section to a field the page does not render puts it somewhere
   * nobody can see, which reads as the tool having done nothing at all.
   */
  public function testHiddenFieldIsNotOffered(): void {
    $display = \Drupal::service('entity_display.repository')
      ->getViewDisplay('node', 'two_field_page', 'default');
    $display->removeComponent('field_footer_sections')->save();

    $node = $this->page('two_field_page');
    $decision = $this->surface->decide($node, 'full');

    self::assertTrue($decision->show, $decision->reason);
    self::assertSame(['field_body_sections'], array_keys($decision->fields));
  }

  /**
   * Creates a content type whose paragraph fields are all displayed.
   *
   * @param string $type
   *   Node bundle machine name.
   * @param array<string, string> $fields
   *   Field machine name => label.
   */
  private function pageType(string $type, array $fields): void {
    NodeType::create(['type' => $type, 'name' => ucfirst($type)])->save();

    $display = \Drupal::service('entity_display.repository')
      ->getViewDisplay('node', $type, 'default');

    foreach ($fields as $name => $label) {
      if (FieldStorageConfig::loadByName('node', $name) === NULL) {
        FieldStorageConfig::create([
          'field_name' => $name,
          'entity_type' => 'node',
          'type' => 'entity_reference_revisions',
          'cardinality' => -1,
          'settings' => ['target_type' => 'paragraph'],
        ])->save();
      }

      FieldConfig::create([
        'field_name' => $name,
        'entity_type' => 'node',
        'bundle' => $type,
        'label' => $label,
        'settings' => [
          'handler' => 'default:paragraph',
          // Empty means ALL, which is how Drupal stores an unrestricted field
          // and is the case that makes the defect silent: both fields accept
          // every bundle, so writing into the wrong one is never refused.
          'handler_settings' => ['target_bundles' => []],
        ],
      ])->save();

      $display->setComponent($name, [
        'type' => 'entity_reference_revisions_entity_view',
        'label' => 'hidden',
        'settings' => ['view_mode' => 'default'],
      ]);
    }

    $display->save();
  }

  /**
   * A saved node of the given type.
   *
   * @param string $type
   *   Node bundle machine name.
   *
   * @return \Drupal\node\Entity\Node
   *   The node.
   */
  private function page(string $type): Node {
    $node = Node::create(['type' => $type, 'title' => 'A page', 'status' => 1]);
    $node->save();

    return $node;
  }

}
