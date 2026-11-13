<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_patterns;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AnnounceCommand;
use Drupal\Core\Ajax\BeforeCommand;
use Drupal\Core\Ajax\FocusFirstCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Psr\Log\LoggerInterface;

/**
 * The Insert button's validate, submit and Ajax callbacks.
 *
 * Named on the button as "paragraphs_bundles_patterns.insert_form:<method>",
 * so the form cache stores a string and never this object.
 *
 * The submit appends the section to the Paragraphs widget state the way
 * ParagraphsWidget::duplicateSubmit() appends a copy. Nothing is saved until
 * the page is saved. Access is checked again here, not only where the picker
 * is drawn, so a forged request changes nothing.
 *
 * @phpstan-import-type PatternCard from \Drupal\paragraphs_bundles_patterns\PatternInserter
 */
final class PatternInsertForm {

  use StringTranslationTrait;

  /**
   * Where the submit leaves the inserted field, delta and Pattern.
   */
  public const INSERTED_KEY = ['paragraphs_bundles_patterns', 'inserted'];

  public function __construct(
    private readonly PatternInserter $inserter,
    private readonly AccountInterface $currentUser,
    private readonly MessengerInterface $messenger,
    private readonly RendererInterface $renderer,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Checks the choice, the field and the editor's access.
   *
   * @param array<string, mixed> $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validate(array &$form, FormStateInterface $form_state): void {
    $parents = $this->choiceParents($form_state);
    if ($parents === NULL) {
      return;
    }
    $name = implode('][', $parents);
    $choice = $form_state->getValue($parents);
    if (!is_string($choice) || $choice === '') {
      $form_state->setErrorByName($name, $this->t('Choose a pattern to insert.'));
      return;
    }
    $card = $this->card($form, $form_state, $choice);
    if ($card === NULL || $card['field'] === NULL) {
      $form_state->setErrorByName($name, $this->t('This pattern cannot be inserted into this page.'));
      return;
    }
    $host = $this->host($form_state);
    $definition = $host?->getFieldDefinition($card['field']);
    if ($definition === NULL) {
      $form_state->setErrorByName($name, $this->t('This pattern cannot be inserted into this page.'));
      return;
    }
    $state = WidgetBase::getWidgetState([], $card['field'], $form_state);
    $cardinality = $definition->getFieldStorageDefinition()->getCardinality();
    if ($cardinality !== FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED && (int) ($state['real_item_count'] ?? 0) >= $cardinality) {
      $form_state->setErrorByName($name, $this->t('This field is full. Remove a section before inserting a pattern.'));
    }
  }

  /**
   * Adds the section to the field and rebuilds the form.
   *
   * @param array<string, mixed> $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submit(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild();
    $parents = $this->choiceParents($form_state);
    $host = $this->host($form_state);
    if ($parents === NULL || $host === NULL) {
      return;
    }
    $choice = $form_state->getValue($parents);
    $card = is_string($choice) ? $this->card($form, $form_state, $choice) : NULL;
    if ($card === NULL || $card['field'] === NULL) {
      return;
    }
    $fieldName = $card['field'];
    try {
      $built = $this->inserter->build($card['pattern'], $host, $fieldName);
    }
    catch (\InvalidArgumentException $e) {
      $this->logger->error('Pattern @id could not be inserted: @message', [
        '@id' => $choice,
        '@message' => $e->getMessage(),
      ]);
      $this->messenger->addError($this->t('The pattern could not be inserted. A paragraph type it needs is missing on this site.'));
      return;
    }
    $paragraph = $built['paragraph'];
    $display = $this->formDisplay($form_state);
    $formMode = $display?->getRenderer($fieldName)?->getSetting('form_display_mode');
    $state = WidgetBase::getWidgetState([], $fieldName, $form_state);
    $delta = (int) ($state['items_count'] ?? 0);
    $state['paragraphs'][$delta] = [
      'entity' => $paragraph,
      'display' => EntityFormDisplay::collectRenderDisplay($paragraph, is_string($formMode) ? $formMode : 'default'),
      'mode' => 'edit',
    ];
    $state['items_count'] = $delta + 1;
    $state['real_item_count'] = (int) ($state['real_item_count'] ?? 0) + 1;
    WidgetBase::setWidgetState([], $fieldName, $form_state, $state);

    // The choice is spent: the rebuilt picker starts with nothing selected.
    $input = &$form_state->getUserInput();
    NestedArray::unsetValue($input, $parents);

    $form_state->set(self::INSERTED_KEY, ['field' => $fieldName, 'delta' => $delta, 'pattern' => $choice]);
    foreach ($built['messages'] as $message) {
      $this->messenger->addWarning($message);
    }
    $pattern = $card['pattern'];
    if ($pattern['needs'] !== []) {
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      $needs = array_map(fn (string $need): string => (string) $this->t($need), $pattern['needs']);
      $this->messenger->addStatus($this->t('To keep %name looking right: @needs', [
        // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
        '%name' => $this->t($pattern['label']),
        '@needs' => implode(' ', $needs),
      ]));
    }
  }

  /**
   * Redraws the field and the picker, and moves focus into the new section.
   *
   * @param array<string, mixed> $form
   *   The complete, rebuilt form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The commands.
   */
  public function ajax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $inserted = $form_state->get(self::INSERTED_KEY);
    $messages = ['#type' => 'status_messages'];
    $fieldName = is_array($inserted) ? ($inserted['field'] ?? NULL) : NULL;
    $element = is_string($fieldName) ? NestedArray::getValue($form, [$fieldName, 'widget']) : NULL;
    $picker = $form[PatternPicker::KEY] ?? NULL;
    if (!is_array($inserted) || !is_string($fieldName) || !is_array($element) || !is_array($picker)) {
      // Nothing was added: show why beside the picker.
      $output = (string) $this->renderer->renderRoot($messages);
      if ($output !== '') {
        $response->addCommand(new BeforeCommand('#' . PatternPicker::WRAPPER_ID, $output));
      }
      return $response;
    }
    $state = WidgetBase::getWidgetState([], $fieldName, $form_state);
    $delta = $inserted['delta'] ?? 0;
    $selector = NestedArray::getValue($element, [$delta, 'subform', '#attributes', 'data-drupal-selector']);
    $status = NestedArray::getValue($picker, ['actions', 'status', 'sentence', '#markup']);
    $sentence = $status instanceof \Stringable ? (string) $status : '';
    $wrapper = $state['ajax_wrapper_id'] ?? '';

    $html = (string) $this->renderer->renderRoot($element);
    $response->addCommand(new ReplaceCommand('#' . (is_string($wrapper) ? $wrapper : ''), $html));
    $attachments = is_array($element['#attached'] ?? NULL) ? $element['#attached'] : [];
    $response->addAttachments($attachments);
    $pickerHtml = (string) $this->renderer->renderRoot($picker);
    $response->addCommand(new ReplaceCommand('#' . PatternPicker::WRAPPER_ID, $pickerHtml));
    $attachments = is_array($picker['#attached'] ?? NULL) ? $picker['#attached'] : [];
    $response->addAttachments($attachments);

    if (is_string($selector) && $selector !== '') {
      $target = '[data-drupal-selector="' . $selector . '"]';
      // Printed at the top of the new section, where the editor lands.
      $output = (string) $this->renderer->renderRoot($messages);
      if ($output !== '') {
        $response->addCommand(new BeforeCommand($target, $output));
      }
      $response->addCommand(new FocusFirstCommand($target));
      $response->addCommand(new AnnounceCommand($sentence !== '' ? strip_tags($sentence) : (string) $this->t('Pattern inserted.')));
    }
    return $response;
  }

  /**
   * Returns the #parents of the radios from the pressed button.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return list<string>|null
   *   The parents, or NULL when another button was pressed.
   */
  private function choiceParents(FormStateInterface $form_state): ?array {
    $button = $form_state->getTriggeringElement() ?? [];
    $parents = $button['#pb_patterns_choice'] ?? NULL;
    return is_array($parents) ? $parents : NULL;
  }

  /**
   * Returns a card, worked out again from the current access.
   *
   * @param array<string, mixed> $form
   *   The complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $choice
   *   The bundle.name key.
   *
   * @return PatternCard|null
   *   The card, or NULL when this editor may not use the picker.
   */
  private function card(array $form, FormStateInterface $form_state, string $choice): ?array {
    $host = $this->host($form_state);
    $display = $this->formDisplay($form_state);
    if ($host === NULL || $display === NULL || !$this->currentUser->hasPermission(PatternInserter::PERMISSION)) {
      return NULL;
    }
    $fields = $this->inserter->fields($host, $display, $form, $this->currentUser);
    return $this->inserter->cards($fields, $this->currentUser)[$choice] ?? NULL;
  }

  /**
   * Returns the entity the form edits.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The entity.
   */
  private function host(FormStateInterface $form_state): ?ContentEntityInterface {
    $object = $form_state->getFormObject();
    $entity = $object instanceof ContentEntityFormInterface ? $object->getEntity() : NULL;
    return $entity instanceof ContentEntityInterface ? $entity : NULL;
  }

  /**
   * Returns the form display of the form.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface|null
   *   The display.
   */
  private function formDisplay(FormStateInterface $form_state): ?EntityFormDisplayInterface {
    $object = $form_state->getFormObject();
    return $object instanceof ContentEntityFormInterface ? $object->getFormDisplay($form_state) : NULL;
  }

}
