<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Css;

/**
 * Normalises paragraph colour field values for dynamic CSS emission.
 */
interface PbColorNormalizerInterface {

  /**
   * Convert opacity field storage (0–100) to CSS alpha (`1` or `0.NN`).
   *
   * @param string $raw
   *   Stored opacity value. Empty string means fully opaque.
   *
   * @return string
   *   `1`, `0`, or `0.NN`.
   */
  public function formatOpacity(string $raw): string;

  /**
   * Convert a hex color or pre-formatted triplet into `r, g, b`.
   *
   * @param string $value
   *   Hex (with or without `#`) or comma-separated rgb triplet.
   *
   * @return string
   *   `r, g, b` suitable for `rgba(...)`.
   */
  public function normaliseToRgbTriplet(string $value): string;

  /**
   * Build an `rgba(r, g, b, a)` colour from bg + opacity fields.
   *
   * @param string $bg
   *   Background colour (hex or rgb triplet).
   * @param string $opacityRaw
   *   Opacity field value (0–100). Empty means opaque.
   *
   * @return string
   *   Full `rgba(...)` value, or empty when bg is empty.
   */
  public function overlayRgba(string $bg, string $opacityRaw): string;

  /**
   * Whether a stored colour value is safe to emit into generated CSS.
   *
   * Colour fields accept exactly two shapes: a 3- or 6-digit hex literal
   * (`#abc`, `#aabbcc`) or a comma-separated `r,g,b` triplet of 0–255
   * integers. Anything else is rejected outright rather than sanitised —
   * a malformed colour has no valid rendering, so emitting a scrubbed
   * remnant would produce a broken declaration while implying the value
   * was honoured.
   *
   * This is the authoritative colour-shape check. It exists because the
   * widget's `#element_validate` only runs for form submissions; config
   * import, migration, REST/JSON:API and direct API writes all bypass it.
   *
   * @param string $value
   *   Raw stored field value.
   *
   * @return bool
   *   TRUE when the value is a well-formed colour.
   */
  public function isSafeColorValue(string $value): bool;

  /**
   * Strip characters that could break out of a CSS declaration or rule.
   *
   * Defence in depth for values that are not colours (lengths, units,
   * `url()`, `minmax()`, font-variation-settings). Removes declaration
   * and rule delimiters, comment delimiters, angle brackets — which would
   * otherwise allow escaping an inline `<style>` element — and newlines.
   *
   * Backslash and quotes are intentionally preserved: `url("…")` string
   * literals depend on them, and CSS hex escapes (`\7d`) resolve to
   * literal characters rather than structural tokens.
   *
   * @param string $value
   *   The resolved CSS value.
   *
   * @return string
   *   The value with structural characters removed.
   */
  public function sanitizeCssValue(string $value): string;

}
