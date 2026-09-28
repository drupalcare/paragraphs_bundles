<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Form;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\paragraphs_bundles_ai\Service\BriefsInterface;
use Drupal\paragraphs_bundles_ai\Service\BundleProfilerInterface;
use Drupal\paragraphs_bundles_ai\Service\PromptHintsInterface;
use Drupal\paragraphs_bundles_ai\Service\SectionCatalogInterface;
use Drupal\paragraphs_bundles_ai\Service\SectionComposerInterface;
use Drupal\paragraphs_bundles_ai\Service\ChildSlotsInterface;
use Drupal\paragraphs_bundles_ai\Service\SectionDrafterInterface;
use Drupal\paragraphs_bundles_ai\Service\SectionFeaturesInterface;
use Drupal\paragraphs_bundles_ai\Service\SectionEditorInterface;
use Drupal\paragraphs_bundles_ai\Service\StylePresetsInterface;
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
   * Says which sections a count applies to.
   */
  protected ChildSlotsInterface $childSlots;

  /**
   * The features a new section is switched on with.
   */
  protected SectionFeaturesInterface $features;

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
   * The named looks a person may apply.
   */
  protected StylePresetsInterface $presets;

  /**
   * Says which fields a bundle has, so a preset can be filtered to it.
   */
  protected BundleProfilerInterface $profiler;

  /**
   * Renders messages through core's own template rather than by hand.
   */
  protected RendererInterface $renderer;

  /**
   * Says what to type, for whichever section is chosen.
   */
  protected PromptHintsInterface $hints;

  /**
   * The best-practice brief for whichever section is chosen.
   */
  protected BriefsInterface $briefs;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $form = new static();
    $form->catalog = $container->get(SectionCatalogInterface::class);
    $form->drafter = $container->get(SectionDrafterInterface::class);
    $form->childSlots = $container->get(ChildSlotsInterface::class);
    $form->features = $container->get(SectionFeaturesInterface::class);
    $form->composer = $container->get(SectionComposerInterface::class);
    $form->editor = $container->get(SectionEditorInterface::class);
    $form->entityTypeManager = $container->get('entity_type.manager');
    $form->presets = $container->get(StylePresetsInterface::class);
    $form->profiler = $container->get(BundleProfilerInterface::class);
    $form->renderer = $container->get('renderer');
    $form->hints = $container->get(PromptHintsInterface::class);
    $form->briefs = $container->get(BriefsInterface::class);

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
   * @param array<string, string> $fields
   *   Every displayed paragraph field, machine name => label. More than one is
   *   ordinary: a content type may have a body and a footer.
   *
   * @return array<string, mixed>
   *   The form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, array $fields = []): array {
    if (!$node instanceof NodeInterface || $fields === []) {
      return [];
    }

    // THE ALLOW-LIST, AND IT LIVES ON THE SERVER. Which field gets written
    // into becomes a submitted value below, and a submitted field name is not
    // trusted: both callbacks look it up in this map and refuse anything that
    // is not in it. Without that, a crafted post could aim the write at any
    // field on the node.
    $form_state->set('pb_ai_node', (int) $node->id());
    $form_state->set('pb_ai_fields', $fields);

    // WHOLE-ARRAY ASSIGNMENT, NOT `[] =`. $form is `array<string, mixed>`, so
    // appending through an offset reads an untyped value first. Nothing sets
    // either key on the root form before buildForm() runs, so there is nothing
    // here to preserve. The attachments are gathered below and assigned once.
    $form['#attributes'] = ['class' => ['pb-ai-bar']];

    // ONE LIST PER FIELD, PLUS THEIR UNION. Which bundles a field accepts is a
    // property of that field: with a body that takes anything and a footer
    // restricted to two bundles, there is no single Add list. The union is
    // offered, the script narrows it the moment a different field is chosen,
    // and the callback re-derives the real list and refuses anything outside
    // it — so the narrowing is a convenience, never the check.
    $perField = [];
    $addable = [];

    foreach (array_keys($fields) as $name) {
      $perField[$name] = $this->catalog->addable($node, (string) $name);
      $addable += $perField[$name];
    }

    // A SECTION WITH NO BRIEF IS NOT OFFERED, and the briefs file is therefore
    // the filter. The maintainer: "we must filter which bundle to show, not any
    // bundle created or active should the AI handle it." Seven of the 33 have
    // no brief — the six that can only point at content somebody made elsewhere
    // (block, block content, node reference, views, webform, contact form) and
    // pb_import's target. Writing a brief is all it takes to offer a section,
    // which keeps the list and the advice in one place instead of two.
    $briefs = $this->briefs->forBundles(array_map(strval(...), array_keys($addable)));
    $addable = array_intersect_key($addable, $briefs);

    // The per-field lists narrow the dropdown in the browser, so they have to
    // lose the same bundles or the script would re-offer one the server will
    // not build.
    foreach ($perField as $name => $list) {
      $perField[$name] = array_intersect_key($list, $briefs);
    }

    asort($addable);

    // GROUPED BY CATEGORY, SORTED BOTH WAYS. A flat list of 26 in whatever
    // order the fields happened to come back in is a list nobody reads —
    // "the dropdown list is not smart, it is mixed". Drupal renders a nested
    // options array as optgroups, so the grouping IS the shape of this array
    // and needs no theme override.
    //
    // KEPT SEPARATE FROM $addable ON PURPOSE: three things below still ask it
    // for bundle names with array_keys(), and on a nested array those come
    // back as category names.
    $grouped = [];

    foreach ($addable as $bundle => $label) {
      $category = $briefs[(string) $bundle]['category'] ?? '';

      // A brief with no category would otherwise open a group with an empty
      // heading, which reads as a rendering fault rather than a missing value.
      if (trim($category) === '') {
        continue;
      }

      $grouped[$category][(string) $bundle] = (string) $label;
    }

    ksort($grouped, SORT_NATURAL | SORT_FLAG_CASE);

    foreach ($grouped as $category => $items) {
      asort($items, SORT_NATURAL | SORT_FLAG_CASE);
      $grouped[$category] = $items;
    }

    // WHICH SECTIONS HOLD OTHER SECTIONS, handed to the page as data. "How
    // many inside it" is a question with no right answer for a section that
    // holds nothing, so the script hides it — and what nests is a fact about
    // this site's configuration, not something a script should decide.
    $nests = [];
    $byHand = [];

    foreach (array_keys($addable) as $bundle) {
      $profile = $this->profiler->profile((string) $bundle);
      // canDraftChildren(), NOT nests(). "Has a field that holds sections" and
      // "children can actually be written" are different questions, and this
      // asked the first while the drafter answered the second — so on 12 of
      // the 18 nesting bundles the count field appeared and the children were
      // silently dropped. One question, one owner, asked in both places.
      // Since 2026-09-27 that owner is ChildSlots, which also reads the
      // briefs' nesting list — so a carousel counts, and two columns (one
      // section per column, whatever the count) does not.
      $nests[$bundle] = $this->childSlots->countable((string) $bundle);

      // Said BEFORE the button is pressed, not only afterwards. Choosing
      // "Views" and only then learning it needs a view is a worse moment to
      // find out than the moment of choosing.
      if ($profile !== NULL) {
        $labels = [];

        foreach ($profile->needsByHand() as $fact) {
          $labels[] = $fact->label;
        }

        if ($labels !== []) {
          $byHand[$bundle] = (string) $this->t('You will need to choose @fields yourself after this is added — it cannot be written.', [
            '@fields' => implode(' and ', $labels),
          ]);
        }
      }
    }

    $pbAi = [
      'nests' => $nests,
      'byHand' => $byHand,
    ];
    // WHAT TO TYPE, per section. The dropdown names a section and then offers
    // one empty box; somebody who has never opened that bundle's edit form has
    // no way of knowing a hero has a body AND a link title. Built on the
    // server from the real fields, so it cannot drift from what the section
    // actually holds.
    $pbAi['hints'] = $this->hints->forBundles(
      array_map(strval(...), array_keys($addable)),
    );

    // THE BEST-PRACTICE BRIEF, FROM THE BRIEFS FILE — not from the paragraphs
    // type description, which is what this read until 2026-09-19 and which the
    // maintainer reported as a bug: choosing Carousel showed *"Slides that move
    // one at a time, across or down, with arrows or dots."* That is the
    // description doing its own job correctly; it is not the brief.
    //
    // THE TWO ARE DIFFERENT TEXTS WITH DIFFERENT READERS. A description is one
    // sentence saying what a section IS, and it belongs in the paragraphs-type
    // admin list. A brief is a category, a line about when to reach for it, ten
    // lines of advice naming the required fields, and an example prompt —
    // edited as a set, in one file, by the person who owns the words.
    //
    // A BUNDLE WITH NO BRIEF IS LEFT OUT, AND THAT IS THE FILTER. Six bundles
    // have none on purpose: block, block content, node reference, views,
    // webform and contact form. The maintainer's words: "Ai can not creat
    // them, you must reference created content from the system in a field".
    $pbAi['briefs'] = $briefs;
    // Which bundles each field will actually take, so the script can narrow
    // the Add list when the person changes where it is going.
    $pbAi['allowed'] = array_map(
      static fn(array $list): array => array_keys($list),
      $perField,
    );

    $form['#attached'] = [
      'library' => ['paragraphs_bundles_ai/bar'],
      'drupalSettings' => ['pbAi' => $pbAi],
    ];

    // ROW ONE: what this is, and the switch for the brief panel.
    //
    // FOUR ROWS, ALWAYS, in this order: this one, the choices, the prompt, the
    // buttons. The shape is fixed so the bar never reflows into something
    // unfamiliar as controls appear and disappear.
    $form['intro'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['pb-ai-bar__row', 'pb-ai-bar__row--intro']],
    ];

    $form['intro']['text'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Choose a section and say what it should say. Nothing is published: it is added to this page as a new version you can undo.'),
      '#attributes' => ['class' => ['pb-ai-bar__intro']],
    ];

    // ROW ONE: everything that is a choice.
    $form['choices'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['pb-ai-bar__row', 'pb-ai-bar__row--choices']],
    ];

    // WHERE IT GOES, and only when that is a real question. One paragraph
    // field is the common case and needs no control at all. Two make "add a
    // hero" genuinely ambiguous — and the ambiguity was being resolved
    // silently, in favour of whichever field the field manager listed first.
    // A default is fine here precisely because it is on screen: the defect was
    // never the choice, it was making it invisibly.
    if (count($fields) > 1) {
      $form['choices']['target_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Where'),
        '#options' => $fields,
        '#default_value' => array_key_first($fields),
      ];
    }

    $form['choices']['section'] = [
      '#type' => 'select',
      '#title' => $this->t('Add section'),
      '#options' => $grouped,
      '#empty_option' => $this->t('- Choose a section -'),
    ];

    $form['choices']['count'] = [
      '#type' => 'number',
      '#title' => $this->t('How many'),
      '#default_value' => 0,
      '#min' => 0,
      '#max' => 12,
      // A hint rather than a description line: the bar is kept to one row.
      '#attributes' => ['title' => $this->t('0 uses the number in your words, or 3 when they give none.')],
      // Hidden until a section that nests is chosen; the script does the rest,
      // and a browser without it sees the field rather than nothing.
      '#wrapper_attributes' => ['class' => ['pb-ai-bar__count']],
    ];

    // A named look, chosen by a person. The assistant is never asked for a
    // colour: these values were written and their contrast checked once, and
    // applying one repeats that decision rather than making a new one.
    $styles = $this->presets->options();

    if ($styles !== []) {
      $form['choices']['style'] = [
        '#type' => 'select',
        '#title' => $this->t('Style'),
        '#options' => $styles,
        '#empty_option' => $this->t('- Leave the styling alone -'),
      ];
    }

    // The second dropdown, and only when there is something in it. An empty
    // "change a section" control on a page with no sections is a control that
    // cannot do anything.
    // THE EDIT LIST SPANS EVERY FIELD, and each section remembers which one it
    // came from. That is what lets the Edit path ask no "where" question at
    // all: a section id already knows where it lives, so asking would invite
    // an answer that disagrees with the page — and an editor pointed at a
    // field the section is not in finds nothing to edit.
    $options = [];
    $sectionFields = [];

    foreach ($fields as $name => $fieldLabel) {
      foreach ($this->catalog->onPage($node, (string) $name) as $section) {
        // SECTIONS THE ASSISTANT CANNOT REALLY EDIT ARE NOT OFFERED. A views
        // section is a pointer at a view; rewording its title while leaving
        // the thing it points at untouched is not what anybody means by "edit
        // this". The Add dropdown already says these need a hand, so saying it
        // twice — once as an offer, once as a refusal — would be worse than
        // not offering.
        $profile = $this->profiler->profile($section['bundle']);

        if ($profile === NULL || $profile->needsByHand() !== []) {
          continue;
        }

        // The id first, because it is what the tooltip on the page shows and
        // what the person is matching against, then just enough of the title
        // to tell two of the same kind apart. The field is named only when
        // there is more than one, so the common case reads as it always did.
        $title = $section['title'];

        if (mb_strlen($title) > 7) {
          $title = mb_substr($title, 0, 7) . '…';
        }

        $options[$section['id']] = sprintf(
          '%s — %s%s',
          $section['dom_id'],
          $title === '' ? (string) $this->t('no title') : $title,
          count($fields) > 1 ? ' · ' . $fieldLabel : '',
        );

        $sectionFields[(int) $section['id']] = (string) $name;
      }
    }

    // The id => field map the Edit callback resolves against. Server-side, so
    // a submitted id cannot name a field it was never offered from.
    $form_state->set('pb_ai_section_fields', $sectionFields);

    if ($options !== []) {
      $form['choices']['existing'] = [
        '#type' => 'select',
        '#title' => $this->t('Or edit a section'),
        '#options' => $options,
        '#empty_option' => $this->t('- Choose a section -'),
      ];
    }

    // ROW TWO: what to say, and the buttons that act on it.
    $form['prompt'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['pb-ai-bar__row', 'pb-ai-bar__row--prompt']],
    ];

    $form['prompt']['words'] = [
      '#type' => 'textarea',
      '#title' => $this->t('What should it say?'),
      '#rows' => 2,
      '#placeholder' => $this->t('A hero about our autumn range, warm and short.'),
    ];

    // ROW FOUR: the buttons, in their own row rather than beside the prompt.
    // One button sits left; two put the second on the right, which the CSS does
    // with `space-between` and no modifier class to keep in step.
    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['pb-ai-bar__row', 'pb-ai-bar__row--actions']],
    ];

    $form['actions']['draft'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add it to this page'),
      '#ajax' => [
        'callback' => '::draft',
        'wrapper' => 'pb-ai-status',
        'progress' => ['type' => 'throbber', 'message' => $this->t('Writing it…')],
      ],
    ];

    if ($options !== []) {
      $form['actions']['change'] = [
        '#type' => 'submit',
        '#value' => $this->t('Edit that section'),
        '#ajax' => [
          'callback' => '::change',
          'wrapper' => 'pb-ai-status',
          'progress' => ['type' => 'throbber', 'message' => $this->t('Rewriting it…')],
        ],
      ];
    }

    // THE TWO SWITCHES SIT WITH THE BUTTONS, and they are declared last so the
    // row reads Add · Edit · Prompts · Best practices in source order. The CSS
    // lays this out with a plain `display: flex` and nothing else, so an RTL
    // language reverses it for free rather than needing its own rule.
    //
    // BOTH ON BY DEFAULT. Somebody who has never built this section is exactly
    // who the panel is for, and they will not go looking for a switch to turn
    // it on. They are independent: either, both, or neither.
    $form['actions']['prompts'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Prompts'),
      '#default_value' => TRUE,
      '#wrapper_attributes' => ['class' => ['pb-ai-bar__switch']],
    ];

    $form['actions']['best_practices'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Best practices'),
      '#default_value' => TRUE,
      '#wrapper_attributes' => ['class' => ['pb-ai-bar__switch']],
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
    $nodeId = self::asInt($form_state->get('pb_ai_node'));
    $fields = self::asStringMap($form_state->get('pb_ai_fields'));
    $bundle = self::asString($form_state->getValue('section'));
    $words = self::asString($form_state->getValue('words'));
    $count = self::asInt($form_state->getValue('count'));

    // The field is a submitted value now, so it is looked up in the map the
    // form stored rather than used as given. A name that is not in that map
    // did not come from this form, and is refused.
    $field = $this->chosenField($form_state, $fields);

    if ($field === '') {
      return $this->say($response, 'error', [$this->t('Choose where the section should go.')]);
    }

    // NEITHER FIELD IS #required ANY MORE, and that is deliberate: the same
    // form carries a second button, and a browser enforcing "required" on the
    // add fields would block "Change that section" before it ever submitted.
    // So the two callbacks ask for what each of them actually needs.
    if ($bundle === '') {
      return $this->say($response, 'error', [$this->t('Choose a section to add.')]);
    }

    if ($words === '') {
      return $this->say($response, 'error', [$this->t('Say what the section should be about.')]);
    }

    // The revision as it stands BEFORE anything is written. That is what undo
    // has to return to — the revision the write creates is the thing being
    // undone, and offering it back would be a link to no change at all.
    $node = $this->entityTypeManager->getStorage('node')->load($nodeId);
    $before = $node instanceof NodeInterface ? (int) $node->getRevisionId() : 0;

    // The Add list is per field, so the bundle is re-checked against the field
    // actually chosen. The script narrows the dropdown for convenience; THIS
    // is what enforces it, and it runs whether or not the script did.
    if ($node instanceof NodeInterface) {
      $allowed = $this->catalog->addable($node, $field);

      if (!isset($allowed[$bundle])) {
        return $this->say($response, 'error', [
          $this->t('The "@field" field does not take that kind of section.', [
            '@field' => $fields[$field] ?? $field,
          ]),
        ]);
      }
    }

    $draft = $this->drafter->draft($bundle, $words, max(0, $count));

    if (!$draft->usable) {
      return $this->say($response, 'error', $draft->refusals);
    }

    // The preset is resolved HERE, against this bundle, rather than trusted as
    // written: a section without a border simply does not get one, and a key a
    // site has renamed is dropped instead of saved and silently ignored.
    $styling = [];
    $style = self::asString($form_state->getValue('style'));
    $profile = $this->profiler->profile($bundle);

    // FEATURES FIRST, the chosen look on top. Both are values a person set
    // once and checked; a look that names the same field is the more specific
    // choice, made for this page.
    $features = $profile === NULL ? [] : $this->features->valuesFor($profile);
    $styling = $features;

    if ($profile !== NULL && $style !== '') {
      $styling = $this->presets->valuesFor($style, $profile) + $styling;
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
      $styling,
    );

    if (!$result->created) {
      return $this->say($response, 'error', $result->refusals);
    }

    // THE NOTES HAVE TO SURVIVE THE RELOAD. Everything this tool says about
    // what it left for the person — a view still to choose, text it had to
    // shorten — is said at the moment of writing, and the next thing that
    // happens is a redirect. Put through the messenger, the sentence appears
    // on the page they land on; written into the status area, it would be
    // wiped by the redirect a millisecond later.
    // SEVERITY TRAVELS WITH THE NOTE NOW — the debt this comment used to name
    // is paid rather than restated. It read `str_starts_with($note,
    // 'CAREFUL:')`, which is a question only English can answer: translating
    // the producers, which is exactly what this module now does, would have
    // demoted every warning to a status message in every other language,
    // silently, with no test going red. CreationResult carries the severity.
    // NOT `(string) $warning`. These are TranslatableMarkup, whose __toString()
    // returns markup that is already safe; handing the messenger a plain string
    // invites it to be escaped a second time, so a placeholder would render as
    // its own entities. Markup is passed through as markup, and the cast is
    // reached only by a note that was a plain string to begin with, where it
    // does nothing.
    foreach ($result->warnings as $warning) {
      $this->messenger()->addWarning($warning instanceof MarkupInterface ? $warning : (string) $warning);
    }

    foreach ($result->notes as $note) {
      $this->messenger()->addStatus($note instanceof MarkupInterface ? $note : (string) $note);
    }

    // WHAT IT CAN DO, said once it exists. Only the features actually written
    // are named: where a chosen look set the same field to something else, the
    // look's value is what is on the page, and the feature was not applied.
    $applied = array_filter(
      $features,
      static fn(bool|int|string $value, string $name): bool => ($styling[$name] ?? NULL) === $value,
      ARRAY_FILTER_USE_BOTH,
    );
    $switchedOn = $profile === NULL ? [] : $this->features->describe($profile, $applied);

    if ($switchedOn !== []) {
      $this->messenger()->addStatus($this->t('Switched on for this section: @features. Change or turn any of them off in its Display tab.', [
        '@features' => implode('; ', $switchedOn),
      ]));
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
    $nodeId = self::asInt($form_state->get('pb_ai_node'));
    $paragraphId = self::asInt($form_state->getValue('existing'));
    $words = self::asString($form_state->getValue('words'));

    if ($paragraphId === 0) {
      return $this->say($response, 'error', [$this->t('Choose which section to change.')]);
    }

    // WHICH FIELD, ANSWERED BY THE SECTION rather than asked of the person.
    // The id went into this map when the form was built, so it cannot name a
    // field this page does not have — or one it was never offered from.
    $sectionFields = self::asStringMap($form_state->get('pb_ai_section_fields'));
    $field = $sectionFields[$paragraphId] ?? '';

    if ($field === '') {
      return $this->say($response, 'error', [
        $this->t('That section is not on this page. Pick one from the list.'),
      ]);
    }

    if ($words === '') {
      return $this->say($response, 'error', [$this->t('Say what should change about it.')]);
    }

    $node = $this->entityTypeManager->getStorage('node')->load($nodeId);

    if (!$node instanceof NodeInterface) {
      return $this->say($response, 'error', [$this->t('This page could not be loaded again.')]);
    }

    $before = (int) $node->getRevisionId();
    $current = $this->editor->currentValues($paragraphId);
    $bundle = $this->bundleOf($node, $field, $paragraphId);

    if ($bundle === '') {
      return $this->say($response, 'error', [
        $this->t('That section is not on this page. Pick one from the list.'),
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
   * The field the person chose, checked against what the form offered.
   *
   * NEVER THE SUBMITTED STRING ON ITS OWN. With one field there is no control
   * and nothing to read — it is the one. With several, the posted value is
   * only accepted when it appears in the map the form built, because that map
   * is the list of fields this person was shown and is allowed to write into.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<int|string, string> $fields
   *   The allow-list the form stored.
   *
   * @return string
   *   The field name, or an empty string when it was not one on offer.
   */
  private function chosenField(FormStateInterface $form_state, array $fields): string {
    if ($fields === []) {
      return '';
    }

    if (count($fields) === 1) {
      return (string) array_key_first($fields);
    }

    $chosen = self::asString($form_state->getValue('target_field'));

    return isset($fields[$chosen]) ? $chosen : '';
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

    $url = Url::fromRoute('node.revision_revert_confirm', [
      'node' => $nodeId,
      'node_revision' => $revisionId,
    ]);

    // OFFERED ONLY WHEN IT WOULD OPEN. That route needs "revert all revisions"
    // — or the per-type permission — which edit access does not imply. Core
    // enforces it either way, so this was never an access hole: it was
    // promising somebody an undo and handing them a 403 when they took it.
    if (!$url->access($this->currentUser())) {
      return '';
    }

    return $url->toString();
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
   * A submitted or stored value as trimmed text.
   *
   * FORM STATE IS `mixed`, AND CASTING IT BLIND IS THE BUG, not the type
   * complaint that reveals it. A posted value can be an array whatever the
   * form declared it to be, and `(string)` on one yields the literal string
   * "Array" with nothing but a warning — measured on PHP 8.5.10 — while an
   * object without __toString() throws outright. Every read of a submitted
   * value goes through here, because the quiet arm is the dangerous one.
   *
   * This is the idiom the module already uses — see Briefs::text() and
   * BundleProfiler — kept identical rather than reinvented, so there is one
   * way of reading an untrusted value in this codebase and not two.
   *
   * @param mixed $value
   *   The value as form state returned it.
   *
   * @return string
   *   The text, or an empty string when it was never text.
   */
  private static function asString(mixed $value): string {
    return is_scalar($value) ? trim((string) $value) : '';
  }

  /**
   * A submitted or stored value as a whole number.
   *
   * @param mixed $value
   *   The value as form state returned it.
   *
   * @return int
   *   The number, or zero when it was not one. Zero is already the "nothing
   *   was chosen" case at every call site, so it needs no separate signal.
   */
  private static function asInt(mixed $value): int {
    return is_numeric($value) ? (int) $value : 0;
  }

  /**
   * A stored map with nothing in it but strings.
   *
   * The two maps this form keeps in form state — field name => label, and
   * section id => field name — are both built by buildForm() and read back by
   * a callback. They are the allow-lists the security model rests on, so they
   * are re-shaped on the way out rather than trusted to still be what they
   * were: anything that is not a scalar is dropped instead of cast.
   *
   * @param mixed $value
   *   The value as form state returned it.
   *
   * @return array<int|string, string>
   *   The map, or an empty array when there was none.
   */
  private static function asStringMap(mixed $value): array {
    if (!is_array($value)) {
      return [];
    }

    $map = [];

    foreach ($value as $key => $item) {
      if (is_scalar($item)) {
        $map[$key] = (string) $item;
      }
    }

    return $map;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
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
   * @param list<string|\Stringable> $lines
   *   What to say.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  private function say(AjaxResponse $response, string $kind, array $lines): AjaxResponse {
    // CORE'S TEMPLATE, NOT HAND-BUILT MARKUP. The old version concatenated
    // `<div class="messages messages--…">` itself. It escaped each line, so it
    // was never injectable — but it bypassed `status_messages`, so a theme
    // that restyles messages never reached these, and they carried none of the
    // markup assistive technology expects from a Drupal message.
    $build = [
      '#theme' => 'status_messages',
      '#message_list' => [$kind => array_values($lines)],
      '#status_headings' => [
        'status' => $this->t('Status message'),
        'error' => $this->t('Error message'),
      ],
    ];

    return $response->addCommand(
      new HtmlCommand('#pb-ai-status', (string) $this->renderer->renderInIsolation($build)),
    );
  }

}
