<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Model;

/**
 * One place a section holds other sections, and what may go in it.
 *
 * THE FIRST BUNDLE IS THE DEFAULT. The rest are what a person may ask for
 * instead, never anything outside the list: a slot is filled from a choice
 * somebody made, not from whatever the model reaches for.
 */
final readonly class ChildSlot {

  /**
   * Constructs a slot.
   *
   * @param string $field
   *   The paragraph field machine name.
   * @param non-empty-list<string> $bundles
   *   Paragraph bundles allowed here, best first.
   */
  public function __construct(
    public string $field,
    public array $bundles,
  ) {}

  /**
   * The bundle used unless the person asks for another.
   *
   * @return string
   *   A paragraph bundle machine name.
   */
  public function default(): string {
    return $this->bundles[0];
  }

  /**
   * Whether a bundle may go in this slot.
   *
   * @param string $bundle
   *   A paragraph bundle machine name.
   *
   * @return bool
   *   TRUE when it is one of the slot's bundles.
   */
  public function allows(string $bundle): bool {
    return in_array($bundle, $this->bundles, TRUE);
  }

}
