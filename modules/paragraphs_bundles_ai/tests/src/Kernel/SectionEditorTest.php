<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_bundles_ai\Service\SectionEditorInterface;
use Drupal\Tests\paragraphs_bundles_ai\Traits\AiCoreSupportTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The editor rewrites words, and refuses everything that is not words.
 *
 * WHY THIS FILE EXISTS AT ALL. Until 2026-09-24 SectionEditor had no test of
 * any kind — not a unit test, not a kernel test — while being the one service
 * that writes into a paragraph somebody is looking at. The defect below was
 * found by a type checker, not by a failure, because nothing here could fail.
 *
 * 🚨 THE DEFECT, AND WHY IT IS NOT MERELY A TYPE COMPLAINT. `$values` carries
 * whatever the model answered, so a value can be an array or an object. The
 * cast that turns it into text behaves differently for each — measured on PHP
 * 8.5.10, 2026-09-24:
 *
 *   (string) ['a', 'b']     warning only, and the literal string "Array"
 *   (string) new stdClass   Error, "could not be converted to string"
 *
 * So the object arm takes the page down, and the ARRAY ARM IS THE QUIET ONE
 * AND THE WORSE ONE: nothing is thrown, nothing is logged where anybody looks,
 * and the word "Array" is saved into the section as though it were the copy
 * that was asked for. A new revision is written to record it.
 *
 * The first test below is the one that goes red when the guard is removed. It
 * does not assert that a refusal happened — a refusal can be right for the
 * wrong reason — it asserts that the FIELD STILL SAYS WHAT IT SAID.
 *
 * @group paragraphs_bundles
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class SectionEditorTest extends KernelTestBase {
  use AiCoreSupportTrait;

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
    // field_group, because the profiler reads the form display's groups to
    // decide which tab a field is on, and the editor refuses anything that is
    // not on the Content tab. Without it every case here would be refused for
    // the wrong reason and the suite would be green and worthless.
    'field_group',
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
  private SectionEditorInterface $editor;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->skipUnlessCoreSupportsAi();
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'field', 'filter', 'node']);

    ParagraphsType::create(['id' => 'slideshow_bundle', 'label' => 'Slideshow'])->save();

    // A plain string field: `string` is in FieldFact::WORDS, and a field with
    // no allowed-values list skips that branch entirely — which is what sends
    // a bad value all the way to the cast this test is about.
    $this->paragraphField('slideshow_bundle', 'pb_content_title', 'Title');
    $this->contentTabDisplay('slideshow_bundle', ['pb_content_title']);

    $this->contentType('simple_page', 'field_sections');
    $this->contentType('other_page', 'field_sections');

    $this->setUpCurrentUser(permissions: [
      'access content',
      'edit any simple_page content',
      'edit any other_page content',
      'bypass node access',
    ]);

    $this->editor = $this->container->get(SectionEditorInterface::class);
  }

  /**
   * 🚨 THE REGRESSION. A list answer must not become the word "Array".
   *
   * Remove the `is_scalar()` guard from SectionEditor::edit() and this is the
   * test that fails: the cast yields "Array", the value differs from what was
   * there, and it is saved. Every other test in this file still passes.
   */
  public function testListAnswerNeverBecomesTheWordArray(): void {
    [$node, $paragraphId] = $this->pageWithSection('The autumn range');

    $outcome = $this->editor->edit($node, 'field_sections', $paragraphId, [
      'pb_content_title' => ['The autumn range', 'and another line'],
    ]);

    self::assertFalse($outcome->changed, 'A list is not words, and must not be written.');

    $title = $this->titleOf($paragraphId);
    self::assertSame(
      'The autumn range',
      $title,
      'The section must still say what it said before the bad answer arrived.',
    );
    self::assertNotSame('Array', $title, 'The literal string "Array" reached the page.');
  }

  /**
   * An object answer is refused rather than thrown.
   *
   * The other half of the same guard, and the half that fails loudly: without
   * it this is an Error, not a wrong value, so the page 500s mid-edit.
   */
  public function testAnObjectAnswerIsRefusedRatherThanThrowing(): void {
    [$node, $paragraphId] = $this->pageWithSection('Winter');

    $outcome = $this->editor->edit($node, 'field_sections', $paragraphId, [
      'pb_content_title' => new \stdClass(),
    ]);

    self::assertFalse($outcome->changed);
    self::assertStringContainsString(
      'rather than words',
      (string) $outcome->refusals[0],
      'The refusal names the field, so the person knows which answer was wrong.',
    );
    self::assertSame('Winter', $this->titleOf($paragraphId));
  }

  /**
   * CONTROL · ordinary words are still written.
   *
   * Without this, both refusals above could be passing because the editor
   * refuses everything — which is exactly how a guard that is too wide hides.
   */
  public function testOrdinaryWordsAreStillWritten(): void {
    [$node, $paragraphId] = $this->pageWithSection('Old title');

    $outcome = $this->editor->edit($node, 'field_sections', $paragraphId, [
      'pb_content_title' => 'A new title',
    ]);

    self::assertTrue($outcome->changed, implode(' | ', array_map(strval(...), $outcome->refusals)));
    self::assertSame('A new title', $this->titleOf($paragraphId));
    self::assertNotNull($outcome->revisionId, 'A change must report the revision to undo to.');
  }

  /**
   * Rewriting a section with the words it already has writes nothing.
   *
   * The `$before`/`$after` comparison this guards was broken once before, by
   * reading the field with getString() — which appended the text format id, so
   * $before never equalled $after and a no-op still wrote two revisions.
   */
  public function testTheSameWordsWriteNoRevision(): void {
    [$node, $paragraphId] = $this->pageWithSection('Unchanged');
    $before = (int) $node->getRevisionId();

    $outcome = $this->editor->edit($node, 'field_sections', $paragraphId, [
      'pb_content_title' => 'Unchanged',
    ]);

    self::assertFalse($outcome->changed);
    self::assertSame($before, (int) $this->reload((int) $node->id())->getRevisionId());
  }

  /**
   * 🚨 A SECTION ON SOMEBODY ELSE'S PAGE IS REFUSED.
   *
   * Paragraph ids are sequential across the whole site and the id arrives from
   * a form, so without this check the editor is an editor for every paragraph
   * the site has. deltaOf() was rewritten on 2026-09-24 — it now reads the
   * items through one guarded helper — and this is what makes that rewrite
   * safe to have done rather than merely plausible.
   */
  public function testSectionOnAnotherPageIsRefusedAndUntouched(): void {
    [$myPage, $mine] = $this->pageWithSection('Mine');
    [, $theirs] = $this->pageWithSection('Theirs', 'other_page');

    // Aimed at MY page, naming THEIR section. The node is the one this test
    // created, held onto rather than recovered by id arithmetic: a security
    // assertion that depends on nodes being numbered 1 and 2 is testing the
    // fixture's luck, not the check.
    $outcome = $this->editor->edit($myPage, 'field_sections', $theirs, [
      'pb_content_title' => 'Rewritten from somewhere else',
    ]);

    self::assertFalse($outcome->changed);
    self::assertSame('Theirs', $this->titleOf($theirs), 'Another page\'s section was edited.');
    self::assertSame('Mine', $this->titleOf($mine));
  }

  /**
   * A field that is not on the page at all is refused by name.
   */
  public function testAnUnknownFieldIsRefusedByName(): void {
    [$node, $paragraphId] = $this->pageWithSection('Here');

    $outcome = $this->editor->edit($node, 'field_no_such_field', $paragraphId, [
      'pb_content_title' => 'Anything',
    ]);

    self::assertFalse($outcome->changed);
    self::assertStringContainsString('field_no_such_field', (string) $outcome->refusals[0]);
  }

  /**
   * The current words come back, without the text format glued on.
   */
  public function testCurrentValuesReadsTheWordsAndNothingElse(): void {
    [, $paragraphId] = $this->pageWithSection('Just the words');

    self::assertSame(
      ['pb_content_title' => 'Just the words'],
      $this->editor->currentValues($paragraphId),
    );
  }

  /**
   * A saved page carrying one section with the given title.
   *
   * @param string $title
   *   What the section says.
   * @param string $type
   *   The content type to put it on.
   *
   * @return array{0: \Drupal\node\NodeInterface, 1: int}
   *   The node, and the paragraph id.
   */
  private function pageWithSection(string $title, string $type = 'simple_page'): array {
    $paragraph = Paragraph::create([
      'type' => 'slideshow_bundle',
      'pb_content_title' => $title,
    ]);
    $paragraph->save();

    $node = Node::create([
      'type' => $type,
      'title' => 'A page',
      'status' => 1,
      'field_sections' => [
        [
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ],
      ],
    ]);
    $node->save();

    return [$node, (int) $paragraph->id()];
  }

  /**
   * What a section currently says, read fresh from storage.
   *
   * @param int $paragraphId
   *   The section.
   *
   * @return string
   *   The title field's value.
   */
  private function titleOf(int $paragraphId): string {
    $storage = $this->container->get('entity_type.manager')->getStorage('paragraph');
    $storage->resetCache([$paragraphId]);
    $paragraph = $storage->load($paragraphId);
    self::assertInstanceOf(ParagraphInterface::class, $paragraph);

    return (string) $paragraph->get('pb_content_title')->value;
  }

  /**
   * Loads a node fresh from storage.
   *
   * @param int $id
   *   Node id.
   *
   * @return \Drupal\node\NodeInterface
   *   The node.
   */
  private function reload(int $id): NodeInterface {
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $storage->resetCache([$id]);
    $node = $storage->load($id);
    self::assertInstanceOf(NodeInterface::class, $node);

    return $node;
  }

  /**
   * Adds a plain text field to a paragraph bundle.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   * @param string $name
   *   Field machine name.
   * @param string $label
   *   Human label — what a refusal names.
   */
  private function paragraphField(string $bundle, string $name, string $label): void {
    if (FieldStorageConfig::loadByName('paragraph', $name) === NULL) {
      FieldStorageConfig::create([
        'field_name' => $name,
        'entity_type' => 'paragraph',
        'type' => 'string',
      ])->save();
    }

    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'paragraph',
      'bundle' => $bundle,
      'label' => $label,
    ])->save();
  }

  /**
   * Puts the given fields on the Content tab, the way PB groups one.
   *
   * The groups are what the profiler reads, and a field outside them is not
   * content — so this is not decoration, it is the fixture that makes the
   * editor reach its own logic at all.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   * @param list<string> $fields
   *   The fields to place on the Content tab.
   */
  private function contentTabDisplay(string $bundle, array $fields): void {
    $display = EntityFormDisplay::create([
      'targetEntityType' => 'paragraph',
      'bundle' => $bundle,
      'mode' => 'default',
      'status' => TRUE,
    ]);

    foreach ($fields as $weight => $field) {
      $display->setComponent($field, ['weight' => $weight]);
    }

    $display->setThirdPartySetting('field_group', 'group_pb_tabs_wrapper', [
      'children' => ['group_pb_tab_content'],
      'parent_name' => '',
      'label' => 'Tabs Wrapper',
      'region' => 'content',
      'weight' => 0,
      'format_type' => 'tabs',
      'format_settings' => [],
    ]);
    $display->setThirdPartySetting('field_group', 'group_pb_tab_content', [
      'children' => $fields,
      'parent_name' => 'group_pb_tabs_wrapper',
      'label' => 'Content',
      'region' => 'content',
      'weight' => 0,
      'format_type' => 'tab',
      'format_settings' => [],
    ]);

    $display->save();
  }

  /**
   * Creates a content type with one unrestricted paragraph field.
   *
   * @param string $type
   *   Node bundle machine name.
   * @param string $field
   *   Paragraph field machine name.
   */
  private function contentType(string $type, string $field): void {
    NodeType::create(['type' => $type, 'name' => ucfirst($type)])->save();

    if (FieldStorageConfig::loadByName('node', $field) === NULL) {
      FieldStorageConfig::create([
        'field_name' => $field,
        'entity_type' => 'node',
        'type' => 'entity_reference_revisions',
        'cardinality' => -1,
        'settings' => ['target_type' => 'paragraph'],
      ])->save();
    }

    FieldConfig::create([
      'field_name' => $field,
      'entity_type' => 'node',
      'bundle' => $type,
      'label' => 'Sections',
      'settings' => [
        'handler' => 'default:paragraph',
        'handler_settings' => ['target_bundles' => []],
      ],
    ])->save();
  }

}
