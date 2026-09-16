<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\paragraphs_bundles_ai\Service\SectionCatalogInterface;
use Drupal\paragraphs_bundles_ai\Service\SectionComposerInterface;
use Drupal\paragraphs_bundles_ai\Service\SectionDrafterInterface;
use Drupal\paragraphs_bundles_ai\Service\SectionEditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The assistant, on the page a person is looking at.
 *
 * TWO THINGS THE MODEL IS NEVER ASKED. The section type comes from the
 * dropdown and the page comes from the URL, so neither is a sentence anyone
 * has to interpret. What the person types is only ever the words.
 *
 * IT ADDS, AND ONLY TO THIS PAGE. There is no node id in the form: the node is
 * the one being viewed, which is also the one whose edit access was checked
 * before the form was built, and checked again by the writer.
 */
final class PbAiPageForm extends FormBase {

  /**
   * Fills the dropdown.
   */
  protected SectionCatalogInterface $catalog;

  /**
   * Asks the model for the words.
   */
  protected SectionDrafterInterface $drafter;

  /**
   * Writes the section.
   */
  protected SectionComposerInterface $composer;

  /**
   * Changes the words in a section that is already here.
   */
  protected SectionEditorInterface $editor;

  /**
   * Loads the page again when the change is applied.
   *
   * FormBase does not carry one — that is ControllerBase — so it is injected
   * rather than reached for through \Drupal.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $form = new static();
    $form->catalog = $container->get(SectionCatalogInterface::class);
    $form->drafter = $container->get(SectionDrafterInterface::class);
    $form->composer = $container->get(SectionComposerInterface::class);
    $form->editor = $container->get(SectionEditorInterface::class);
    $form->entityTypeManager = $container->get('entity_type.manager');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'paragraphs_bundles_ai_page';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\node\NodeInterface|null $node
   *   The page being viewed.
   * @param string $field
   *   The paragraph field to write into.
   *
   * @return array<string, mixed>
   *   The form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, string $field = ''): array {
    if (!$node instanceof NodeInterface || $field === '') {
      return [];
    }

    $form_state->set('pb_ai_node', (int) $node->id());
    $form_state->set('pb_ai_field', $field);

    $form['#attributes']['class'][] = 'pb-ai-bar';
    $form['#attached']['library'][] = 'paragraphs_bundles_ai/bar';

    $form['section'] = [
      '#type' => 'select',
      '#title' => $this->t('Add section'),
      '#options' => $this->catalog->addable($node, $field),
      '#empty_option' => $this->t('- Choose a section -'),
      '#required' => TRUE,
    ];

    $form['count'] = [
      '#type' => 'number',
      '#title' => $this->t('How many inside it'),
      '#description' => $this->t('Slides, cards or items, for a section that holds them.'),
      '#default_value' => 0,
      '#min' => 0,
      '#max' => 12,
    ];

    $form['words'] = [
      '#type' => 'textarea',
      '#title' => $this->t('What should it say?'),
      '#rows' => 2,
      '#required' => TRUE,
      '#placeholder' => $this->t('A hero about our autumn range, warm and short.'),
    ];

    // The second dropdown, and only when there is something in it. An empty
    // "change a section" control on a page with no sections is a control that
    // cannot do anything.
    $sections = $this->catalog->onPage($node, $field);

    if ($sections !== []) {
      $options = [];

      foreach ($sections as $section) {
        $options[$section['id']] = sprintf(
          '%d · %s — %s — %s',
          $section['position'],
          $section['label'],
          $section['title'] === '' ? $this->t('no title') : '"' . $section['title'] . '"',
          $section['dom_id'],
        );
      }

      $form['existing'] = [
        '#type' => 'select',
        '#title' => $this->t('Or change a section'),
        '#options' => $options,
        '#empty_option' => $this->t('- Choose a section -'),
      ];
    }

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['draft'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add it to this page'),
      '#ajax' => [
        'callback' => '::draft',
        'wrapper' => 'pb-ai-status',
        'progress' => ['type' => 'throbber', 'message' => $this->t('Writing it…')],
      ],
    ];

    if ($sections !== []) {
      $form['actions']['change'] = [
        '#type' => 'submit',
        '#value' => $this->t('Change that section'),
        '#ajax' => [
          'callback' => '::change',
          'wrapper' => 'pb-ai-status',
          'progress' => ['type' => 'throbber', 'message' => $this->t('Rewriting it…')],
        ],
      ];
    }

    $form['status'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'pb-ai-status', 'class' => ['pb-ai-bar__status']],
    ];

    return $form;
  }

  /**
   * Drafts the content, writes the section, and reloads the page onto it.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public function draft(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $nodeId = (int) $form_state->get('pb_ai_node');
    $field = (string) $form_state->get('pb_ai_field');
    $bundle = (string) $form_state->getValue('section');
    $words = (string) $form_state->getValue('words');
    $count = (int) $form_state->getValue('count');

    // The revision as it stands BEFORE anything is written. That is what undo
    // has to return to — the revision the write creates is the thing being
    // undone, and offering it back would be a link to no change at all.
    $node = $this->entityTypeManager->getStorage('node')->load($nodeId);
    $before = $node instanceof NodeInterface ? (int) $node->getRevisionId() : 0;

    $draft = $this->drafter->draft($bundle, $words, max(0, $count));

    if (!$draft->usable) {
      return $this->say($response, 'error', $draft->refusals);
    }

    // The composer re-checks every value against the real fields, so a model
    // that answered with something impossible is refused here rather than
    // written and discovered later.
    $result = $this->composer->compose(
      NULL,
      $field,
      $bundle,
      $draft->values,
      $draft->children,
      NULL,
      '',
      $nodeId,
    );

    if (!$result->created) {
      return $this->say($response, 'error', $result->refusals);
    }

    // Back to the page, so the section is seen rather than described. The
    // query carries what the script needs to mark it and to offer the way back.
    $url = Url::fromRoute('entity.node.canonical', ['node' => $nodeId], [
      'query' => array_filter([
        'pb-ai-added' => (string) $result->sections,
        'pb-ai-revert' => $this->revertUrl($nodeId, $before),
      ]),
    ])->toString();

    return $response->addCommand(new RedirectCommand($url));
  }

  /**
   * Rewrites the words in the section the person pointed at.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public function change(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $nodeId = (int) $form_state->get('pb_ai_node');
    $field = (string) $form_state->get('pb_ai_field');
    $paragraphId = (int) $form_state->getValue('existing');
    $words = trim((string) $form_state->getValue('words'));

    if ($paragraphId === 0) {
      return $this->say($response, 'error', ['Choose which section to change.']);
    }

    if ($words === '') {
      return $this->say($response, 'error', ['Say what should change about it.']);
    }

    $node = $this->entityTypeManager->getStorage('node')->load($nodeId);

    if (!$node instanceof NodeInterface) {
      return $this->say($response, 'error', ['This page could not be loaded again.']);
    }

    $before = (int) $node->getRevisionId();
    $current = $this->editor->currentValues($paragraphId);
    $bundle = $this->bundleOf($node, $field, $paragraphId);

    if ($bundle === '') {
      return $this->say($response, 'error', [
        'That section is not on this page. Pick one from the list.',
      ]);
    }

    $draft = $this->drafter->draftEdit($bundle, $current, $words);

    if (!$draft->usable) {
      return $this->say($response, 'error', $draft->refusals);
    }

    $outcome = $this->editor->edit($node, $field, $paragraphId, $draft->values);

    if (!$outcome->changed) {
      return $this->say(
        $response,
        $outcome->refusals === [] ? 'status' : 'error',
        $outcome->refusals === [] ? $outcome->notes : $outcome->refusals,
      );
    }

    $url = Url::fromRoute('entity.node.canonical', ['node' => $nodeId], [
      'query' => array_filter([
        'pb-ai-updated' => (string) $paragraphId,
        'pb-ai-revert' => $this->revertUrl($nodeId, $before),
      ]),
    ])->toString();

    return $response->addCommand(new RedirectCommand($url));
  }

  /**
   * A link to core's own revert form, for the revision before the change.
   *
   * UNDO IS CORE'S, DELIBERATELY. Reverting a revision is a confirm form
   * Drupal already ships, with its own permission check — "revert all
   * revisions" — and its own log message. Building a second way to do it here
   * would mean re-implementing that check, and a bespoke undo that skips a
   * permission is worse than no undo at all.
   *
   * @param int $nodeId
   *   The page.
   * @param int $revisionId
   *   The revision to go back to. Zero when there is nothing to go back to.
   *
   * @return string
   *   The URL, or an empty string when undo is not available.
   */
  private function revertUrl(int $nodeId, int $revisionId): string {
    if ($revisionId === 0) {
      return '';
    }

    return Url::fromRoute('node.revision_revert_confirm', [
      'node' => $nodeId,
      'node_revision' => $revisionId,
    ])->toString();
  }

  /**
   * The bundle of a section that is on this page.
   *
   * Asked of the PAGE rather than of the paragraph storage, so an id that
   * belongs to some other page cannot be edited through this form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The page.
   * @param string $field
   *   The paragraph field.
   * @param int $paragraphId
   *   The section.
   *
   * @return string
   *   The bundle, or an empty string when that section is not on this page.
   */
  private function bundleOf(NodeInterface $node, string $field, int $paragraphId): string {
    foreach ($this->catalog->onPage($node, $field) as $section) {
      if ($section['id'] === $paragraphId) {
        return $section['bundle'];
      }
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Everything happens in the AJAX callback. A non-AJAX submit reloads the
    // page unchanged, which is the safe outcome when scripting is off.
  }

  /**
   * Puts a message in the status area.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response.
   * @param string $kind
   *   Either 'error' or 'status'.
   * @param list<string> $lines
   *   What to say.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  private function say(AjaxResponse $response, string $kind, array $lines): AjaxResponse {
    $markup = '<div class="messages messages--' . $kind . '">';

    foreach ($lines as $line) {
      $markup .= '<p>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    return $response->addCommand(new HtmlCommand('#pb-ai-status', $markup . '</div>'));
  }

}
