<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\paragraphs_bundles_ai\Service\SectionCatalogInterface;
use Drupal\paragraphs_bundles_ai\Service\SectionComposerInterface;
use Drupal\paragraphs_bundles_ai\Service\SectionDrafterInterface;
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $form = new static();
    $form->catalog = $container->get(SectionCatalogInterface::class);
    $form->drafter = $container->get(SectionDrafterInterface::class);
    $form->composer = $container->get(SectionComposerInterface::class);

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
    // query parameter is what marks it as new once the badge exists.
    $url = Url::fromRoute('entity.node.canonical', ['node' => $nodeId], [
      'query' => ['pb-ai-added' => $result->nodeId === NULL ? '' : (string) $result->sections],
    ])->toString();

    return $response->addCommand(new RedirectCommand($url));
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
