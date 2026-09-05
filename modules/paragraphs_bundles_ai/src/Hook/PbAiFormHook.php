<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Hook;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Drupal\paragraphs_bundles_ai\Service\ChatSurfaceInterface;

/**
 * Puts the chat bar on node add and edit forms.
 *
 * WHY THIS ASKS ABOUT THE ENTITY AND NOT THE FORM CLASS. The first version of
 * this hook tested `instanceof \Drupal\node\NodeForm` — a class that does not
 * exist; the real one is `\Drupal\node\Form\NodeForm`. PHP does not complain
 * about `instanceof` against an unknown class, it just answers FALSE, so the
 * hook ran on every node form and added nothing, with no error anywhere.
 * Measured on sts.local 2026-09-04; the bar was simply absent.
 *
 * Testing the ENTITY removes the whole class of mistake. It also survives the
 * ordinary case of a site substituting its own node form class, which the
 * `instanceof` version would have failed on just as silently.
 *
 * Matching form ids would be no better: `node_*_form` also catches
 * `node_type_edit_form` and anything else named that way.
 *
 * The hook adds a render element and nothing else. It does not touch the form
 * display, reorder fields, or change any setting — those are the site
 * builder's, and this is a guest on their form.
 */
final class PbAiFormHook {

  /**
   * Constructs the hook.
   *
   * @param \Drupal\paragraphs_bundles_ai\Service\ChatSurfaceInterface $surface
   *   Decides whether the bar appears, and builds it.
   */
  public function __construct(
    private readonly ChatSurfaceInterface $surface,
  ) {}

  /**
   * Appends the chat bar to node add and edit forms.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   * @param string $formId
   *   The form id.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $formState, string $formId): void {
    $object = $formState->getFormObject();

    if (!$object instanceof EntityFormInterface) {
      return;
    }

    if (!$object->getEntity() instanceof NodeInterface) {
      return;
    }

    // Add and edit only. Delete, and any other one-off operation a module
    // registers on nodes, get nothing: a confirmation form is not a place to
    // start a conversation about creating sections.
    if (!in_array($object->getOperation(), ['default', 'edit', 'add'], TRUE)) {
      return;
    }

    // The bundle decides whether the bar is any use here: a content type with
    // no paragraph field can only ever produce a refusal.
    $entity = $object->getEntity();
    $bar = $this->surface->build($entity->bundle());

    if ($bar === []) {
      return;
    }

    $form['paragraphs_bundles_ai_chat'] = $bar;
  }

}
