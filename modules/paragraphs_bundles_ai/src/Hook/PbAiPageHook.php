<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Drupal\paragraphs_bundles_ai\Form\PbAiPageForm;
use Drupal\paragraphs_bundles_ai\Service\PageSurfaceInterface;

/**
 * Puts the assistant at the bottom of a page a person is looking at.
 *
 * ONE PLACE, NOT TWO. It appears on the page being viewed and nowhere else —
 * not on the edit form, where a write behind an open form leaves the form
 * stale and the next save fighting it.
 *
 * THE CACHE CONTEXTS ARE NOT DECORATION. Whether this appears depends on the
 * person's permissions and on their access to this node. Without them, the
 * first anonymous visitor to warm the cache could be served a page carrying an
 * editor's assistant — or, just as bad, an editor could be served the
 * anonymous copy and conclude the feature is broken.
 */
final class PbAiPageHook {

  /**
   * Constructs the hook.
   *
   * @param \Drupal\paragraphs_bundles_ai\Service\PageSurfaceInterface $surface
   *   Decides whether the assistant belongs on this page.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   Builds the assistant's form.
   */
  public function __construct(
    private readonly PageSurfaceInterface $surface,
    private readonly FormBuilderInterface $formBuilder,
  ) {}

  /**
   * Adds the assistant to a full node view.
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
    $decision = $this->surface->decide($node, $viewMode);

    // The cacheability is attached whether it shows or not: a page that hides
    // the assistant because of who is looking must not be reused for someone
    // else.
    $build['#cache']['contexts'][] = 'user.permissions';
    $build['#cache']['contexts'][] = 'user.node_grants:update';
    $build['#cache']['tags'][] = 'config:ai.settings';

    if (!$decision->show) {
      return;
    }

    $build['paragraphs_bundles_ai_assistant'] = [
      '#type' => 'container',
      '#weight' => 1000,
      '#attributes' => ['class' => ['pb-ai']],
      'form' => $this->formBuilder->getForm(PbAiPageForm::class, $node, $decision->field),
    ];
  }

}
