<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_alert\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Preprocess hook — alert dismissal, date range, and cache max-age.
 */
final class PbaPreprocessAlertHook {

  /**
   * Implements hook_preprocess_paragraph__alert_bundle().
   *
   * @param array<string, mixed> $variables
   *   Render variables (passed by reference).
   */
  #[Hook('preprocess_paragraph__alert_bundle')]
  public function preprocessAlertBundle(array &$variables): void {
    $paragraph = $variables['paragraph'] ?? NULL;
    if (!$paragraph instanceof ParagraphInterface) {
      return;
    }

    $variables['pb_default_state'] = 'open';
    $variables['pb_alert_hidden'] = FALSE;

    if (!$paragraph->hasField('pb_display_alert_dismissal_type')) {
      return;
    }

    $dismissal_type = $paragraph->get('pb_display_alert_dismissal_type')->value ?? 'none';
    $variables['pb_dismissal_type'] = $dismissal_type;
    $variables['pb_countdown_seconds'] = 0;
    if ($paragraph->hasField('pb_display_alert_countdown_sec')
      && !$paragraph->get('pb_display_alert_countdown_sec')->isEmpty()) {
      $seconds = $paragraph->get('pb_display_alert_countdown_sec')->value;
      $variables['pb_countdown_seconds'] = is_scalar($seconds) ? (int) $seconds : 0;
    }
    $variables['pb_show_countdown_label'] = $paragraph->hasField('pb_display_alert_show_cdown_lbl')
      && !$paragraph->get('pb_display_alert_show_cdown_lbl')->isEmpty()
      && $paragraph->get('pb_display_alert_show_cdown_lbl')->value;
    $variables['pb_close_persist'] = $paragraph->hasField('pb_display_alert_close_persist')
      ? ($paragraph->get('pb_display_alert_close_persist')->value ?? 'none')
      : 'none';

    $variables['pb_date_range_end'] = 0;

    if ($dismissal_type !== 'date_range') {
      return;
    }

    // Every other field read in this method is guarded by hasField(); these two
    // were not, so a site that had deleted either field fataled on any alert
    // set to 'date_range'.
    $start = $this->dateValue($paragraph, 'pb_display_alert_start_date');
    $end = $this->dateValue($paragraph, 'pb_display_alert_end_date');
    $utc = new \DateTimeZone('UTC');
    $now_ts = time();
    $start_ts = 0;
    $end_ts = 0;
    if ($start !== NULL) {
      $start_dt = new DrupalDateTime($start, $utc);
      if (!$start_dt->hasErrors()) {
        $start_ts = $start_dt->getTimestamp();
      }
    }
    if ($end !== NULL) {
      $end_dt = new DrupalDateTime($end, $utc);
      if (!$end_dt->hasErrors()) {
        $end_ts = $end_dt->getTimestamp();
      }
    }

    if ($start_ts && $end_ts && $start_ts > $end_ts) {
      $variables['pb_alert_hidden'] = TRUE;
      $variables['pb_default_state'] = 'closed';
      return;
    }

    if ($start_ts && $now_ts < $start_ts) {
      $variables['pb_alert_hidden'] = TRUE;
      $variables['pb_default_state'] = 'closed';
      $this->mergeMaxAge($variables, $start_ts - $now_ts);
      return;
    }

    if ($end_ts && $now_ts > $end_ts) {
      $variables['pb_alert_hidden'] = TRUE;
      $variables['pb_default_state'] = 'closed';
      return;
    }

    $variables['pb_date_range_end'] = $end_ts;
    if ($end_ts && $now_ts <= $end_ts) {
      $this->mergeMaxAge($variables, $end_ts - $now_ts);
    }
  }

  /**
   * Reads a date field as a string, or NULL when it is absent or empty.
   */
  private function dateValue(ParagraphInterface $paragraph, string $field): ?string {
    if (!$paragraph->hasField($field) || $paragraph->get($field)->isEmpty()) {
      return NULL;
    }
    $value = $paragraph->get($field)->value;

    return is_string($value) && $value !== '' ? $value : NULL;
  }

  /**
   * Lowers the render array's max-age, never raising it.
   *
   * @param array<string, mixed> $variables
   *   Render variables (passed by reference).
   * @param int $max_age
   *   The max-age to merge in, in seconds.
   */
  private function mergeMaxAge(array &$variables, int $max_age): void {
    $cache = $variables['#cache'] ?? NULL;
    $cache = is_array($cache) ? $cache : [];
    $current = $cache['max-age'] ?? Cache::PERMANENT;
    $cache['max-age'] = Cache::mergeMaxAges(
      is_int($current) ? $current : Cache::PERMANENT,
      $max_age,
    );
    $variables['#cache'] = $cache;
  }

}
