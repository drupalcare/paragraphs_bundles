<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_alert\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Field widget hook — site timezone for alert date-range fields.
 */
final class PbaFieldWidgetHook {

  use StringTranslationTrait;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_field_widget_single_element_form_alter().
   *
   * @param array<string, mixed> $element
   *   Widget form element (passed by reference).
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   * @param array<string, mixed> $context
   *   Widget context.
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(
    array &$element,
    FormStateInterface $form_state,
    array $context,
  ): void {
    $items = $context['items'] ?? NULL;
    if (!$items instanceof FieldItemListInterface) {
      return;
    }

    $field_name = $items->getFieldDefinition()->getName();
    if (!in_array($field_name, [
      'pb_display_alert_start_date',
      'pb_display_alert_end_date',
    ], TRUE)) {
      return;
    }

    $value = $element['value'] ?? NULL;
    if (!is_array($value)) {
      return;
    }

    // A site whose `timezone.default` is unset, blank or not a string leaves
    // the widget on the server timezone rather than fataling: DateTimeZone
    // throws on anything PHP does not recognise, and this runs on every
    // alert paragraph form.
    $site_timezone = $this->siteTimezone();

    $value['#date_timezone'] = $site_timezone;
    $value['#description'] = $this->t('Timezone: @tz', [
      '@tz' => $site_timezone,
    ]);
    $default = $value['#default_value'] ?? NULL;
    if ($default instanceof DrupalDateTime) {
      $default->setTimezone(new \DateTimeZone($site_timezone));
    }
    $element['value'] = $value;
  }

  /**
   * The configured site timezone, falling back to UTC.
   *
   * @return string
   *   A timezone identifier PHP can construct a DateTimeZone from.
   */
  private function siteTimezone(): string {
    $configured = $this->configFactory->get('system.date')->get('timezone.default');
    if (!is_string($configured) || $configured === '') {
      return 'UTC';
    }
    return in_array($configured, \DateTimeZone::listIdentifiers(), TRUE)
      ? $configured
      : 'UTC';
  }

}
