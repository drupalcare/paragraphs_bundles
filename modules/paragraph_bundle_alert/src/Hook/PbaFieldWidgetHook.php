<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_alert\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
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
    if ($items === NULL) {
      return;
    }

    $field_name = $items->getFieldDefinition()->getName();
    if (!in_array($field_name, [
      'pb_display_alert_start_date',
      'pb_display_alert_end_date',
    ], TRUE)) {
      return;
    }

    $site_timezone = $this->configFactory->get('system.date')
      ->get('timezone.default') ?: 'UTC';
    if (!isset($element['value'])) {
      return;
    }

    $element['value']['#date_timezone'] = $site_timezone;
    $element['value']['#description'] = $this->t('Timezone: @tz', [
      '@tz' => $site_timezone,
    ]);
    if (!empty($element['value']['#default_value'])
      && $element['value']['#default_value'] instanceof DrupalDateTime) {
      $element['value']['#default_value']->setTimezone(
        new \DateTimeZone($site_timezone)
      );
    }
  }

}
