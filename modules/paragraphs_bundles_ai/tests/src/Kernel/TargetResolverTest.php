<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs_bundles_ai\Service\TargetResolverInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The seven preconditions, each asserted by the sentence a person receives.
 *
 * WHY THE SENTENCES ARE ASSERTED AND NOT JUST THE BOOLEAN. The refusal IS the
 * feature. A resolver that returns FALSE tells an assistant nothing it can
 * relay; one that says which fields exist turns a dead end into the next
 * question. Asserting only `resolved === FALSE` would let the wording rot
 * silently, and the wording is the part a person reads.
 *
 * @group paragraphs_bundles
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class TargetResolverTest extends KernelTestBase {

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
  private TargetResolverInterface $resolver;

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

    // A type with ONE paragraph field, accepting anything.
    $this->contentTypeWithFields('simple_page', ['field_sections' => []]);

    // A type with TWO, one of which is restricted to cards only. Two fields is
    // the case the resolver exists for; the restriction is precondition 4.
    $this->contentTypeWithFields('rich_page', [
      'field_body_sections' => [],
      'field_card_strip' => ['card_bundle' => 'card_bundle'],
    ]);

    // A type with none at all — a different failure from a typo.
    NodeType::create(['type' => 'plain_page', 'name' => 'Plain page'])->save();

    // 'access content' is required for node create access; without it every
    // case below would fail on permission rather than on what it tests.
    $this->setUpCurrentUser(permissions: [
      'access content',
      'create simple_page content',
      'create rich_page content',
      'create plain_page content',
    ]);

    $this->resolver = $this->container->get(TargetResolverInterface::class);
  }

  /**
   * Control: a fully specified, valid request resolves.
   *
   * Without this, every refusal assertion below could be passing because the
   * resolver refuses everything.
   */
  public function testValidRequestResolves(): void {
    $result = $this->resolver->resolve('simple_page', 'field_sections', 'slideshow_bundle', 3);

    self::assertTrue($result->resolved, implode(' | ', $result->questions));
    self::assertSame('simple_page', $result->contentType);
    self::assertSame('field_sections', $result->field);
    // The bundle comes back too. `resolve_target` tells the model to call
    // `create_sections` "with exactly these values", and the bundle is one it
    // requires — leaving it out of the answer is where a guessed machine name
    // gets substituted for a checked one.
    self::assertSame('slideshow_bundle', $result->bundle);
    self::assertSame([], $result->questions);
  }

  /**
   * Precondition 1 · No content type named — the answer lists the ones that would work.
   */
  public function testWithoutContentTypeItAsksAndListsTheUsableOnes(): void {
    $result = $this->resolver->resolve(NULL, NULL, 'slideshow_bundle');

    self::assertFalse($result->resolved);
    self::assertStringContainsString('Which content type?', $result->questions[0]);
    self::assertStringContainsString('Simple page', $result->questions[0]);
    self::assertStringContainsString('Rich page', $result->questions[0]);
    // The type with no paragraph field must not be offered: suggesting it
    // would send the person down a road that cannot work.
    self::assertStringNotContainsString('Plain page', $result->questions[0]);
  }

  /**
   * Precondition 1 · A content type that does not exist is a typo, and says so.
   */
  public function testAnUnknownContentTypeIsNamedAsUnknown(): void {
    $result = $this->resolver->resolve('simpl_page', NULL, 'slideshow_bundle');

    self::assertFalse($result->resolved);
    self::assertStringContainsString('"simpl_page" is not a content type', $result->questions[0]);
  }

  /**
   * Precondition 2 · A real content type with no paragraph field gets a DIFFERENT sentence.
   *
   * The distinction is the point: a typo is fixed by retyping, a type with no
   * paragraph field is fixed by a site builder adding one. Collapsing both
   * into "cannot use that" would hide which.
   */
  public function testContentTypeWithoutParagraphFieldsSaysSo(): void {
    $result = $this->resolver->resolve('plain_page', NULL, 'slideshow_bundle');

    self::assertFalse($result->resolved);
    self::assertStringContainsString('has no paragraph field', $result->questions[0]);
    self::assertStringNotContainsString('is not a content type', $result->questions[0]);
  }

  /**
   * Precondition 3 · One paragraph field is chosen silently; two are asked about.
   */
  public function testSingleFieldIsChosenButTwoAreAskedAbout(): void {
    $single = $this->resolver->resolve('simple_page', NULL, 'slideshow_bundle');
    self::assertTrue($single->resolved, 'One candidate needs no question.');
    self::assertSame('field_sections', $single->field);

    $several = $this->resolver->resolve('rich_page', NULL, 'card_bundle');
    self::assertFalse($several->resolved);
    self::assertStringContainsString('Which field?', $several->questions[0]);
    self::assertStringContainsString('2 paragraph fields', $several->questions[0]);
  }

  /**
   * Precondition 3 · A field name that is not on the type lists the ones that are.
   */
  public function testAnUnknownFieldNameListsTheRealOnes(): void {
    $result = $this->resolver->resolve('simple_page', 'field_nope', 'slideshow_bundle');

    self::assertFalse($result->resolved);
    self::assertStringContainsString('"field_nope" is not a paragraph field', $result->questions[0]);
    self::assertStringContainsString('Available:', $result->questions[0]);
  }

  /**
   * Precondition 4 · A field that does not accept the bundle says what it does accept.
   *
   * The check people forget. `field_card_strip` is restricted to cards, so a
   * slideshow must be refused even though both the field and the bundle exist
   * and the user is permitted.
   */
  public function testFieldThatRejectsTheBundleNamesWhatItAccepts(): void {
    $result = $this->resolver->resolve('rich_page', 'field_card_strip', 'slideshow_bundle');

    self::assertFalse($result->resolved);
    self::assertStringContainsString('does not accept "slideshow_bundle"', $result->questions[0]);
    self::assertStringContainsString('Card', $result->questions[0]);
  }

  /**
   * Precondition 4 · An UNRESTRICTED field accepts anything — the empty-means-all trap.
   *
   * Drupal stores "any bundle allowed" as an EMPTY target_bundles, not as a
   * list of every bundle. A naive in_array() check rejects everything on a
   * field that accepts everything, and measured on sts.local 2026-09-04 every
   * paragraph field on that site is exactly this case — so the naive version
   * would have failed on every real input while passing a fixture that set the
   * list explicitly. This is that fixture, deliberately left empty.
   */
  public function testAnUnrestrictedFieldAcceptsAnyBundle(): void {
    foreach (['slideshow_bundle', 'card_bundle'] as $bundle) {
      $result = $this->resolver->resolve('simple_page', 'field_sections', $bundle);
      self::assertTrue(
        $result->resolved,
        sprintf('%s should be allowed: %s', $bundle, implode(' | ', $result->questions)),
      );
    }
  }

  /**
   * Precondition 6 · A bundle whose module is not installed is named as absent.
   */
  public function testAnUnknownBundleIsNamedAsAbsent(): void {
    $result = $this->resolver->resolve('simple_page', 'field_sections', 'no_such_bundle');

    self::assertFalse($result->resolved);
    self::assertStringContainsString('no paragraph type "no_such_bundle"', $result->questions[0]);
    self::assertStringContainsString('module may not be enabled', $result->questions[0]);
  }

  /**
   * Precondition 6 · With no bundle named, "ready" would be a false green.
   *
   * Every other precondition is skipped rather than failed when its input is
   * absent, and for the bundle that is wrong: resolving successfully tells the
   * assistant to go ahead without ever establishing WHAT to create, which is
   * the one thing the person actually asked for.
   */
  public function testWithoutBundleItAsksWhichSection(): void {
    $result = $this->resolver->resolve('simple_page', 'field_sections', NULL);

    self::assertFalse($result->resolved, 'Ready to create — what, exactly?');
    self::assertStringContainsString('Which section', $result->questions[0]);
  }

  /**
   * Precondition 7 · Without create permission the answer is permission, not fields.
   *
   * Checked before the field questions on purpose: telling someone which field
   * to choose and then refusing on permission wastes their time.
   */
  public function testWithoutPermissionThePermissionIsTheAnswer(): void {
    $this->setUpCurrentUser(permissions: ['access content']);
    $resolver = $this->container->get(TargetResolverInterface::class);

    $result = $resolver->resolve('simple_page', 'field_sections', 'slideshow_bundle');

    self::assertFalse($result->resolved);
    self::assertStringContainsString('do not have permission', $result->questions[0]);
  }

  /**
   * Every blocker is returned at once, not one per call.
   *
   * Interrogating one item per turn is what makes a conversational tool
   * exhausting. Two independent problems here — an unknown bundle and a field
   * that must be chosen — must both come back from a single call.
   */
  public function testAllBlockersComeBackTogether(): void {
    $result = $this->resolver->resolve('rich_page', 'field_body_sections', 'no_such_bundle', 99);

    self::assertFalse($result->resolved);
    self::assertGreaterThanOrEqual(1, count($result->questions));
    self::assertStringContainsString('no paragraph type', implode(' ', $result->questions));
  }

  /**
   * Creates a node type with the given paragraph fields.
   *
   * @param string $type
   *   Node bundle machine name.
   * @param array<string, array<string, string>> $fields
   *   Field name => target_bundles map. An empty map means "any bundle",
   *   which is how Drupal itself stores an unrestricted field.
   */
  private function contentTypeWithFields(string $type, array $fields): void {
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
          'cardinality' => -1,
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
