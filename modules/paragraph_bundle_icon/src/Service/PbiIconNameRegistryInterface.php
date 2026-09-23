<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_icon\Service;

/**
 * Knows which Material Symbols icon names are real.
 *
 * WHY THIS EXISTS. `pb_content_gms_icon_name` is a plain string field with no
 * `allowed_values`, so an author can type anything, and what they type is sent
 * to Google as part of a subsetted font request. Measured 2026-09-22 against
 * the live API, an unchecked name fails in two different ways and neither one
 * announces itself:
 *
 * - A bad name ALONGSIDE good ones is silently dropped. The request succeeds,
 *   the font is correct for every other icon, and the offending icon simply
 *   does not render. Nothing is logged anywhere.
 * - A request where EVERY name is invalid falls back to the complete font:
 *   344,712 bytes instead of ~1,700. The page still works, so nobody notices
 *   that one typo made it 200x heavier.
 *
 * Both are cheap to prevent and expensive to debug, which is what a validator
 * is for.
 */
interface PbiIconNameRegistryInterface {

  /**
   * Is this a real Material Symbols icon name?
   *
   * @param string $name
   *   Candidate name as the author typed it.
   *
   * @return bool
   *   TRUE when the name exists in the shipped list.
   */
  public function isValid(string $name): bool;

  /**
   * Reduce author input to names Google will actually accept.
   *
   * Trims, drops empties and unknowns, de-duplicates, and sorts. Sorting is
   * what Google's documentation asks for; it is not required for correctness
   * (their API normalises order — measured: the same set in a different order
   * returns an identical font id), but it makes OUR cache key stable, which
   * is the reason to keep doing it.
   *
   * @param list<string> $names
   *   Raw names, in any order, possibly with duplicates or rubbish.
   *
   * @return list<string>
   *   Valid names, unique, sorted. Empty when nothing survives.
   */
  public function filter(array $names): array;

  /**
   * Every known icon name.
   *
   * @return list<string>
   *   Sorted list of all shipped names.
   */
  public function all(): array;

}
