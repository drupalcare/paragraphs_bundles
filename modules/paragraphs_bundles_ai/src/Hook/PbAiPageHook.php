<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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

  // REQUIRED BY THE HANDLE'S LABEL. This class is a plain service, not a form
  // or a controller, so it has no t() of its own — and `$this->t()` without
  // this trait is an undefined method that `php -l` cannot see. It would not
  // have surfaced in testing either: decide() refuses at the provider check
  // before the button is built, so nothing on a site without a configured
  // provider ever reaches the line that would have thrown.
  use StringTranslationTrait;

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
    // Read, narrow, write back. $build is array<string, mixed>, so
    // $build['#cache'] is mixed and appending straight into it indexes a value
    // of unknown type. That is harmless while every caller is core passing a
    // well-formed render array, and a fatal the day one is not — and this hook
    // runs on every full node view on the site.
    $cache = is_array($build['#cache'] ?? NULL) ? $build['#cache'] : [];
    $contexts = is_array($cache['contexts'] ?? NULL) ? $cache['contexts'] : [];
    $tags = is_array($cache['tags'] ?? NULL) ? $cache['tags'] : [];

    $contexts[] = 'user.permissions';
    $contexts[] = 'user.node_grants:update';
    $tags[] = 'config:ai.settings';

    $cache['contexts'] = $contexts;
    $cache['tags'] = $tags;
    $build['#cache'] = $cache;

    if (!$decision->show) {
      return;
    }

    $build['paragraphs_bundles_ai_assistant'] = [
      '#type' => 'container',
      '#weight' => 1000,
      '#attributes' => [
        'id' => 'pb-ai-panel',
        'class' => ['pb-ai'],
      ],
      // THE TOGGLE IS A CHILD OF THE BAR, not a fixed element beside it. It is
      // positioned against the bar's own box — `bottom: 100%` — so it travels
      // with it, and hiding is one transform on the bar with no second
      // position to keep in step.
      //
      // RENDERED HERE, not built by the script: a button that arrives over
      // HTTP fails silently when the response is not what was expected, and
      // one built in JS is absent for as long as the script takes to run.
      // Rendered with the bar, it exists whenever the bar does.
      'toggle' => [
        '#type' => 'inline_template',
        '#weight' => -10,
        '#template' => <<<'TWIG'
          <button type="button" class="pb-ai-toggle" aria-controls="pb-ai-panel" aria-expanded="true" aria-label="{{ label }}">
            <span class="pb-ai-toggle__icon" aria-hidden="true">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M19 9l1.25-2.75L23 5l-2.75-1.25L19 1l-1.25 2.75L15 5l2.75 1.25L19 9zm-7.5.5L9 4 6.5 9.5 1 12l5.5 2.5L9 20l2.5-5.5L17 12l-5.5-2.5zM19 15l-1.25 2.75L15 19l2.75 1.25L19 23l1.25-2.75L23 19l-2.75-1.25L19 15z"/></svg>
            </span>
          </button>
          TWIG,
        // A HALF CIRCLE HOLDS NO TEXT, so the name is an aria-label and the
        // state is aria-expanded, which the script keeps current. Both live
        // here rather than in JS, where no translation could reach them.
        '#context' => ['label' => $this->t('Show or hide the assistant')],
        // Asked for here as well as by the form below. Measured 2026-09-17:
        // the form does attach it and it does bubble, so this changes nothing
        // today — it removes a contract held across two classes. If
        // buildForm() ever returned early, the toggle would otherwise render
        // as a raw browser button. Drupal de-duplicates libraries, so asking
        // twice costs nothing.
        '#attached' => ['library' => ['paragraphs_bundles_ai/bar']],
      ],
      'form' => $this->formBuilder->getForm(PbAiPageForm::class, $node, $decision->fields),
    ];
  }

}
