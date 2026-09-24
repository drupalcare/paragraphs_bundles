<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_icon\Service;

/**
 * Default {@see PbiFontUrlBuilderInterface} implementation.
 */
final class PbiFontUrlBuilder implements PbiFontUrlBuilderInterface {

  /**
   * Google Fonts CSS endpoint.
   */
  private const string ENDPOINT = 'https://fonts.googleapis.com/css2';

  /**
   * PB style key => Material Symbols family name.
   *
   * These three keys are the allowed values of `pb_content_gms_style`, and the
   * family names must match the `font-family` the stylesheets and the
   * `.material-symbols-*` classes already use, or the CSS resolves to nothing
   * and every icon falls back to its ligature name in plain text.
   */
  private const array FAMILIES = [
    'outlined' => 'Material Symbols Outlined',
    'rounded' => 'Material Symbols Rounded',
    'sharp' => 'Material Symbols Sharp',
  ];

  /**
   * {@inheritdoc}
   */
  public function familyName(string $style): string {
    return self::FAMILIES[$style] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function buildUrl(string $style, array $names): string {
    $family = $this->familyName($style);
    if ($family === '' || $names === []) {
      return '';
    }

    // `display=block` rather than `swap`. With swap the browser paints the
    // fallback face while the icon font loads, and for a ligature icon font
    // the fallback paints the LIGATURE TEXT — a paragraph set to `home`
    // flashes the word "home". That is a brief version of the exact failure
    // that forced the revert in 4ea0ce5, so it is worth the short blank.
    $iconNames = implode(',', $names);

    // 🚨 TOO MANY ICONS DOES NOT FAIL LOUDLY, WHICH IS WHY THIS GUARD EXISTS.
    // Measured 2026-09-23: past MAX_ICON_NAMES_BYTES Google returns HTTP 200
    // and silently stops subsetting, serving the complete 4,001,724-byte font
    // — heavier than the 3,411,436-byte file bundled with this module. The
    // page looks perfect and is bigger than if this feature did nothing.
    //
    // Returning '' means "attach no subset", so the bundled @font-face is the
    // only face and renders every icon. Two reasons that beats truncating the
    // list: it is lighter than what Google would have served, and truncation
    // would LOSE icons — the external face still wins the family and would
    // simply lack the glyphs that were cut, with nothing to fall back to.
    if (strlen($iconNames) > self::MAX_ICON_NAMES_BYTES) {
      return '';
    }

    $query = sprintf(
      'family=%s:%s&icon_names=%s&display=block',
      str_replace('%20', '+', rawurlencode($family)),
      self::AXES,
      $iconNames,
    );

    return self::ENDPOINT . '?' . $query;
  }

}
