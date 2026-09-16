<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Model;

/**
 * Whether the assistant appears on a page being viewed, and why not.
 *
 * WHY NOT REUSE SurfaceDecision. That one answers a different question — which
 * assistant entity a chat bar should talk to — and it asserts an assistant id
 * on every show(). This surface drives the call itself, so there is no
 * assistant entity to name, and borrowing the type would mean inventing a
 * value to satisfy an assertion.
 *
 * THE REASON IS STILL THE POINT. Five things must be true before the assistant
 * can appear, and every one of them is invisible from the page: it must be a
 * node being viewed in full view mode, the type must have a paragraph field,
 * that field must actually be displayed, and the person must be allowed to
 * edit the page. A surface that silently fails to appear leaves someone
 * guessing which of the five is missing.
 */
final readonly class PageDecision {

  /**
   * Constructs a decision.
   *
   * @param bool $show
   *   Whether to render the assistant.
   * @param string $field
   *   The paragraph field it will write into, when showing. Empty otherwise.
   * @param string $reason
   *   Why it is not showing. Empty when $show is TRUE.
   */
  private function __construct(
    public bool $show,
    public string $field = '',
    public string $reason = '',
  ) {}

  /**
   * The assistant should appear, writing into this field.
   *
   * @param string $field
   *   Paragraph field machine name on the node.
   *
   * @return self
   *   The decision.
   */
  public static function show(string $field): self {
    assert($field !== '', 'Showing the assistant requires a field to write into.');

    return new self(TRUE, $field);
  }

  /**
   * It stays hidden, and this is what to fix.
   *
   * @param string $reason
   *   A complete sentence naming the missing precondition.
   *
   * @return self
   *   The decision.
   */
  public static function hide(string $reason): self {
    assert($reason !== '', 'A hidden surface must say what is missing.');

    return new self(FALSE, '', $reason);
  }

}
