<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Model;

/**
 * The answer to "where should these sections go", or why that is not yet known.
 *
 * Deliberately not a bare array. A resolution is one of exactly two things — a
 * usable target, or a list of questions that must be answered first — and a
 * value object makes a caller state which it is rather than test for the
 * presence of keys.
 *
 * WHY QUESTIONS ARE A LIST AND NOT A SINGLE STRING. The resolver returns
 * EVERY unmet precondition at once so the assistant can ask one combined
 * question. Asking one item per turn is what makes a conversational tool
 * exhausting to use: three round trips to learn a content type, a field and a
 * count that could have been asked for together.
 */
final readonly class TargetResolution {

  /**
   * Constructs a resolution.
   *
   * @param bool $resolved
   *   TRUE when $contentType and $field are both usable.
   * @param string|null $contentType
   *   The node bundle to create on, when resolved.
   * @param string|null $field
   *   The paragraph reference field to write into, when resolved.
   * @param string|null $bundle
   *   The paragraph bundle that was validated against the field, when
   *   resolved. Carried back so a caller can hand the whole target on without
   *   re-deriving any part of it.
   * @param list<string> $questions
   *   Everything the caller must answer, when not resolved. Each entry is a
   *   complete sentence a person can act on, not an error code.
   */
  private function __construct(
    public bool $resolved,
    public ?string $contentType = NULL,
    public ?string $field = NULL,
    public ?string $bundle = NULL,
    public array $questions = [],
  ) {}

  /**
   * A usable target.
   *
   * @param string $contentType
   *   The node bundle.
   * @param string $field
   *   The paragraph reference field name.
   * @param string $bundle
   *   The paragraph bundle, already checked against the field.
   *
   * @return self
   *   The resolution.
   */
  public static function target(string $contentType, string $field, string $bundle): self {
    return new self(TRUE, $contentType, $field, $bundle);
  }

  /**
   * A target that cannot be determined yet, and why.
   *
   * @param list<string> $questions
   *   One or more complete sentences naming what is missing or wrong.
   *
   * @return self
   *   The resolution.
   */
  public static function blocked(array $questions): self {
    // A blocked resolution with nothing to ask would leave the caller stuck
    // with no way forward, which is the one outcome this class exists to make
    // impossible.
    assert($questions !== [], 'A blocked resolution must carry at least one question.');

    return new self(FALSE, NULL, NULL, NULL, array_values($questions));
  }

}
