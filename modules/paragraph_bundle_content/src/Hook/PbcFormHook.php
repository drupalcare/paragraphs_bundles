<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_content\Hook;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Drupal\paragraphs_bundles\Utility\PbRenderArray;

/**
 * Attaches PB Content admin assets on pb_content node add/edit forms.
 */
final class PbcFormHook {

  /**
   * Implements hook_form_node_form_alter().
   *
   * @param array<string, mixed> $form
   *   The node form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(array &$form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof EntityFormInterface) {
      return;
    }

    $node = $form_object->getEntity();
    if (!$node instanceof NodeInterface || $node->bundle() !== 'pb_content') {
      return;
    }

    PbRenderArray::attachLibraries($form, 'paragraph_bundle_content/paragraph-bundle-content-admin');
  }

}
