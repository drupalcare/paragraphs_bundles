<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\paragraph_bundle_alert\Hook\PbaPreprocessAlertHook;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Guards the alert state contract across config, PHP, Twig and CSS.
 *
 * The state value travels through four files that know nothing about each
 * other: the allowed_values in field storage config, a whitelist constant in
 * the preprocess hook, an icon branch in the template, and a colour rule in
 * the stylesheet. Adding a fifth state to one of them and not the others fails
 * SILENTLY - the value saves, the class renders, and the alert simply has no
 * icon and no colour.
 *
 * This is the drift test for that. It is deliberately about the CONTRACT, not
 * about rendering: rendering is covered by PbAlertAriaTest.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
class PbAlertStateContractTest extends KernelTestBase {

  /**
   * {@inheritdoc}
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
   * The states this feature ships.
   */
  private const EXPECTED = ['success', 'info', 'warning', 'error'];

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
    // installOptionalConfig() is what puts the new field in place here. That
    // is the point: this is the FRESH-INSTALL path, the one the update hook
    // does not cover.
    $this->container->get('config.installer')->installOptionalConfig();
  }

  /**
   * The field's allowed values are exactly the states we ship.
   *
   * Also proves the config/optional install path works - the fresh-site route,
   * as opposed to the update hook that covers existing sites.
   */
  public function testAllowedValuesMatchTheShippedStates(): void {
    $storage = FieldStorageConfig::load('paragraph.pb_display_alert_state');
    $this->assertNotNull(
      $storage,
      'pb_display_alert_state storage was not installed from config/optional.'
    );

    $allowed = array_keys($storage->getSetting('allowed_values'));
    sort($allowed);
    $expected = self::EXPECTED;
    sort($expected);
    $this->assertSame($expected, $allowed);
  }

  /**
   * The preprocess whitelist matches the field's allowed values.
   *
   * A value that storage accepts but the hook rejects is dropped on the floor:
   * the editor picks a state, saves, and nothing happens.
   */
  public function testPreprocessWhitelistMatchesConfig(): void {
    $storage = FieldStorageConfig::load('paragraph.pb_display_alert_state');
    $this->assertNotNull($storage);
    $allowed = array_keys($storage->getSetting('allowed_values'));

    $reflection = new \ReflectionClass(PbaPreprocessAlertHook::class);
    $states = $reflection->getConstant('STATES');
    $this->assertIsArray($states, 'PbaPreprocessAlertHook::STATES is missing.');

    sort($allowed);
    sort($states);
    $this->assertSame(
      $allowed,
      $states,
      'The preprocess whitelist and the field allowed_values have drifted.'
    );
  }

  /**
   * Every state has an icon branch in the template and a colour in the CSS.
   */
  public function testEveryStateHasAnIconAndColour(): void {
    $module = \Drupal::service('extension.list.module')
      ->getPath('paragraph_bundle_alert');
    $twig = (string) file_get_contents(
      $module . '/templates/paragraph--alert-bundle.html.twig'
    );
    $css = (string) file_get_contents(
      $module . '/css/paragraph-bundle-alert.css'
    );

    $missingIcon = [];
    $missingColour = [];
    foreach (self::EXPECTED as $state) {
      // The last state is the {% else %} branch, so match either form.
      if (!str_contains($twig, "'" . $state . "'") && !str_contains($twig, 'else')) {
        $missingIcon[] = $state;
      }
      if (!str_contains($css, '.pb-alert--' . $state)) {
        $missingColour[] = $state;
      }
    }

    $this->assertSame([], $missingIcon, 'States with no icon branch in the template.');
    $this->assertSame([], $missingColour, 'States with no colour rule in the stylesheet.');
  }

}
