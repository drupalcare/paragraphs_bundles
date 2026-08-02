<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Css;

/**
 * Default {@see PbColorNormalizerInterface} implementation.
 */
final class PbColorNormalizer implements PbColorNormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function formatOpacity(string $raw): string {
    if ($raw === '') {
      return '1';
    }
    $value = (int) $raw;
    if ($value >= 100) {
      return '1';
    }
    if ($value <= 0) {
      return '0';
    }
    return '0.' . sprintf('%02d', $value);
  }

  /**
   * {@inheritdoc}
   */
  public function normaliseToRgbTriplet(string $value): string {
    if (!str_starts_with($value, '#')) {
      return $value;
    }
    $hex = ltrim($value, '#');
    if (strlen($hex) === 3) {
      $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6) {
      return $value;
    }
    $r = (int) hexdec(substr($hex, 0, 2));
    $g = (int) hexdec(substr($hex, 2, 2));
    $b = (int) hexdec(substr($hex, 4, 2));
    return sprintf('%d, %d, %d', $r, $g, $b);
  }

  /**
   * {@inheritdoc}
   */
  public function overlayRgba(string $bg, string $opacityRaw): string {
    $bg = trim($bg);
    if ($bg === '' || !$this->isSafeColorValue($bg)) {
      return '';
    }
    $opacity = $this->formatOpacity($opacityRaw);
    return sprintf(
      'rgba(%s, %s)',
      $this->normaliseToRgbTriplet($bg),
      $opacity,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isSafeColorValue(string $value): bool {
    $value = trim($value);
    if ($value === '') {
      return FALSE;
    }
    // Hex literal: #abc or #aabbcc.
    if (preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value) === 1) {
      return TRUE;
    }
    // Legacy widget output: comma-separated r,g,b triplet, 0-255 each.
    if (preg_match('/^\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}$/', $value) !== 1) {
      return FALSE;
    }
    foreach (explode(',', $value) as $channel) {
      if ((int) trim($channel) > 255) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function sanitizeCssValue(string $value): string {
    return str_replace(
      ['{', '}', ';', '<', '>', '/*', '*/', "\n", "\r"],
      '',
      $value,
    );
  }

}
