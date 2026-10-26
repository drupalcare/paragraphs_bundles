<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Card text section "Link display": title link (default) or a button.
 *
 * The default must keep the markup every existing card already renders: the
 * title wrapped in the link and no button. "Button under the body" prints the
 * link after the body, labelled with the link text, and leaves the title plain.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbCardLinkModeRenderTest extends KernelTestBase {

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
    'file',
    'image',
    'link',
    'options',
    'media',
    'node',
    'entity_reference_revisions',
    'paragraphs',
    'field_group',
    'paragraphs_bundles',
    'paragraph_bundle_card',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installConfig(['system', 'filter']);
    $this->container->get('config.installer')->installOptionalConfig();
  }

  /**
   * Renders one card text section.
   *
   * @param array<string, mixed> $values
   *   Extra field values.
   */
  private function renderCard(array $values): string {
    $paragraph = Paragraph::create([
      'type' => 'card_text_section_bundle',
      'pb_content_title' => 'Case study',
      'pb_content_body' => ['value' => '<p>Body text.</p>', 'format' => 'plain_text'],
    ] + $values);
    $paragraph->save();
    $build = $this->container->get('entity_type.manager')->getViewBuilder('paragraph')->view($paragraph);
    return (string) $this->container->get('renderer')->renderRoot($build);
  }

  /**
   * Unset and 'title' both link the title, with no button.
   */
  public function testDefaultLinksTheTitle(): void {
    foreach ([[], ['pb_display_card_link_mode' => 'title']] as $extra) {
      $html = $this->renderCard(['pb_content_link' => ['uri' => 'https://example.com/case', 'title' => 'Read the case']] + $extra);
      self::assertStringContainsString('class="pb-ui-link"', $html);
      self::assertStringNotContainsString('pb-card-text-section__button', $html);
    }
  }

  /**
   * The 'button' mode prints the link under the body, labelled by its text.
   */
  public function testButtonUnderTheBody(): void {
    $html = $this->renderCard([
      'pb_content_link' => ['uri' => 'https://example.com/case', 'title' => 'Read the case'],
      'pb_display_card_link_mode' => 'button',
    ]);
    self::assertStringNotContainsString('class="pb-ui-link"', $html);
    self::assertMatchesRegularExpression('#<a href="https://example.com/case" class="button pb-card-text-section__button">Read the case</a>#', $html);
    self::assertGreaterThan(strpos($html, 'Body text.'), strpos($html, 'pb-card-text-section__button'), 'The button follows the body.');

    // No link text: a translatable fallback that still names the card.
    $html = $this->renderCard([
      'pb_content_link' => ['uri' => 'https://example.com/case'],
      'pb_display_card_link_mode' => 'button',
    ]);
    self::assertStringContainsString('Read more<span class="visually-hidden">: Case study</span>', $html);
  }

}
