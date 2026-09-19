<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

/**
 * Tells a person what to say, for the section they just chose.
 *
 * WHY THIS EXISTS. The maintainer's words: *"if the user asking for hero, they
 * dont know what fields they have, if you dont check and tell them what to fill
 * in each field, this is a mistake."* The Add dropdown names a section and then
 * offers one empty box, so somebody who has never opened that bundle's edit
 * form has no way of knowing a hero has a body AND a link title, or that an
 * accordion wants its rows named.
 *
 * SO THE PLACEHOLDER CHANGES WITH THE DROPDOWN, and it is built from the
 * bundle's real fields rather than from a list written by hand. A hand-written
 * map would be a second home for facts `BundleProfiler` already owns, and it
 * would be wrong the first time a site added a field.
 *
 * WHAT IT DELIBERATELY LEAVES OUT. Fields that need a person — a view, a
 * webform, a block — are already named by their own notice beside the dropdown;
 * saying them twice in two voices is worse than saying them once. Pictures are
 * left out because the module now supplies one. Measured 2026-09-17 across the
 * 33 parent bundles: 0 to 6 writable fields each, median 3, so a sentence
 * naming them all stays short enough to read.
 */
interface PromptHintsInterface {

  /**
   * One sentence telling a person what to say for this section.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   *
   * @return string
   *   The sentence, or an empty string when there is no such bundle.
   */
  public function forBundle(string $bundle): string;

  /**
   * The sentence for each of several bundles, keyed by machine name.
   *
   * @param list<string> $bundles
   *   Bundle machine names.
   *
   * @return array<string, string>
   *   Machine name => sentence.
   */
  public function forBundles(array $bundles): array;

}
