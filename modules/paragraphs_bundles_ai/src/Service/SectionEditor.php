<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
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
 */
final class SectionEditor implements SectionEditorInterface {

  /**
   * Field types a person types words into.
   */
  private const TEXTUAL = ['string', 'string_long', 'text', 'text_long', 'list_string'];

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
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly BundleProfilerInterface $profiler,
    private readonly FieldDrafterInterface $drafter,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function edit(NodeInterface $node, string $field, int $paragraphId, array $values): EditOutcome {
    if (!$node->access('update', $this->currentUser)) {
      return EditOutcome::refused(['You do not have permission to edit this page.']);
    }

    if (!$node->hasField($field)) {
      return EditOutcome::refused([sprintf('This page has no field %s.', $field)]);
    }

    $delta = $this->deltaOf($node, $field, $paragraphId);

    if ($delta === NULL) {
      return EditOutcome::refused([
        'That section is not on this page. Pick one from the list rather than typing an id.',
      ]);
    }

    $paragraph = $node->get($field)->get($delta)->entity;

    if (!$paragraph instanceof ParagraphInterface) {
      return EditOutcome::refused(['That section could not be loaded.']);
    }

    $profile = $this->profiler->profile($paragraph->bundle());

    if ($profile === NULL) {
      return EditOutcome::refused([
        sprintf('The section type %s is not installed on this site any more.', $paragraph->bundle()),
      ]);
    }

    $changes = [];
    $notes = [];
    $problems = [];

    foreach ($values as $name => $value) {
      $name = (string) $name;
      $fact = $profile->field($name);

      if ($fact === NULL) {
        $problems[] = sprintf('%s has no field "%s".', $profile->label, $name);
        continue;
      }

      if ($fact->tab !== 'content' || $fact->holdsSections) {
        $problems[] = sprintf(
          '%s is not a field this can write to — only the words on the Content tab can be changed.',
          $name,
        );
        continue;
      }

      if (!in_array($fact->type, self::TEXTUAL, TRUE)) {
        $problems[] = sprintf('%s is a %s field, not text.', $name, $fact->type);
        continue;
      }

      if ($fact->allowedValues !== [] && !in_array((string) $value, $fact->allowedValues, TRUE)) {
        $problems[] = sprintf(
          '%s only accepts: %s.',
          $name,
          implode(', ', $fact->allowedValues),
        );
        continue;
      }

      $before = trim((string) $paragraph->get($name)->getString());
      $after = trim((string) $value);

      if ($after === '' || $after === $before) {
        continue;
      }

      $draft = $this->drafter->fit($paragraph->bundle(), $name, $after);

      if ($draft->usable) {
        $after = $draft->text;

        foreach ($draft->notes as $note) {
          $notes[] = $name . ': ' . $note;
        }
      }

      $paragraph->set($name, $after);
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

    $notes[] = 'Nothing was published, and no other section was touched.';

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

      if ($fact === NULL || $fact->holdsSections || !in_array($fact->type, self::TEXTUAL, TRUE)) {
        continue;
      }

      $value = trim((string) $paragraph->get($name)->getString());

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
