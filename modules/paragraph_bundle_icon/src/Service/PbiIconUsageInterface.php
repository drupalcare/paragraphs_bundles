<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_icon\Service;

/**
 * Which Material Symbols icons this site actually uses, grouped by style.
 *
 * WHY SITE-WIDE AND NOT PER-PAGE. A subset font is addressed by the exact set
 * of icons in it, so a different set is a different file and a separate browser
 * cache entry. Measured 2026-09-22: the same set requested twice returns an
 * identical font id, and adding one icon changes it. A per-PAGE subset would
 * therefore make a visitor re-download a font on every page whose icons differ,
 * while today's single bundled font is fetched once and reused everywhere. One
 * site-wide set is a single URL, cached across every page — and still tiny:
 * 30 icons with all four axes measured 33,823 bytes against 3,411,436 today.
 */
interface PbiIconUsageInterface {

  /**
   * The styles this module offers, matching `pb_content_gms_style`.
   *
   * Used as a whitelist rather than a description. A stored value reaches a
   * font-family name, and widget validation does not run for config import,
   * migration or JSON:API writes — so what is in the database is not
   * necessarily what the form would have allowed.
   */
  public const array STYLES = ['outlined', 'rounded', 'sharp'];

  /**
   * The style assumed when a paragraph has none stored.
   *
   * Matches the template, which defaults `pb_content_gms_style` to `outlined`.
   * A paragraph with no style row still renders an icon, so it must still
   * contribute that icon to a subset.
   */
  public const string DEFAULT_STYLE = 'outlined';

  /**
   * Every valid icon name in use, keyed by style.
   *
   * @return array<string, list<string>>
   *   Style key => sorted, unique, validated icon names. Styles with no valid
   *   icons are omitted entirely rather than present with an empty list, so a
   *   caller can treat "absent" as "change nothing".
   */
  public function iconsByStyle(): array;

}
