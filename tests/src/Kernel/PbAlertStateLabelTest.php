<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The alert's Show state label option prints the state word once (P9).
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbAlertStateLabelTest extends KernelTestBase {

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
    'datetime',
    'views',
    'media',
    'media_library',
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
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('media');
    $this->installConfig(['system']);
    $this->container->get('config.installer')->installOptionalConfig();
  }

  /**
   * Renders one alert paragraph with the given values.
   *
   * @param array<string, mixed> $values
   *   Field values.
   */
  private function renderAlert(array $values): string {
    $paragraph = Paragraph::create(['type' => 'alert_bundle'] + $values);
    $paragraph->save();
    $build = $this->container->get('entity_type.manager')->getViewBuilder('paragraph')->view($paragraph);
    return (string) $this->container->get('renderer')->renderRoot($build);
  }

  /**
   * Visible label only when on with a state; the word appears exactly once.
   */
  public function testStateLabel(): void {
    $field = 'pb_display_alert_show_state_lbl';
    $cases = [
      'default' => [['pb_display_alert_state' => 'warning'], FALSE, 1],
      'off' => [['pb_display_alert_state' => 'warning', $field => 0], FALSE, 1],
      'on' => [['pb_display_alert_state' => 'warning', $field => 1], TRUE, 1],
      'on, no state' => [[$field => 1], FALSE, 0],
    ];
    foreach ($cases as $label => [$values, $visible, $count]) {
      $html = $this->renderAlert($values);
      $this->assertStringContainsString('pb-alert', $html, "$label renders the alert.");
      $this->assertSame($visible, str_contains($html, 'class="pb-alert__state-label"'), "$label: visible label.");
      $this->assertSame($visible, str_contains($html, 'pb-alert--has-state-label'), "$label: root class.");
      $this->assertSame($count, substr_count($html, 'Warning: '), "$label: state word count.");
      if ($count === 1) {
        $hidden = str_contains($html, '<span class="visually-hidden">Warning: </span>');
        $this->assertSame(!$visible, $hidden, "$label: hidden word only when off.");
      }
    }
  }

}
