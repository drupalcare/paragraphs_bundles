<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Model;

/**
 * What a model proposed for one section, before anything is written.
 *
 * A draft is a PROPOSAL. Nothing here has touched a page: the values still
 * have to survive the composer's checks, which are the same checks a tool call
 * would face. Keeping the two steps apart means a model that answers with a
 * field name that does not exist produces a refusal, not a write.
 */
final readonly class DraftedSection {

  /**
   * Constructs a draft.
   *
   * @param bool $usable
   *   Whether there is anything worth writing.
   * @param array<string, mixed> $values
   *   Field machine name => proposed value, for the section itself.
   * @param array<string, list<array<string, mixed>>> $children
   *   Child sections keyed by the field they go in, each ['bundle' => string,
   *   'values' => array] and, one level down, its own 'children' the same way.
   * @param list<string|\Stringable> $refusals
   *   Why there is no draft. Empty when $usable is TRUE.
   * @param string $model
   *   The model that answered, for the line shown to the person.
   */
  private function __construct(
    public bool $usable,
    public array $values = [],
    public array $children = [],
    public array $refusals = [],
    public string $model = '',
  ) {}

  /**
   * A usable draft.
   *
   * @param array<string, mixed> $values
   *   Proposed values.
   * @param array<string, list<array<string, mixed>>> $children
   *   Proposed children, keyed by the field they go in.
   * @param string $model
   *   The model that answered.
   *
   * @return self
   *   The draft.
   */
  public static function of(array $values, array $children, string $model): self {
    return new self(TRUE, $values, $children, [], $model);
  }

  /**
   * No draft, and why.
   *
   * @param list<string|\Stringable> $refusals
   *   Complete sentences a person can act on.
   *
   * @return self
   *   The draft.
   */
  public static function refused(array $refusals): self {
    assert($refusals !== [], 'A refusal must say why.');

    return new self(FALSE, [], [], array_values($refusals));
  }

}
