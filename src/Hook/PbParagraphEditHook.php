<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Hook;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Sends the editor back to the Paragraphs List after saving a paragraph there.
 *
 * Moved from pb_import's hook class; behaviour is unchanged.
 */
final class PbParagraphEditHook {

  /**
   * The route this module serves its own paragraph edit form on.
   */
  private const EDIT_ROUTE = 'paragraphs_bundles.paragraph_edit';

  public function __construct(
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for paragraph forms.
   *
   * Only for the form this module itself serves, at
   * `/admin/content/paragraph/{paragraph}/edit`. It must NOT run on every
   * paragraph form on the site: editing a paragraph on a node form, or inside
   * a layout, would otherwise redirect the editor to the list, a page they had
   * not asked for.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $form_id
   *   The form id.
   */
  #[Hook('form_paragraph_form_alter')]
  public function formParagraphFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    if ($this->routeMatch->getRouteName() !== self::EDIT_ROUTE) {
      return;
    }
    $actions = $form['actions'] ?? NULL;
    if (!is_array($actions)) {
      return;
    }
    $submit_button = $actions['submit'] ?? NULL;
    if (!is_array($submit_button) || !is_array($submit_button['#submit'] ?? NULL)) {
      return;
    }
    $submit_button['#submit'][] = [static::class, 'redirectToList'];
    $actions['submit'] = $submit_button;
    $form['actions'] = $actions;
  }

  /**
   * Submit handler that returns the editor to the paragraph list.
   *
   * Static because form submit callbacks are serialised with the form and a
   * service-holding object must not be. The messenger therefore comes from the
   * container here.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function redirectToList(array $form, FormStateInterface $form_state): void {
    $form_object = $form_state->getFormObject();
    if (!$form_object instanceof EntityFormInterface) {
      return;
    }
    \Drupal::messenger()->addStatus(new TranslatableMarkup('The paragraph %label has been saved.', [
      '%label' => $form_object->getEntity()->label(),
    ]));
    $form_state->setRedirect('paragraphs_bundles.paragraphs_list');
  }

}
