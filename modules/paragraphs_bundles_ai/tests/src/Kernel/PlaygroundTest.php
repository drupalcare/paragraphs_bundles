<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs_bundles_ai\Hook\PbPlaygroundHook;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The playground content type keeps the promise in its own name.
 *
 * THE ONE THAT MATTERS is that "never published" is enforced on SAVE rather
 * than merely defaulted. A default covers the edit form and nothing else — the
 * bulk "Publish content" action, another module, a migration and a REST call
 * all reach save() without consulting one. So the tests here publish a
 * playground page the way those callers would, by setting the field directly,
 * and assert it comes back unpublished anyway.
 *
 * @group paragraphs_bundles
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PlaygroundTest extends KernelTestBase {

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
    // The parent is a hard dependency in info.yml, and this module's services
    // now reference one of its services. Installing this module without it is
    // a configuration no site can have, and it fails at container compile.
    // drupal/ai too: SectionDrafter takes its provider manager, so the
    // container cannot compile without it.
    'key',
    'ai',
    'paragraphs_bundles',
    'paragraphs_bundles_ai',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    // Saving an EXISTING node rewrites its access grants, and that table is
    // not part of the node entity schema. Without it the first save passes and
    // the second throws — which is precisely the case these tests exist for,
    // since publishing an existing page is what the bulk action does.
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'field', 'filter', 'node']);

    // THE SHIPPED TYPE IS WHAT THESE TESTS RUN AGAINST. Installing the module
    // installs its config/optional, so pb_playground already exists here —
    // measured 2026-09-15, when creating it a second time threw "'node_type'
    // entity with ID 'pb_playground' already exists". Creating it only when
    // absent keeps that true and still works if a future environment does not
    // install optional config.
    self::assertNotNull(
      NodeType::load(PbPlaygroundHook::BUNDLE),
      'The module ships pb_playground as optional config and installing it brings the type.',
    );

    if (NodeType::load('article') === NULL) {
      NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    }
  }

  /**
   * A playground page asked to be published is saved unpublished.
   */
  public function testPlaygroundPageCannotBePublished(): void {
    $node = Node::create([
      'type' => PbPlaygroundHook::BUNDLE,
      'title' => 'Trying a hero',
      'status' => 1,
    ]);
    $node->save();

    self::assertFalse($node->isPublished(), 'Publishing was requested at creation and refused.');

    // The bulk action's shape: load it, publish it, save it.
    $node->setPublished();
    $node->save();

    self::assertFalse($node->isPublished(), 'Publishing an existing playground page is refused too.');

    $reloaded = Node::load((int) $node->id());
    self::assertNotNull($reloaded);
    self::assertFalse($reloaded->isPublished(), 'And it is unpublished in storage, not just in memory.');
  }

  /**
   * Every other content type is left entirely alone.
   *
   * Without this the rule above could be a site-wide publishing bug and the
   * first test would still pass.
   */
  public function testOtherContentTypesAreUntouched(): void {
    $node = Node::create(['type' => 'article', 'title' => 'A real page', 'status' => 1]);
    $node->save();

    self::assertTrue($node->isPublished());
  }

  /**
   * Viewing someone else's playground page needs the permission.
   *
   * Core lets a person see their OWN unpublished content and stops there, so
   * without this grant a shared library lists pages nobody else can open.
   */
  public function testViewingAnotherPersonsPageNeedsThePermission(): void {
    $node = Node::create([
      'type' => PbPlaygroundHook::BUNDLE,
      'title' => 'Someone else’s experiment',
    ]);
    $node->save();

    $hook = $this->container->get(PbPlaygroundHook::class);

    $permitted = $this->createMock(AccountInterface::class);
    $permitted->method('hasPermission')
      ->willReturnCallback(static fn(string $p): bool => $p === PbPlaygroundHook::PERMISSION);

    $stranger = $this->createMock(AccountInterface::class);
    $stranger->method('hasPermission')->willReturn(FALSE);

    self::assertTrue($hook->nodeAccess($node, 'view', $permitted)->isAllowed());
    self::assertFalse($hook->nodeAccess($node, 'view', $stranger)->isAllowed());
  }

  /**
   * The permission grants viewing only, and only on the playground type.
   *
   * A grant that leaked into update or delete would hand every holder the
   * right to edit other people's pages.
   */
  public function testThePermissionGrantsNothingElse(): void {
    $playground = Node::create([
      'type' => PbPlaygroundHook::BUNDLE,
      'title' => 'Mine',
    ]);
    $playground->save();

    $article = Node::create(['type' => 'article', 'title' => 'Real']);
    $article->save();

    $hook = $this->container->get(PbPlaygroundHook::class);

    $permitted = $this->createMock(AccountInterface::class);
    $permitted->method('hasPermission')->willReturn(TRUE);

    self::assertFalse(
      $hook->nodeAccess($playground, 'update', $permitted)->isAllowed(),
      'Viewing is granted; editing is not.',
    );
    self::assertFalse(
      $hook->nodeAccess($playground, 'delete', $permitted)->isAllowed(),
    );
    self::assertTrue(
      $hook->nodeAccess($article, 'view', $permitted)->isNeutral(),
      'Another content type is none of this hook’s business.',
    );
  }

  /**
   * The shipped config defaults to unpublished, as well as forcing it.
   *
   * Belt and braces on purpose: the default is what an editor sees on the form,
   * and the presave rule is what makes the name true. Losing either silently
   * would leave the other looking like it works.
   */
  public function testShippedConfigDefaultsToUnpublished(): void {
    $file = __DIR__ . '/../../../config/optional/core.base_field_override.node.pb_playground.status.yml';

    self::assertFileExists($file);

    $config = Yaml::decode((string) file_get_contents($file));

    self::assertIsArray($config);
    self::assertSame('status', $config['field_name']);
    self::assertSame(PbPlaygroundHook::BUNDLE, $config['bundle']);
    self::assertSame(0, $config['default_value'][0]['value'], 'New playground pages start unpublished.');
  }

}
