<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs_bundles_ai\Service\ChatSurfaceInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Every reason the chat bar does not appear, asserted as a sentence.
 *
 * WHY THE REASONS ARE ASSERTED AND NOT JUST THE BOOLEAN. Four invisible
 * preconditions gate this bar, and the failure mode they produce is the worst
 * one in the catalogue: a person enables the module, opens a node form, sees
 * nothing, and has no way to learn which of the four was missing. A test that
 * only checked `show === FALSE` would let all four collapse into one useless
 * message and still pass.
 *
 * NOTE ON WHAT IS NOT COVERED HERE. `drupal/ai` is not a dependency of this
 * test — ai_chatbot and ai_assistant_api are absent from the container on
 * purpose, because "the module is not installed" is the state most sites are
 * in and the one that must fail quietly. The showing path is proved on
 * sts.local, where both modules are real. That gap is deliberate and stated
 * rather than hidden behind a mock that would prove only that the mock works.
 *
 * @group paragraphs_bundles
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class ChatSurfaceTest extends KernelTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'paragraphs_bundles_ai']);
  }

  /**
   * Without the permission, the answer is the permission — and nothing else.
   *
   * Checked first on purpose. Someone who may not use the assistant does not
   * need to be told about the site's AI configuration, and reporting a missing
   * module to them would leak it.
   */
  public function testWithoutPermissionThePermissionIsTheReason(): void {
    $this->setUpCurrentUser(permissions: []);

    $decision = $this->container->get(ChatSurfaceInterface::class)->decide();

    self::assertFalse($decision->show);
    self::assertStringContainsString('permission', $decision->reason);
    self::assertStringNotContainsString('ai_chatbot', $decision->reason);
  }

  /**
   * With the permission but no chatbot module, the module is named.
   *
   * This is the state of every site that enables this module without the rest
   * of drupal/ai, and it must be quiet: no error, no broken block, one
   * sentence naming the module to install.
   */
  public function testWithoutTheChatbotModuleTheModuleIsNamed(): void {
    $this->setUpCurrentUser(permissions: [ChatSurfaceInterface::PERMISSION]);

    $decision = $this->container->get(ChatSurfaceInterface::class)->decide();

    self::assertFalse($decision->show);
    self::assertStringContainsString('ai_chatbot', $decision->reason);
  }

  /**
   * A hidden surface builds nothing at all — not an empty wrapper.
   *
   * An empty container still renders a div, and a div with a class on a node
   * form is something a theme can style, a test can find, and a person can be
   * confused by. Nothing means nothing.
   */
  public function testHiddenSurfaceBuildsAnEmptyArray(): void {
    $this->setUpCurrentUser(permissions: [ChatSurfaceInterface::PERMISSION]);

    self::assertSame([], $this->container->get(ChatSurfaceInterface::class)->build());
  }

  /**
   * The installed default is off, and off is a legitimate resting state.
   *
   * No assistant can be shipped — an ai_assistant names a provider and model
   * that exist on one site only — so the setting installs empty. This asserts
   * that emptiness is the shipped value rather than an accident of the test.
   */
  public function testTheShippedSettingIsOff(): void {
    self::assertSame(
      '',
      $this->config('paragraphs_bundles_ai.settings')->get('assistant'),
    );
  }

  /**
   * Every reason is a complete sentence, not a code.
   *
   * These strings are read by a person on the settings page, in the middle of
   * wondering why nothing happened. A fragment like "missing module" would
   * pass a `show === FALSE` test and help nobody.
   */
  public function testEveryReasonReadsAsSentence(): void {
    foreach ([[], [ChatSurfaceInterface::PERMISSION]] as $permissions) {
      $this->setUpCurrentUser(permissions: $permissions);
      $reason = $this->container->get(ChatSurfaceInterface::class)->decide()->reason;

      self::assertNotSame('', $reason);
      self::assertStringEndsWith('.', $reason, 'A reason is a sentence: ' . $reason);
      self::assertGreaterThan(30, strlen($reason), 'Too terse to act on: ' . $reason);
    }
  }

}
