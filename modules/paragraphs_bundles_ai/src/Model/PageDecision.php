<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Model;

/**
 * Whether the assistant appears on a page being viewed, and why not.
 *
 * THE REASON IS THE POINT. Five things must be true before the assistant
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
   * @param array<string, string> $fields
   *   Every displayed paragraph field it may write into, machine name =>
   *   label. Empty when hiding.
   * @param string $reason
   *   Why it is not showing. Empty when $show is TRUE.
   */
  private function __construct(
    public bool $show,
    public array $fields = [],
    public string $reason = '',
  ) {}

  /**
   * The assistant should appear, offering these fields to write into.
   *
   * ALL OF THEM, NOT THE FIRST. A content type may have two paragraph fields —
   * a body and a footer, say. Until 2026-09-17 this carried a single string
   * chosen with `array_key_first()`, so every section landed in whichever
   * field the field manager happened to return first, and nothing on screen
   * said which. Both fields being valid targets, nothing refused it: the
   * section simply appeared in the wrong half of the page.
   *
   * @param array<string, string> $fields
   *   Displayed paragraph fields, machine name => label.
   *
   * @return self
   *   The decision.
   */
  public static function show(array $fields): self {
    assert($fields !== [], 'Showing the assistant requires a field to write into.');

    return new self(TRUE, $fields);
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

    // [], NOT ''. When $fields became an array on 2026-09-17 this line kept
    // passing the empty STRING the single-field version used, which is a
    // TypeError on every hidden surface — and decide() hides first for any
    // view mode that is not `full`, so every teaser and listing on the site
    // fataled. `php -l` cannot see it and no test touched the hide path.
    return new self(FALSE, [], $reason);
  }

}
