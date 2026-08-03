<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The alert never removes its own content on a timer the user cannot stop.
 *
 * WCAG 2.2.1 Timing Adjustable is a Level A criterion, and the alert's
 * `countdown` dismissal breached it: content disappeared on a time limit with
 * no way to turn it off, adjust it or extend it.
 *
 * The subtle half is which flag gates what. The countdown TIMER runs whenever
 * the dismissal type is `countdown` and the seconds are above zero. The
 * countdown REGION used to be gated on `show_countdown_label` as well — so an
 * alert with the numeric label switched off ran the timer and rendered no
 * control whatsoever. That configuration is the one asserted hardest here.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbAlertAriaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'file',
    'image',
    'link',
    'options',
    'views',
    'media',
    'media_library',
    'node',
    'datetime',
    'datetime_range',
    'entity_reference_revisions',
    'paragraphs',
    'field_group',
    'paragraphs_bundles',
    'paragraph_bundle_alert',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('media');
    $this->installConfig(['system', 'filter']);
    $this->container->get('config.installer')->installOptionalConfig();
  }

  /**
   * Dismissal configurations, and whether the countdown timer actually runs.
   *
   * @return array<string, array{string, int, bool, bool}>
   *   Dismissal type, seconds, show-label flag, whether the timer runs.
   */
  public static function dismissalProvider(): array {
    return [
      'countdown, label shown' => ['countdown', 30, TRUE, TRUE],
      // The configuration the bug shipped in.
      'countdown, label hidden' => ['countdown', 30, FALSE, TRUE],
      'countdown, zero seconds' => ['countdown', 0, TRUE, FALSE],
      'no dismissal' => ['none', 0, TRUE, FALSE],
    ];
  }

  /**
   * Wherever the countdown can run, a control to stop it is rendered.
   *
   * @param string $type
   *   Dismissal type.
   * @param int $seconds
   *   Countdown seconds.
   * @param bool $showLabel
   *   Whether the numeric label is enabled.
   * @param bool $timerRuns
   *   Whether the countdown timer runs in this configuration.
   */
  #[DataProvider('dismissalProvider')]
  public function testCountdownAlwaysHasPauseControl(
    string $type,
    int $seconds,
    bool $showLabel,
    bool $timerRuns,
  ): void {
    $html = $this->renderAlert($type, $seconds, $showLabel);
    $hasPause = str_contains($html, 'data-pb-countdown-pause');

    if ($timerRuns) {
      $this->assertTrue($hasPause, sprintf(
        'The countdown runs for dismissal "%s" at %d seconds (label %s), so a '
        . 'control that turns the limit off must render — WCAG 2.2.1. The '
        . 'control is gated on the timer, never on the label.',
        $type,
        $seconds,
        $showLabel ? 'shown' : 'hidden',
      ));
      $this->assertStringContainsString('aria-pressed="false"', $html);
      return;
    }

    $this->assertFalse($hasPause, 'No timer, so nothing to pause.');
  }

  /**
   * The close button advertises no disclosure state it cannot honour.
   *
   * Dismissal is one-way: closeAlert() marks the container `inert` and the
   * close button is inside it, so a collapsed-disclosure state described an
   * affordance that could never be reached again (WCAG 4.1.2).
   *
   * @param string $type
   *   Dismissal type.
   * @param int $seconds
   *   Countdown seconds.
   * @param bool $showLabel
   *   Whether the numeric label is enabled.
   */
  #[DataProvider('dismissalProvider')]
  public function testCloseButtonHasNoDisclosureState(
    string $type,
    int $seconds,
    bool $showLabel,
  ): void {
    $html = $this->renderAlert($type, $seconds, $showLabel);

    $this->assertStringNotContainsString('aria-expanded', $html);
    // The button itself is still there and still named.
    $this->assertStringContainsString('pb-alert__close', $html);
  }

  /**
   * Renders one alert with the given dismissal configuration.
   *
   * @param string $type
   *   Dismissal type.
   * @param int $seconds
   *   Countdown seconds.
   * @param bool $showLabel
   *   Whether the numeric label is enabled.
   *
   * @return string
   *   Rendered markup.
   */
  private function renderAlert(string $type, int $seconds, bool $showLabel): string {
    $paragraph = Paragraph::create([
      'type' => 'alert_bundle',
      'pb_display_alert_dismissal_type' => $type,
      'pb_display_alert_countdown_sec' => $seconds,
      'pb_display_alert_show_cdown_lbl' => $showLabel ? 1 : 0,
      'pb_display_show_close_button' => 1,
    ]);
    $paragraph->save();

    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('paragraph')
      ->view($paragraph, 'default');

    return (string) $this->container->get('renderer')->renderInIsolation($build);
  }

}
