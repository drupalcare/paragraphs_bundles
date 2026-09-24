<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_icon\Service;

/**
 * Builds the Google Fonts stylesheet URL for a subset of Material Symbols.
 *
 * WHAT THIS IS FOR, in one number. The font bundled with this module is
 * 3,411,436 bytes for Outlined, because it carries all 4,284 icons. A page
 * draws three of them. Asking Google for only the icons actually used returns
 * 4,900 bytes with all four variable axes intact — measured 2026-09-22 against
 * the live API, and matching Google's own published figure.
 *
 * THE FOUR WAYS THIS GOES WRONG, all measured, all handled here:
 *
 * - `&icon_names=` with an empty value returns **HTTP 400** and an HTML error
 *   page, not CSS.
 * - Omitting `icon_names` entirely returns the **complete 344,712-byte** font.
 * - A name Google does not recognise is dropped silently, so the icon just
 *   does not appear.
 * - 🚨 **Past 4,096 bytes of `icon_names`, Google does not fail. It returns
 *   HTTP 200 and SILENTLY STOPS SUBSETTING**, serving the complete font —
 *   4,001,724 bytes, measured 2026-09-23, which is *heavier* than the
 *   3,411,436-byte file this module bundles. Every signal says success while
 *   the page gets bigger than shipping nothing at all. Bisected to the byte:
 *   4,092 still subsets, 4,114 does not. Past ~4,400 it finally 400s.
 *
 * So the contract is: hand this builder names that are already validated, and
 * when nothing valid survives it returns an empty string meaning *attach no
 * stylesheet at all*. Attaching nothing is correct — the bundled font is still
 * on disk as the fallback, which is what keeps this change different from the
 * one reverted in 4ea0ce5.
 */
interface PbiFontUrlBuilderInterface {

  /**
   * The variable-axis specification requested for every style.
   *
   * All four axes are kept at full range because this module exposes all four
   * as author fields (Fill, Weight, Grade, Optical Size). Pinning them would
   * be smaller — 1,760 bytes against 4,900 for three icons — but it would
   * silently stop those fields doing anything, and 2 of the 5 icon paragraphs
   * on the maintainer's own site use non-default axis values.
   */
  public const string AXES = 'opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200';

  /**
   * Largest `icon_names` value Google will actually subset, in bytes.
   *
   * Not a guess and not a round number chosen for comfort: measured against
   * the live API on 2026-09-23 by bisection. 4,092 bytes of names returns a
   * genuine subset via `/l/font?kit=`; 4,114 bytes returns HTTP 200 and the
   * complete font from `/s/materialsymbolsoutlined/`. 4,096 is the only
   * boundary consistent with both readings.
   *
   * Enforced as a BYTE length rather than an icon count, because icon names
   * are not a fixed width — `go` and `sentiment_very_dissatisfied` differ by
   * an order of magnitude, so a cap in icons is a cap that moves.
   */
  public const int MAX_ICON_NAMES_BYTES = 4096;

  /**
   * Build the stylesheet URL for one style and one set of icons.
   *
   * @param string $style
   *   PB style key: `outlined`, `rounded` or `sharp`.
   * @param list<string> $names
   *   Icon names, already validated and sorted.
   *
   * @return string
   *   Absolute https URL, or an empty string when there is nothing to
   *   request — an unknown style, or no names.
   */
  public function buildUrl(string $style, array $names): string;

  /**
   * The CSS font-family name for a PB style key.
   *
   * @param string $style
   *   PB style key.
   *
   * @return string
   *   Family name, or an empty string when the style is unknown.
   */
  public function familyName(string $style): string;

}
