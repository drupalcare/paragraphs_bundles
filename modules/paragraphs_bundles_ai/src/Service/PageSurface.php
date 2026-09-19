<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs_bundles_ai\Model\PageDecision;

/**
 * Default page surface.
 *
 * FIVE CONDITIONS, AND THE FOURTH IS THE ONE THAT LOOKS EASY.
 *
 * "Only where the paragraph field is displayed" reads like a check for a view
 * display named `full`. Measured on sts.local 2026-09-16: NOT ONE node type
 * there has an enabled `full` display — `pb_content` ships one with
 * `status: false`, and the others have none at all, so every page renders
 * through `default` with `full` as its mode. A check for a display called
 * `full` would therefore hide the assistant on every page of the site that
 * invented it.
 *
 * `EntityViewDisplay::collectRenderDisplay()` answers the question that was
 * actually being asked: which display renders this node in this view mode,
 * fallback already resolved. The component lookup then happens on the display
 * that genuinely draws the page.
 *
 * THE ORDER OF THE CHECKS IS DELIBERATE. Permission comes before anything that
 * would describe the site's configuration, so that someone without it learns
 * nothing about how the site is set up.
 */
final class PageSurface implements PageSurfaceInterface {

  /**
   * The permission that reveals the assistant.
   */
  private const string PERMISSION = 'use paragraphs bundles ai assistant';

  /**
   * Constructs the surface.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Finds the paragraph fields on the node's type.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Reads which AI provider the site chose, by name only.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Checked for the permission and for edit access.
   */
  public function __construct(
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function decide(NodeInterface $node, string $viewMode): PageDecision {
    if ($viewMode !== 'full') {
      return PageDecision::hide(
        'The assistant only appears on the full page, not in teasers, search results or listings.',
      );
    }

    if (!$this->currentUser->hasPermission(self::PERMISSION)) {
      return PageDecision::hide(
        'You do not have the "use the Paragraphs Bundles assistant" permission.',
      );
    }

    // Edit access, not view access: the assistant only ever adds sections to
    // this page, so someone who cannot edit it has nothing to gain from it.
    if (!$node->access('update', $this->currentUser)) {
      return PageDecision::hide(
        'You may not edit this page, so there is nothing the assistant could add to it.',
      );
    }

    $displayed = $this->displayedParagraphFields($node, $viewMode);

    if ($displayed === []) {
      $all = $this->paragraphFields($node);

      return PageDecision::hide($all === []
        ? sprintf(
          'The content type "%s" has no paragraph field, so there is nowhere here for the assistant to add sections.',
          $node->bundle(),
        )
        : sprintf(
          'The paragraph field %s is hidden in the display that renders this page, so a section added here would be invisible. Show it at Manage display.',
          implode(', ', array_keys($all)),
        ));
    }

    $providers = $this->configFactory->get('ai.settings')->get('default_providers');
    $chat = is_array($providers) ? ($providers['chat'] ?? NULL) : NULL;
    $provider = is_array($chat) ? ($chat['provider_id'] ?? '') : '';

    if (!is_string($provider) || trim($provider) === '') {
      return PageDecision::hide(
        'No default AI provider is set for chat. Choose one at Configuration › AI › Settings; this module never asks for an API key itself.',
      );
    }

    // EVERY displayed field, not the first of them. Choosing here would be
    // choosing on the person's behalf, silently, on a fact they can see and
    // the code cannot: which of their two paragraph fields they meant.
    return PageDecision::show($displayed);
  }

  /**
   * {@inheritdoc}
   */
  public function displayedParagraphFields(NodeInterface $node, string $viewMode): array {
    $fields = $this->paragraphFields($node);

    if ($fields === []) {
      return [];
    }

    // The display that RENDERS this node in this view mode, with the fallback
    // to `default` already resolved. See the class docblock: asking for a
    // display named `full` answers a different, wrong question.
    $display = EntityViewDisplay::collectRenderDisplay($node, $viewMode);
    $out = [];

    foreach ($fields as $name => $label) {
      if ($display->getComponent($name) !== NULL) {
        $out[$name] = $label;
      }
    }

    return $out;
  }

  /**
   * Every paragraph field on the node, displayed or not.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return array<string, string>
   *   Field machine name => label.
   */
  private function paragraphFields(NodeInterface $node): array {
    $out = [];

    foreach ($this->entityFieldManager->getFieldDefinitions('node', $node->bundle()) as $name => $definition) {
      // Both halves matter: entity_reference_revisions is used for things that
      // are not paragraphs, so the target type is checked rather than assumed.
      if ($definition->getType() !== 'entity_reference_revisions') {
        continue;
      }
      if (($definition->getSettings()['target_type'] ?? NULL) !== 'paragraph') {
        continue;
      }

      $out[(string) $name] = (string) $definition->getLabel();
    }

    return $out;
  }

}
