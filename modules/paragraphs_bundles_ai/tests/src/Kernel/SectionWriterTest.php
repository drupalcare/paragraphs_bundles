<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs_bundles_ai\Service\SectionWriterInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The three write rules, and the re-validation that makes them enforceable.
 *
 * WHY A MUTATION PROBE IS IN HERE. Every other test would still pass if the
 * writer trusted its caller — they all pass valid input. The probe below
 * (@see testTheWriterRevalidatesInsteadOfTrustingItsCaller) is the only test
 * that fails when the resolver call is removed from SectionWriter, and that
 * call is the entire safety model: a model can skip `pb_resolve_target`, so a
 * writer that assumed it ran would write into a field that rejects the bundle.
 *
 * @group paragraphs_bundles
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class SectionWriterTest extends KernelTestBase {

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
    'paragraphs_bundles_ai',
  ];

  /**
   * The service under test.
   */
  private SectionWriterInterface $writer;

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

    ParagraphsType::create(['id' => 'slideshow_bundle', 'label' => 'Slideshow'])->save();
    ParagraphsType::create(['id' => 'card_bundle', 'label' => 'Card'])->save();

    // One unrestricted field: the ordinary case, and the one every paragraph
    // field on a real site turns out to be.
    $this->contentTypeWithFields('simple_page', ['field_sections' => []]);

    // A field restricted to cards. The probe writes a slideshow at it.
    $this->contentTypeWithFields('card_page', [
      'field_card_strip' => ['card_bundle' => 'card_bundle'],
    ]);

    // A field that holds at most two. Every other fixture here is unlimited,
    // which is exactly why the append-past-the-limit bug survived the first
    // round of tests.
    $this->contentTypeWithFields('tight_page', ['field_two_max' => []], 2);

    $this->setUpCurrentUser(permissions: [
      'access content',
      'create simple_page content',
      'create card_page content',
      'create tight_page content',
      'edit any simple_page content',
      'edit any card_page content',
      'edit any tight_page content',
      'bypass node access',
    ]);

    $this->writer = $this->container->get(SectionWriterInterface::class);
  }

  /**
   * Rule 1 · A new page is created UNPUBLISHED, and says so.
   */
  public function testNewPageIsUnpublishedAndSaysSo(): void {
    $result = $this->writer->write('simple_page', 'field_sections', 'slideshow_bundle', 3, 'Spring launch');

    self::assertTrue($result->created, implode(' | ', $result->refusals));
    self::assertTrue($result->isNewNode);
    self::assertSame(3, $result->sections);
    self::assertNull($result->revisionId, 'A new node has no prior revision to return to.');

    $node = $this->reload((int) $result->nodeId);
    self::assertFalse($node->isPublished(), 'A machine-made draft must never be live.');
    self::assertSame('Spring launch', $node->label());
    self::assertCount(3, $node->get('field_sections')->getValue());
    self::assertSame(
      'slideshow_bundle',
      $node->get('field_sections')->entity->bundle(),
    );

    // The person is TOLD it is unpublished. Creating a hidden draft and not
    // saying so is how someone concludes the tool did nothing.
    self::assertStringContainsString('UNPUBLISHED', implode(' ', $result->notes));
  }

  /**
   * A new page with no title is refused rather than given an invented one.
   */
  public function testNewPageWithoutTitleIsRefused(): void {
    $before = $this->nodeCount();
    $result = $this->writer->write('simple_page', 'field_sections', 'slideshow_bundle', 1, '   ');

    self::assertFalse($result->created);
    self::assertStringContainsString('title is required', $result->refusals[0]);
    self::assertSame($before, $this->nodeCount(), 'A refusal must write nothing.');
  }

  /**
   * Rule 2 · Editing writes a NEW revision and reports its id.
   *
   * The reported id is asserted against the previous one, not merely for being
   * an integer: "I changed your page" without a way back is the worst thing
   * this module could say, and an id that never moved is exactly that.
   */
  public function testEditingWritesNewRevisionAndKeepsTheOldSections(): void {
    $node = $this->nodeWithSections('simple_page', 'field_sections', 1);
    $firstRevision = (int) $node->getRevisionId();

    $result = $this->writer->write(NULL, NULL, 'slideshow_bundle', 2, '', (int) $node->id());

    self::assertTrue($result->created, implode(' | ', $result->refusals));
    self::assertFalse($result->isNewNode);
    self::assertNotNull($result->revisionId);
    self::assertNotSame($firstRevision, $result->revisionId, 'The revision id must move.');

    $current = $this->reload((int) $node->id());
    self::assertCount(3, $current->get('field_sections')->getValue(), '1 existing + 2 added.');

    // The way back, actually walked rather than assumed.
    $storage = $this->container->get('entity_type.manager')->getStorage('node');
    $previous = $storage->loadRevision($firstRevision);
    self::assertInstanceOf(NodeInterface::class, $previous);
    self::assertCount(1, $previous->get('field_sections')->getValue());
  }

  /**
   * Rule 3 · Appending is the default; existing sections are not touched.
   */
  public function testAppendingIsTheDefaultAndKeepsWhatWasThere(): void {
    $node = $this->nodeWithSections('simple_page', 'field_sections', 2);
    $originalFirst = $node->get('field_sections')->getValue()[0]['target_id'];

    $result = $this->writer->write(NULL, NULL, 'slideshow_bundle', 1, '', (int) $node->id());

    self::assertTrue($result->created);
    $current = $this->reload((int) $node->id());
    $after = $current->get('field_sections')->getValue();

    self::assertCount(3, $after);
    self::assertSame($originalFirst, $after[0]['target_id'], 'New sections go AFTER the old ones.');
    self::assertStringContainsString('already there', implode(' ', $result->notes));
  }

  /**
   * Rule 3 · Replacing happens only when explicitly asked for.
   */
  public function testReplacingOnlyHappensWhenExplicitlyAskedFor(): void {
    $node = $this->nodeWithSections('simple_page', 'field_sections', 2);

    $result = $this->writer->write(NULL, NULL, 'slideshow_bundle', 1, '', (int) $node->id(), TRUE);

    self::assertTrue($result->created);
    self::assertCount(1, $this->reload((int) $node->id())->get('field_sections')->getValue());
    self::assertStringContainsString('Replaced 2', implode(' ', $result->notes));
  }

  /**
   * THE MUTATION PROBE. Remove the resolver call and only this test fails.
   *
   * A model that never called `pb_resolve_target` can hand the writer a bundle
   * the field rejects. Without the writer's own check this writes a slideshow
   * into a card-only field — Drupal will store it, because the constraint lives
   * in the form, not the schema. The refusal here IS the safeguard.
   */
  public function testTheWriterRevalidatesInsteadOfTrustingItsCaller(): void {
    $node = $this->nodeWithSections('card_page', 'field_card_strip', 0);
    $before = $this->paragraphCount();

    $result = $this->writer->write(NULL, NULL, 'slideshow_bundle', 1, '', (int) $node->id());

    self::assertFalse($result->created, 'A card-only field must refuse a slideshow.');
    self::assertStringContainsString('does not accept "slideshow_bundle"', $result->refusals[0]);
    self::assertCount(0, $this->reload((int) $node->id())->get('field_card_strip')->getValue());
    self::assertSame($before, $this->paragraphCount(), 'A refusal must leave no orphan paragraphs.');
  }

  /**
   * Appending must respect what the node ALREADY holds, not just the request.
   *
   * The resolver compares the number asked for against cardinality, which is
   * right for a new page and wrong for an existing one: a field holding 2 of 2
   * has room for none, however small the request. Nothing in Drupal stops the
   * over-write either — cardinality is enforced by the widget and by
   * validate(), and a programmatic save() runs neither, so the extra rows are
   * stored and the limit becomes a suggestion.
   */
  public function testAppendingCannotExceedWhatTheFieldStillHasRoomFor(): void {
    $node = $this->nodeWithSections('tight_page', 'field_two_max', 1);
    $paragraphsBefore = $this->paragraphCount();

    $result = $this->writer->write(NULL, NULL, 'slideshow_bundle', 2, '', (int) $node->id());

    self::assertFalse($result->created, 'A field of 2 holding 1 cannot take 2 more.');
    self::assertStringContainsString('room for 1 more', $result->refusals[0]);
    self::assertCount(1, $this->reload((int) $node->id())->get('field_two_max')->getValue());
    self::assertSame($paragraphsBefore, $this->paragraphCount(), 'No orphans from a refusal.');
  }

  /**
   * Filling a limited field exactly to its limit is allowed.
   *
   * Without this the refusal above could be passing because the writer refuses
   * every limited field.
   */
  public function testAppendingUpToTheLimitExactlyStillWorks(): void {
    $node = $this->nodeWithSections('tight_page', 'field_two_max', 1);

    $result = $this->writer->write(NULL, NULL, 'slideshow_bundle', 1, '', (int) $node->id());

    self::assertTrue($result->created, implode(' | ', $result->refusals));
    self::assertCount(2, $this->reload((int) $node->id())->get('field_two_max')->getValue());
  }

  /**
   * Replacing is measured against the limit itself, not the leftover room.
   *
   * Replace clears the field first, so a full field can still be replaced —
   * treating it as full would refuse the one operation that fixes it.
   */
  public function testReplacingFullFieldIsAllowed(): void {
    $node = $this->nodeWithSections('tight_page', 'field_two_max', 2);

    $result = $this->writer->write(NULL, NULL, 'slideshow_bundle', 2, '', (int) $node->id(), TRUE);

    self::assertTrue($result->created, implode(' | ', $result->refusals));
    self::assertCount(2, $this->reload((int) $node->id())->get('field_two_max')->getValue());
  }

  /**
   * A node id that does not exist is named, not swallowed.
   */
  public function testAnUnknownNodeIsRefusedByNumber(): void {
    $result = $this->writer->write(NULL, NULL, 'slideshow_bundle', 1, '', 4242);

    self::assertFalse($result->created);
    self::assertStringContainsString('no node 4242', $result->refusals[0]);
  }

  /**
   * The node decides its own type — a caller's contradicting guess is ignored.
   *
   * Taking a content type alongside a node id lets the two disagree, and only
   * one of them can be right. Here the caller is wrong on purpose.
   */
  public function testAnExistingNodeOverridesContradictoryContentType(): void {
    $node = $this->nodeWithSections('simple_page', 'field_sections', 0);

    $result = $this->writer->write('card_page', NULL, 'slideshow_bundle', 1, '', (int) $node->id());

    self::assertTrue($result->created, implode(' | ', $result->refusals));
    self::assertCount(1, $this->reload((int) $node->id())->get('field_sections')->getValue());
  }

  /**
   * Without edit access the answer is permission, and nothing is written.
   */
  public function testWithoutEditAccessNothingIsWritten(): void {
    $node = $this->nodeWithSections('simple_page', 'field_sections', 1);

    $this->setUpCurrentUser(permissions: ['access content']);
    $writer = $this->container->get(SectionWriterInterface::class);

    $result = $writer->write(NULL, NULL, 'slideshow_bundle', 1, '', (int) $node->id());

    self::assertFalse($result->created);
    self::assertStringContainsString('do not have permission', $result->refusals[0]);
    self::assertCount(1, $this->reload((int) $node->id())->get('field_sections')->getValue());
  }

  /**
   * A nonsense count becomes one section, not zero and not a crash.
   *
   * Models emit 0 and negatives. Creating nothing while reporting success is
   * worse than either refusing or doing the obvious thing; this does the
   * obvious thing.
   */
  public function testCountBelowOneStillCreatesOneSection(): void {
    $result = $this->writer->write('simple_page', 'field_sections', 'slideshow_bundle', 0, 'Zero');

    self::assertTrue($result->created);
    self::assertSame(1, $result->sections);
    self::assertCount(1, $this->reload((int) $result->nodeId)->get('field_sections')->getValue());
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
   * Creates a saved node carrying the given number of sections.
   *
   * @param string $type
   *   Node bundle.
   * @param string $field
   *   Paragraph field name.
   * @param int $sections
   *   How many paragraphs to attach.
   *
   * @return \Drupal\node\NodeInterface
   *   The saved node.
   */
  private function nodeWithSections(string $type, string $field, int $sections): NodeInterface {
    $values = [];

    for ($i = 0; $i < $sections; $i++) {
      $paragraph = Paragraph::create([
        'type' => $type === 'card_page' ? 'card_bundle' : 'slideshow_bundle',
      ]);
      $paragraph->save();
      $values[] = [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];
    }

    $node = Node::create([
      'type' => $type,
      'title' => 'Existing page',
      'status' => 1,
      $field => $values,
    ]);
    $node->save();

    return $node;
  }

  /**
   * How many nodes exist.
   *
   * @return int
   *   The count.
   */
  private function nodeCount(): int {
    return (int) $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

  /**
   * How many paragraphs exist.
   *
   * @return int
   *   The count.
   */
  private function paragraphCount(): int {
    return (int) $this->container->get('entity_type.manager')
      ->getStorage('paragraph')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

  /**
   * Creates a node type with the given paragraph fields.
   *
   * @param string $type
   *   Node bundle machine name.
   * @param array<string, array<string, string>> $fields
   *   Field name => target_bundles map. Empty means "any bundle", which is how
   *   Drupal itself stores an unrestricted field.
   * @param int $cardinality
   *   Field storage cardinality; -1 for unlimited.
   */
  private function contentTypeWithFields(string $type, array $fields, int $cardinality = -1): void {
    NodeType::create([
      'type' => $type,
      'name' => ucfirst(str_replace('_', ' ', $type)),
    ])->save();

    foreach ($fields as $name => $targets) {
      if (FieldStorageConfig::loadByName('node', $name) === NULL) {
        FieldStorageConfig::create([
          'field_name' => $name,
          'entity_type' => 'node',
          'type' => 'entity_reference_revisions',
          'cardinality' => $cardinality,
          'settings' => ['target_type' => 'paragraph'],
        ])->save();
      }

      FieldConfig::create([
        'field_name' => $name,
        'entity_type' => 'node',
        'bundle' => $type,
        'label' => ucfirst(str_replace(['field_', '_'], ['', ' '], $name)),
        'settings' => [
          'handler' => 'default:paragraph',
          'handler_settings' => ['target_bundles' => $targets],
        ],
      ])->save();
    }
  }

}
