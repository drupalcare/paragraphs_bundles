<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_bundles_ai\Model\EditOutcome;

/**
 * Default editor.
 *
 * THE CHECK THAT MATTERS IS THE FIRST ONE. A paragraph id arriving from a form
 * is a number a person could change, and paragraph ids are sequential across
 * the whole site — so without proof that this section is ON THIS PAGE, the
 * form would be an editor for every paragraph the site has. The id is
 * therefore looked for among the node's own items, and anything else is
 * refused as not being here.
 *
 * REVISIONS ARE WRITTEN EXPLICITLY, in this order: a new paragraph revision
 * first, then the node's reference updated to point at it, then a new node
 * revision. Saving the node and hoping the paragraph follows leaves the old
 * revision pointing at mutated content, which quietly breaks the undo this
 * whole feature promises.
 * WHICH FIELDS HOLD WORDS IS NOT DECIDED HERE. It is a property of the field,
 * answered by FieldFact::isWords(), because three services used to keep their
 * own list of text types and three lists are three chances to disagree.
 */
final class SectionEditor implements SectionEditorInterface {

  use StringTranslationTrait;

  /**
   * Constructs the editor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Loads paragraphs.
   * @param \Drupal\paragraphs_bundles_ai\Service\BundleProfilerInterface $profiler
   *   Says which fields are content and what each accepts.
   * @param \Drupal\paragraphs_bundles_ai\Service\FieldDrafterInterface $drafter
   *   Fits text to the field's real limit, and says when it had to.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Checked for edit access on the page.
   * @param \Drupal\paragraphs_bundles_ai\Service\TextFormatsInterface $formats
   *   Keeps a formatted field's format when its words are replaced.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly BundleProfilerInterface $profiler,
    private readonly FieldDrafterInterface $drafter,
    private readonly AccountInterface $currentUser,
    private readonly TextFormatsInterface $formats,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function edit(NodeInterface $node, string $field, int $paragraphId, array $values): EditOutcome {
    if (!$node->access('update', $this->currentUser)) {
      return EditOutcome::refused([$this->t('You do not have permission to edit this page.')]);
    }

    if (!$node->hasField($field)) {
      return EditOutcome::refused([$this->t('This page has no field @field.', ['@field' => $field])]);
    }

    $delta = $this->deltaOf($node, $field, $paragraphId);

    if ($delta === NULL) {
      return EditOutcome::refused([
        $this->t('That section is not on this page. Pick one from the list rather than typing an id.'),
      ]);
    }

    $paragraph = $node->get($field)->get($delta)->entity;

    if (!$paragraph instanceof ParagraphInterface) {
      return EditOutcome::refused([$this->t('That section could not be loaded.')]);
    }

    $profile = $this->profiler->profile($paragraph->bundle());

    if ($profile === NULL) {
      return EditOutcome::refused([
        $this->t('The section type @section is not installed on this site any more.', [
          '@section' => $paragraph->bundle(),
        ]),
      ]);
    }

    $changes = [];
    $notes = [];
    $problems = [];

    foreach ($values as $name => $value) {
      $name = (string) $name;
      $fact = $profile->field($name);

      if ($fact === NULL) {
        $problems[] = $this->t('@section has no field "@field".', [
          '@section' => $profile->label,
          '@field' => $name,
        ]);
        continue;
      }

      if ($fact->tab !== 'content' || $fact->holdsSections) {
        $problems[] = $this->t('@field is not a field this can write to — only the words on the Content tab can be changed.', [
          '@field' => $name,
        ]);
        continue;
      }

      if (!$fact->isWords()) {
        $problems[] = $this->t('The "@field" field holds a @type, not words, so it cannot be changed from here.', [
          '@field' => $fact->label,
          '@type' => $fact->type,
        ]);
        continue;
      }

      if ($fact->allowedValues !== [] && !in_array((string) $value, $fact->allowedValues, TRUE)) {
        $problems[] = $this->t('@field only accepts: @values.', [
          '@field' => $name,
          '@values' => implode(', ', $fact->allowedValues),
        ]);
        continue;
      }

      // ->value, NOT getString(). On a formatted field getString() joins every
      // non-computed property with ", ", so a text_long came back as
      // "Our autumn range, basic_html" — measured on sts.local 2026-09-17.
      // That made $before permanently unequal to any clean $after, so the
      // "nothing actually changed" branch below could never be reached and a
      // no-op rewrite still wrote two new revisions.
      $before = trim((string) ($paragraph->get($name)->value ?? ''));
      $after = trim((string) $value);

      if ($after === '' || $after === $before) {
        continue;
      }

      $draft = $this->drafter->fit($paragraph->bundle(), $name, $after);

      if ($draft->usable) {
        $after = $draft->text;

        foreach ($draft->notes as $note) {
          $notes[] = $this->t('@field: @note', ['@field' => $name, '@note' => $note]);
        }
      }

      // THROUGH THE FORMAT HELPER, never the bare string. Passing $after
      // straight to set() replaces the whole item and drops the `format`
      // property — measured: 'basic_html' became NULL, and the body then
      // rendered through the fallback format, as escaped source.
      $paragraph->set($name, $this->formats->valueFor($paragraph->get($name), $after));
      $changes[$name] = ['from' => $before, 'to' => $after];
    }

    if ($problems !== []) {
      return EditOutcome::refused($problems);
    }

    if ($changes === []) {
      return EditOutcome::unchanged();
    }

    // See the class docblock: the paragraph's new revision first, then the
    // reference, then the node's. Any other order leaves the previous node
    // revision pointing at content that has already changed under it.
    $paragraph->setNewRevision(TRUE);
    $paragraph->save();

    $items = $node->get($field)->getValue();
    $items[$delta] = [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];
    $node->set($field, $items);

    $node->setNewRevision(TRUE);
    $node->setRevisionLogMessage(sprintf(
      'Reworded %s in section %d via Paragraphs Bundles AI.',
      implode(', ', array_keys($changes)),
      $paragraphId,
    ));
    $node->setRevisionUserId((int) $this->currentUser->id());
    $node->save();

    $notes[] = $this->t('Nothing was published, and no other section was touched.');

    return EditOutcome::changed((int) $node->getRevisionId(), $changes, $notes);
  }

  /**
   * {@inheritdoc}
   */
  public function currentValues(int $paragraphId): array {
    $paragraph = $this->entityTypeManager->getStorage('paragraph')->load($paragraphId);

    if (!$paragraph instanceof ParagraphInterface) {
      return [];
    }

    $profile = $this->profiler->profile($paragraph->bundle());

    if ($profile === NULL) {
      return [];
    }

    $out = [];

    foreach ($profile->contentFields as $name) {
      $fact = $profile->field($name);

      if ($fact === NULL || !$fact->isWords()) {
        continue;
      }

      // ->value, for the same reason as in edit(): getString() appends the
      // text format id, and these lines are read to the model as what the
      // section currently says. "basic_html" is not part of anybody's copy,
      // and a model shown it may well write it back.
      $value = trim((string) ($paragraph->get($name)->value ?? ''));

      if ($value !== '') {
        $out[$name] = $value;
      }
    }

    return $out;
  }

  /**
   * Where this section sits on this page, if it is on it at all.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The page.
   * @param string $field
   *   The paragraph field.
   * @param int $paragraphId
   *   The section being looked for.
   *
   * @return int|null
   *   The delta, or NULL when the section is not among this page's own items.
   */
  private function deltaOf(NodeInterface $node, string $field, int $paragraphId): ?int {
    foreach ($node->get($field)->getValue() as $delta => $item) {
      if ((int) ($item['target_id'] ?? 0) === $paragraphId) {
        return (int) $delta;
      }
    }

    return NULL;
  }

}
