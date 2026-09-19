<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Model;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * What changed in one section, and what it was before.
 *
 * THE BEFORE VALUES ARE THE POINT. An edit that only reports success leaves a
 * person unable to see what the assistant did to their words, and unable to
 * judge whether to keep it. Every changed field carries its old value, so the
 * chat can show "Title: 'Autumn range' → 'Autumn collection'" and the revision
 * number that puts it back.
 */
final readonly class EditOutcome {

  /**
   * Constructs an outcome.
   *
   * @param bool $changed
   *   Whether anything was written.
   * @param int|null $revisionId
   *   The node revision that holds the change, for undo.
   * @param array<string, array{from: string, to: string}> $fields
   *   Field machine name => what it was and what it is now.
   * @param list<string|\Stringable> $notes
   *   Anything the person must be told, such as text that had to be shortened.
   * @param list<string|\Stringable> $refusals
   *   Why nothing changed. Empty when $changed is TRUE.
   */
  private function __construct(
    public bool $changed,
    public ?int $revisionId = NULL,
    public array $fields = [],
    public array $notes = [],
    public array $refusals = [],
  ) {}

  /**
   * The section was changed.
   *
   * @param int $revisionId
   *   The new node revision.
   * @param array<string, array{from: string, to: string}> $fields
   *   What changed.
   * @param list<string|\Stringable> $notes
   *   What the person must be told.
   *
   * @return self
   *   The outcome.
   */
  public static function changed(int $revisionId, array $fields, array $notes = []): self {
    return new self(TRUE, $revisionId, $fields, array_values($notes));
  }

  /**
   * Nothing changed, and why.
   *
   * @param list<string|\Stringable> $refusals
   *   Complete sentences a person can act on.
   *
   * @return self
   *   The outcome.
   */
  public static function refused(array $refusals): self {
    assert($refusals !== [], 'A refusal must say why.');

    return new self(FALSE, NULL, [], [], array_values($refusals));
  }

  /**
   * Nothing needed changing, which is not a failure.
   *
   * A model asked to make a title warmer may return the title it was given.
   * Writing a revision for that would fill the revision list with edits that
   * changed nothing, and telling the person it worked would be a small lie.
   *
   * @return self
   *   The outcome.
   */
  public static function unchanged(): self {
    // A STATIC FACTORY HAS NO $this, so this is the one place a
    // TranslatableMarkup is constructed directly rather than through t().
    // Leaving it a bare string because the trait was not reachable here is how
    // a single untranslated sentence survives a whole translation pass.
    return new self(FALSE, NULL, [], [
      new TranslatableMarkup('Nothing changed — the wording that came back was the same.'),
    ], []);
  }

}
