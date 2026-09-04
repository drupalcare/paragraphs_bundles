<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Model;

/**
 * What a write actually did — or why it did nothing.
 *
 * Every field here exists because a caller needs it to tell the truth
 * afterwards. `$revisionId` is not decoration: an edit that cannot be pointed
 * at is an edit that cannot be undone, and "I changed your page" without a way
 * back is the worst thing this module could say.
 */
final readonly class CreationResult {

  /**
   * Constructs a result.
   *
   * @param bool $created
   *   Whether anything was written.
   * @param int|null $nodeId
   *   The node written to, when created.
   * @param int|null $revisionId
   *   The new revision, when an existing node was edited. NULL for a new node,
   *   which has no prior revision to return to.
   * @param bool $isNewNode
   *   TRUE when the node itself was created by this call.
   * @param int $sections
   *   How many paragraphs were added.
   * @param list<string> $notes
   *   Anything the caller must be told — fields left empty, defaults applied.
   *   Never empty on success: silence about what was skipped is how a tool
   *   ends up trusted for work it did not do.
   * @param list<string> $refusals
   *   Why nothing was written. Empty when $created is TRUE.
   */
  private function __construct(
    public bool $created,
    public ?int $nodeId = NULL,
    public ?int $revisionId = NULL,
    public bool $isNewNode = FALSE,
    public int $sections = 0,
    public array $notes = [],
    public array $refusals = [],
  ) {}

  /**
   * A successful write.
   *
   * @param int $nodeId
   *   The node.
   * @param int|null $revisionId
   *   The new revision id, when an existing node was edited.
   * @param bool $isNewNode
   *   Whether the node was created here.
   * @param int $sections
   *   Paragraphs added.
   * @param list<string> $notes
   *   What the caller must be told.
   *
   * @return self
   *   The result.
   */
  public static function written(
    int $nodeId,
    ?int $revisionId,
    bool $isNewNode,
    int $sections,
    array $notes = [],
  ): self {
    return new self(TRUE, $nodeId, $revisionId, $isNewNode, $sections, array_values($notes));
  }

  /**
   * Nothing was written, and why.
   *
   * @param list<string> $refusals
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
