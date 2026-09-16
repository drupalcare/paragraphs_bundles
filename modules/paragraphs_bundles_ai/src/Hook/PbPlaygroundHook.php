<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;

/**
 * Makes the playground content type behave the way its name promises.
 *
 * THREE THINGS, AND THE FIRST IS THE ONLY ONE THAT MAKES THE NAME TRUE.
 *
 * 1. A playground page is forced UNPUBLISHED on every save. The shipped
 *    default value covers the edit form and nothing else: the bulk "Publish
 *    content" action on the content list, another module, a migration and a
 *    REST call all reach save() without ever consulting a default. A content
 *    type called "never published" whose only guard is a default is a
 *    promise, not a rule — and it would be found out by whoever trusted it.
 *
 * 2. A permission to view somebody else's playground page. Core lets a person
 *    see their OWN unpublished content and stops there, which is right for
 *    drafts and wrong for a shared shelf of examples: without this the
 *    library lists pages that 404 for everyone but their author.
 *
 * 3. A banner on the page itself, because a page that cannot be published is
 *    a surprise worth spending one sentence on.
 *
 * WHY NOT CONTENT MODERATION. A workflow with no published state would do the
 * same job in config, and it drags another module into an optional submodule
 * and complicates every save the assistant makes. The rule here is five lines
 * and testable in one assertion.
 */
final class PbPlaygroundHook {

  use StringTranslationTrait;

  /**
   * The content type this all applies to.
   */
  public const string BUNDLE = 'pb_playground';

  /**
   * The permission that reveals other people's playground pages.
   */
  public const string PERMISSION = 'view any pb playground page';

  /**
   * Forces every playground page unpublished, whatever asked otherwise.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node being saved.
   */
  #[Hook('node_presave')]
  public function nodePresave(NodeInterface $node): void {
    if ($node->bundle() !== self::BUNDLE) {
      return;
    }

    if ($node->isPublished()) {
      $node->setUnpublished();
    }
  }

  /**
   * Lets a permitted person view a playground page they do not own.
   *
   * Only ever GRANTS, and only for viewing. Returning forbidden here would
   * override the site's own access rules, which is not this module's business
   * — a guest on someone else's site does not take away access nobody asked
   * it to police.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $operation
   *   The operation.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The person asking.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Allowed with the permission, neutral otherwise.
   */
  #[Hook('node_access')]
  public function nodeAccess(NodeInterface $node, string $operation, AccountInterface $account): AccessResultInterface {
    if ($node->bundle() !== self::BUNDLE || $operation !== 'view') {
      return AccessResult::neutral();
    }

    return AccessResult::allowedIfHasPermission($account, self::PERMISSION)
      ->addCacheableDependency($node);
  }

  /**
   * Says on the page itself that it can never be published.
   *
   * @param array<string, mixed> $build
   *   The render array.
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The view display.
   * @param string $viewMode
   *   The view mode.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, NodeInterface $node, EntityViewDisplayInterface $display, string $viewMode): void {
    if ($node->bundle() !== self::BUNDLE || $viewMode !== 'full') {
      return;
    }

    $build['pb_playground_notice'] = [
      '#type' => 'container',
      '#weight' => -100,
      '#attributes' => ['class' => ['pb-playground-notice']],
      'text' => [
        '#markup' => $this->t('Playground page — this can never be published. Copy a section from here to a real page when you are happy with it.'),
      ],
    ];
  }

}
