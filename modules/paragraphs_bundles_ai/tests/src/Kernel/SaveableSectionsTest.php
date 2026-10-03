<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_bundles_ai\Service\BundleProfilerInterface;
use Drupal\paragraphs_bundles_ai\Service\ChildSlotsInterface;
use Drupal\paragraphs_bundles_ai\Service\SectionComposerInterface;
use Drupal\Tests\paragraphs_bundles_ai\Traits\AiCoreSupportTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Every section this tool can add can be SAVED AGAIN from the edit form.
 *
 * Maintainer, 2026-09-28: "if we dont do that and creat then the user come
 * and edit and try to save, it will not save and they will think the bundle is
 * broken, please you must validate this". Measured the same day on sts.local:
 * 22 sections written by the tool failed the edit form's validation — required
 * slots left empty and link items with no address.
 *
 * For every bundle that ships a brief, one section is composed from the
 * least a model could send — a word in each writable field, one item in each
 * slot — and every paragraph written is then validated as the edit form
 * validates it. A new required field the tool does not fill turns this red.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles_ai')]
#[RunTestsInSeparateProcesses]
final class SaveableSectionsTest extends KernelTestBase {
  use AiCoreSupportTrait;

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = ['system', 'user'];

  /**
   * The PB modules whose bundles ship a brief, plus the page type.
   */
  private const MODULES = [
    'node',
    'paragraph_bundle_content',
    'paragraph_bundle_3d_carousel',
    'paragraph_bundle_3d_flip_box',
    'paragraph_bundle_accordion',
    'paragraph_bundle_alert',
    'paragraph_bundle_card',
    'paragraph_bundle_carousel',
    'paragraph_bundle_grid',
    'paragraph_bundle_hero',
    'paragraph_bundle_icon',
    'paragraph_bundle_image',
    'paragraph_bundle_image_background',
    'paragraph_bundle_image_grid',
    'paragraph_bundle_image_overlay',
    'paragraph_bundle_layout',
    'paragraph_bundle_link',
    'paragraph_bundle_modal',
    'paragraph_bundle_parallax',
    'paragraph_bundle_slideshow',
    'paragraph_bundle_tabs',
    'paragraphs_bundles_ai',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->skipUnlessCoreSupportsAi();
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system']);
    $this->container->get('module_installer')->install(self::MODULES);
    $this->setUpCurrentUser([], [], TRUE);
  }

  /**
   * One section of every briefed bundle; every paragraph written validates.
   */
  public function testEverySectionTheToolWritesCanBeSavedAgain(): void {
    $composer = $this->container->get(SectionComposerInterface::class);
    $briefs = $this->config('paragraphs_bundles_ai.briefs')->get('briefs');
    self::assertIsArray($briefs);

    $node = Node::create(['type' => 'pb_content', 'title' => 'Saveable sections', 'status' => 0]);
    $node->save();
    $nid = (int) $node->id();

    $refused = [];
    $unsaveable = [];

    foreach (array_keys($briefs) as $bundle) {
      $bundle = (string) $bundle;
      $result = $composer->compose(NULL, 'pb_content_type_body', $bundle, $this->words($bundle), $this->oneOfEach($bundle, TRUE), NULL, '', $nid, []);

      if (!$result->created) {
        $refused[$bundle] = implode(' | ', array_map('strval', $result->refusals));
      }
    }

    $node = Node::load($nid);
    self::assertNotNull($node);

    foreach ($node->get('pb_content_type_body')->referencedEntities() as $section) {
      $unsaveable = array_merge($unsaveable, $this->problems($section));
    }

    self::assertSame([], $refused, 'Every briefed bundle can be added with one item in each slot.');
    self::assertSame([], $unsaveable, 'Every paragraph the tool wrote passes the edit form\'s validation.');
    self::assertCount(count($briefs), $node->get('pb_content_type_body'));
  }

  /**
   * A required slot left empty is refused, and nothing is written.
   */
  public function testAnEmptyRequiredSlotIsRefusedNotSaved(): void {
    $node = Node::create(['type' => 'pb_content', 'title' => 'Empty slot', 'status' => 0]);
    $node->save();

    $result = $this->container->get(SectionComposerInterface::class)
      ->compose(NULL, 'pb_content_type_body', 'accordion_bundle', ['pb_content_title' => 'Rose care'], [], NULL, '', (int) $node->id());

    self::assertFalse($result->created);
    self::assertStringContainsString('needs at least one', implode(' ', array_map('strval', $result->refusals)));
    self::assertCount(0, Node::load((int) $node->id())?->get('pb_content_type_body') ?? []);
  }

  /**
   * A link with no address is saved as "no link", and the person is told.
   */
  public function testLinksWithoutAnAddressStillSave(): void {
    $node = Node::create(['type' => 'pb_content', 'title' => 'Links', 'status' => 0]);
    $node->save();
    $item = static fn(string $title): array => [
      'bundle' => 'link_section_bundle',
      'values' => ['pb_content_link_section_title' => $title],
    ];
    $links = ['pb_link_section_body' => [$item('Shop'), $item('Contact')]];

    $result = $this->container->get(SectionComposerInterface::class)
      ->compose(NULL, 'pb_content_type_body', 'link_bundle', ['pb_content_title' => 'Get started'], $links, NULL, '', (int) $node->id());

    self::assertTrue($result->created, implode(' | ', array_map('strval', $result->refusals)));
    self::assertStringContainsString('2 links were saved without one', implode(' ', array_map('strval', $result->notes)));

    $section = Node::load((int) $node->id())?->get('pb_content_type_body')->entity;
    self::assertInstanceOf(ParagraphInterface::class, $section);
    self::assertSame([], $this->problems($section));
    self::assertSame('route:<nolink>', $section->get('pb_link_section_body')->entity?->get('pb_content_link_section_url')->uri);
  }

  /**
   * A word in each field the model may write, as the drafter offers them.
   *
   * @return array<string, string>
   *   Field name => value.
   */
  private function words(string $bundle): array {
    $profile = $this->container->get(BundleProfilerInterface::class)->profile($bundle);
    $values = [];

    // What the drafter offers the model: content fields that take words,
    // with a list field answered from its own list.
    foreach ($profile?->contentFields ?? [] as $name) {
      $fact = $profile->field($name);

      if ($fact === NULL || !$fact->isWords()) {
        continue;
      }

      $values[$name] = $fact->allowedValues === [] ? 'Roses' : (string) (array_values($fact->allowedValues)[0] ?? '');
    }

    return $values;
  }

  /**
   * One item in each of a bundle's slots, the slot's default kind.
   *
   * @return array<string, list<array<string, mixed>>>
   *   Slot field => one child.
   */
  private function oneOfEach(string $bundle, bool $withInner): array {
    $children = [];

    foreach ($this->container->get(ChildSlotsInterface::class)->slotsFor($bundle) as $slot) {
      $kind = $slot->default();
      $child = ['bundle' => $kind, 'values' => $this->words($kind)];

      if ($withInner && ($inner = $this->oneOfEach($kind, FALSE)) !== []) {
        $child['children'] = $inner;
      }

      $children[$slot->field] = [$child];
    }

    return $children;
  }

  /**
   * Validation problems in a section and every section inside it.
   *
   * @return list<string>
   *   "bundle field: message" lines.
   */
  private function problems(ParagraphInterface $paragraph): array {
    $out = [];

    foreach ($paragraph->validate() as $violation) {
      $out[] = sprintf('%s %s: %s', $paragraph->bundle(), $violation->getPropertyPath(), strip_tags((string) $violation->getMessage()));
    }

    foreach ($paragraph->getFields() as $items) {
      if ($items->getFieldDefinition()->getType() !== 'entity_reference_revisions') {
        continue;
      }

      foreach ($items->referencedEntities() as $child) {
        if ($child instanceof ParagraphInterface) {
          $out = array_merge($out, $this->problems($child));
        }
      }
    }

    return $out;
  }

}
