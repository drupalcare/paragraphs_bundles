<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\paragraphs_bundles_ai\Model\DraftableField;
use Drupal\paragraphs_bundles_ai\Model\FieldDraft;

/**
 * Reports what a section can hold, and fits drafted text to it.
 *
 * THE PROBLEM THIS SOLVES. `create_sections` leaves empty sections behind by
 * design. What it cannot leave behind is knowledge: the fields on a paragraph
 * bundle are named by whoever built the site, so an assistant asked to "write
 * the headlines" is drafting for a field name it invented, at a length it
 * guessed. Both are wrong silently — the text reads fine right up until
 * someone tries to save it.
 *
 * IT NEVER WRITES, and that is not caution for its own sake. Text is the part
 * of this work a machine gets confidently wrong, and a bad headline that was
 * only ever shown to a person costs nothing to reject.
 */
final class FieldDrafter implements FieldDrafterInterface {

  /**
   * Field types holding text a person writes.
   *
   * The two families differ in one way that matters here: `text*` runs through
   * a text format and renders markup, `string*` does not and shows the tags.
   */
  private const TEXTUAL = [
    'string' => FALSE,
    'string_long' => FALSE,
    'text' => TRUE,
    'text_long' => TRUE,
    'text_with_summary' => TRUE,
  ];

  /**
   * Constructs the drafter.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Confirms a paragraph bundle exists before its fields are read.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Reads field definitions for the bundle.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function draftableFields(string $bundle): ?array {
    $bundle = trim($bundle);

    if ($bundle === '') {
      return NULL;
    }

    if ($this->entityTypeManager->getStorage('paragraphs_type')->load($bundle) === NULL) {
      return NULL;
    }

    $out = [];

    foreach ($this->entityFieldManager->getFieldDefinitions('paragraph', $bundle) as $name => $definition) {
      $storage = $definition->getFieldStorageDefinition();

      // Base fields — id, uuid, type, parent_id and the rest — are machinery,
      // not content. Offering them for drafting would invite writing into
      // them, and `parent_id` is not a place for a headline.
      if ($storage->isBaseField()) {
        continue;
      }

      $type = $definition->getType();

      if (!array_key_exists($type, self::TEXTUAL)) {
        continue;
      }

      $limit = $storage->getSetting('max_length');

      $out[(string) $name] = new DraftableField(
        (string) $name,
        (string) $definition->getLabel(),
        $type,
        is_numeric($limit) ? (int) $limit : NULL,
        self::TEXTUAL[$type],
        $definition->isRequired(),
      );
    }

    // Sorted so the answer reads the same on every site and every run. The
    // order Drupal happens to return config fields in is stable in practice
    // but guaranteed nowhere, and this list is read aloud to a person.
    ksort($out);

    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function fit(string $bundle, string $field, string $draft): FieldDraft {
    $fields = $this->draftableFields($bundle);

    if ($fields === NULL) {
      return FieldDraft::refused([
        sprintf(
          'There is no paragraph type "%s" on this site. Its module may not be enabled.',
          trim($bundle),
        ),
      ]);
    }

    $field = trim($field);

    if (!isset($fields[$field])) {
      // The two cases read differently on purpose. A field that exists but
      // holds an image is a misunderstanding about the section; a field that
      // does not exist is a typo or an invention.
      $all = $this->entityFieldManager->getFieldDefinitions('paragraph', $bundle);
      $exists = $field !== '' && isset($all[$field]);

      return FieldDraft::refused([
        $exists
          ? sprintf(
            'The field %s on %s is a %s field, so there is no text to draft for it.',
            $field,
            $bundle,
            $all[$field]->getType(),
        )
          : sprintf(
            'There is no text field "%s" on %s. It has: %s.',
            $field,
            $bundle,
            $fields === [] ? 'no text fields at all' : implode(', ', array_keys($fields)),
        ),
      ]);
    }

    $draft = trim($draft);

    if ($draft === '') {
      return FieldDraft::refused([
        sprintf('There is nothing to fit — no draft was supplied for %s.', $field),
      ]);
    }

    $definition = $fields[$field];
    $limit = $definition->maxLength;
    $original = mb_strlen($draft);
    $notes = [];

    if (!$definition->allowsHtml && $draft !== strip_tags($draft)) {
      // Not stripped. Rewriting someone's copy on a guess is worse than saying
      // what will happen, and the tags may well be intentional.
      $notes[] = sprintf(
        '%s is a plain text field — any markup here will be shown to visitors as tags, not rendered.',
        $field,
      );
    }

    if ($limit === NULL || $original <= $limit) {
      return FieldDraft::fitted($field, $draft, FALSE, $original, $limit, $notes);
    }

    $text = $this->shorten($draft, $limit);
    $notes[] = sprintf(
      'Shortened from %d characters to %d — %s holds at most %d. Check nothing important was at the end.',
      $original,
      mb_strlen($text),
      $field,
      $limit,
    );

    return FieldDraft::fitted($field, $text, TRUE, $original, $limit, $notes);
  }

  /**
   * Cuts text to a limit, preferring a word boundary.
   *
   * Uses the mb_ functions throughout: `substr()` cuts by byte and will split
   * a multi-byte character in half, which produces a broken glyph rather than
   * a short sentence — and on a site whose content is not English that is
   * every truncation, not an edge case.
   *
   * @param string $text
   *   The text.
   * @param int $limit
   *   Maximum characters.
   *
   * @return string
   *   The shortened text.
   */
  private function shorten(string $text, int $limit): string {
    $cut = mb_substr($text, 0, $limit);
    $space = mb_strrpos($cut, ' ');

    // Only honour a word boundary that is not absurdly early: a limit of 60
    // whose only space sits at character 3 would otherwise leave one word.
    if ($space !== FALSE && $space > (int) ($limit * 0.6)) {
      $cut = mb_substr($cut, 0, $space);
    }

    return rtrim($cut);
  }

}
