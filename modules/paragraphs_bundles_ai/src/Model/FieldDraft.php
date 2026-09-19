<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Model;

/**
 * A piece of drafted text, fitted to the field it is meant for.
 *
 * NOTHING HERE IS SAVED. The draft comes back for a person to read and accept;
 * putting it in the page stays a human action. That is the brief's line and it
 * is also the honest one — text is the part of this job a machine gets
 * confidently wrong, and a wrong headline that was never written is a
 * non-event.
 *
 * WHY $trimmed IS A FIELD AND NOT A DETAIL. Silently shortening someone's copy
 * to fit a limit is the failure this whole class guards: the text reads fine,
 * the last clause is gone, and nobody finds out until it is published.
 */
final readonly class FieldDraft {

  /**
   * Constructs a draft result.
   *
   * @param bool $usable
   *   Whether there is text to show.
   * @param string $field
   *   The field the text is for.
   * @param string $text
   *   The fitted text.
   * @param bool $trimmed
   *   TRUE when the draft was longer than the field allows and was shortened.
   * @param int $originalLength
   *   Character count before fitting.
   * @param int|null $limit
   *   The field's limit, or NULL when it has none.
   * @param list<string|\Stringable> $notes
   *   Anything the caller must be told about the fit.
   * @param list<string|\Stringable> $refusals
   *   Why there is no draft. Empty when $usable is TRUE.
   */
  private function __construct(
    public bool $usable,
    public string $field = '',
    public string $text = '',
    public bool $trimmed = FALSE,
    public int $originalLength = 0,
    public ?int $limit = NULL,
    public array $notes = [],
    public array $refusals = [],
  ) {}

  /**
   * Text that fits the field.
   *
   * @param string $field
   *   Field machine name.
   * @param string $text
   *   The fitted text.
   * @param bool $trimmed
   *   Whether it had to be shortened.
   * @param int $originalLength
   *   Length before fitting.
   * @param int|null $limit
   *   The field's limit.
   * @param list<string|\Stringable> $notes
   *   What the caller must be told.
   *
   * @return self
   *   The draft.
   */
  public static function fitted(
    string $field,
    string $text,
    bool $trimmed,
    int $originalLength,
    ?int $limit,
    array $notes = [],
  ): self {
    return new self(TRUE, $field, $text, $trimmed, $originalLength, $limit, array_values($notes));
  }

  /**
   * No draft, and why.
   *
   * @param list<string|\Stringable> $refusals
   *   Complete sentences a person can act on.
   *
   * @return self
   *   The result.
   */
  public static function refused(array $refusals): self {
    assert($refusals !== [], 'A refusal must say why.');

    return new self(FALSE, refusals: array_values($refusals));
  }

}
