<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Model;

/**
 * Whether the chat bar appears on a node form, and why not when it does not.
 *
 * THE REASON IS THE POINT. A surface that silently fails to appear is the
 * defect class this whole project keeps hitting: a person turns something on,
 * sees nothing, and has no way to find out which of five preconditions was the
 * missing one. Four separate things must be true before the bar can render —
 * a module, a setting, an assistant that still exists, and a permission — and
 * every one of them is invisible from the node form.
 *
 * So the decision carries its own explanation, and the settings form prints
 * it. "Nothing appears" stops being a mystery and becomes a sentence.
 */
final readonly class SurfaceDecision {

  /**
   * Constructs a decision.
   *
   * @param bool $show
   *   Whether to render the chat bar.
   * @param string $assistant
   *   The assistant config entity id to use, when showing.
   * @param string $reason
   *   Why it is not showing. Empty when $show is TRUE.
   */
  private function __construct(
    public bool $show,
    public string $assistant = '',
    public string $reason = '',
  ) {}

  /**
   * The bar should appear, using this assistant.
   *
   * @param string $assistant
   *   Assistant config entity id.
   *
   * @return self
   *   The decision.
   */
  public static function show(string $assistant): self {
    assert($assistant !== '', 'Showing the bar requires an assistant to show.');

    return new self(TRUE, $assistant);
  }

  /**
   * The bar stays hidden, and this is what to fix.
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
