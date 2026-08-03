<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit;

use Drupal\Core\Entity\Display\EntityDisplayInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * The update_9008 realign pass: shipped layout wins, site additions survive.
 *
 * The bug this guards against is invisible to every other suite, because it
 * only exists on the upgrade path. `update_9006` wires the shared v2 fields
 * onto displays that already exist, so it computes weights from what it finds;
 * a fresh install imports the weights from `config/optional`. The two then
 * disagree, and on most bundles the disagreement is large enough to reorder
 * the edit form. Same module version, two different admin UIs.
 *
 * The realign pass exists to close that gap, and it has to close it without
 * bulldozing the site: a field the site added, a component the site chose to
 * hide, and a field the site dragged into one of this module's groups all have
 * to come out the other side untouched. Those three are as much of the
 * contract as the convergence is, so they are asserted here too.
 */
#[Group('paragraphs_bundles')]
final class PbDisplayRealignTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    require_once dirname(__DIR__, 3) . '/paragraphs_bundles.install';
  }

  /**
   * A display entity backed by a plain array, so get()/set() round-trip.
   *
   * @param array<string, mixed> $data
   *   Initial config values; updated in place by set().
   *
   * @return \Drupal\Core\Entity\Display\EntityDisplayInterface
   *   The double.
   */
  private function display(array &$data): EntityDisplayInterface {
    $display = $this->createMock(EntityDisplayInterface::class);
    $display->method('get')->willReturnCallback(
      static fn(string $key): mixed => $data[$key] ?? NULL,
    );
    $display->method('set')->willReturnCallback(
      static function (string $key, mixed $value) use (&$data, $display): EntityDisplayInterface {
        $data[$key] = $value;
        return $display;
      },
    );
    return $display;
  }

  /**
   * Runs the realign pass, returning [changed, data, stats].
   *
   * @param array<string, mixed> $active
   *   Active display config.
   * @param array<string, mixed> $shipped
   *   Shipped config/optional file contents.
   *
   * @return array{bool, array<string, mixed>, array<string, int>}
   *   Whether anything changed, the resulting config, and the counters.
   */
  private function realign(array $active, array $shipped): array {
    $stats = ['weights' => 0, 'unhidden' => 0, 'groups' => 0];
    $display = $this->display($active);
    $changed = paragraphs_bundles_pb_realign_display($display, $shipped, $stats);
    return [$changed, $active, $stats];
  }

  /**
   * A weight the upgrade path computed is replaced by the shipped weight.
   */
  public function testDriftedWeightIsRealigned(): void {
    [$changed, $data, $stats] = $this->realign(
      ['content' => ['pb_display_padding' => ['weight' => 17, 'region' => 'content']]],
      ['content' => ['pb_display_padding' => ['weight' => 28]]],
    );

    $this->assertTrue($changed);
    $this->assertSame(28, $data['content']['pb_display_padding']['weight']);
    $this->assertSame(1, $stats['weights']);
    // Everything else about the component is left alone.
    $this->assertSame('content', $data['content']['pb_display_padding']['region']);
  }

  /**
   * A component in BOTH content and hidden loses the hidden entry.
   *
   * EntityDisplayBase::setComponent() unsets `hidden`; the v1 wiring path wrote
   * straight into the `content` array and did not. preSave() only ksorts, so
   * the contradiction survives every later save and every config export.
   */
  public function testStaleHiddenEntryIsRemoved(): void {
    [$changed, $data, $stats] = $this->realign(
      [
        'content' => ['pb_display_link' => ['weight' => 23]],
        'hidden' => ['pb_display_link' => TRUE, 'pb_content_body' => TRUE],
      ],
      ['content' => ['pb_display_link' => ['weight' => 23]]],
    );

    $this->assertTrue($changed);
    $this->assertSame(1, $stats['unhidden']);
    $this->assertArrayNotHasKey('pb_display_link', $data['hidden']);
    // A genuinely hidden component is not disturbed.
    $this->assertArrayHasKey('pb_content_body', $data['hidden']);
  }

  /**
   * A component this module does not ship keeps whatever weight it has.
   */
  public function testSiteAddedComponentIsNotTouched(): void {
    [$changed, $data] = $this->realign(
      [
        'content' => [
          'pb_display_padding' => ['weight' => 28],
          'field_site_custom' => ['weight' => 4],
        ],
      ],
      ['content' => ['pb_display_padding' => ['weight' => 28]]],
    );

    $this->assertFalse($changed);
    $this->assertSame(4, $data['content']['field_site_custom']['weight']);
  }

  /**
   * A shipped component the site has hidden is not made visible again.
   */
  public function testHiddenComponentIsNotRestored(): void {
    [$changed, $data, $stats] = $this->realign(
      ['content' => [], 'hidden' => ['pb_display_shadow' => TRUE]],
      ['content' => ['pb_display_shadow' => ['weight' => 30]]],
    );

    $this->assertFalse($changed);
    $this->assertSame(0, $stats['weights']);
    $this->assertArrayHasKey('pb_display_shadow', $data['hidden']);
    $this->assertArrayNotHasKey('pb_display_shadow', $data['content']);
  }

  /**
   * Field group children are put back into the shipped order.
   */
  public function testFieldGroupChildOrderIsRealigned(): void {
    [$changed, $data, $stats] = $this->realign(
      [
        'third_party_settings' => [
          'field_group' => [
            'group_pb_display_colors' => [
              'weight' => 3,
              'children' => ['pb_display_link', 'pb_display_link_hover', 'pb_display_text'],
            ],
          ],
        ],
      ],
      [
        'third_party_settings' => [
          'field_group' => [
            'group_pb_display_colors' => [
              'weight' => 10,
              'children' => ['pb_display_text', 'pb_display_link', 'pb_display_link_hover'],
            ],
          ],
        ],
      ],
    );

    $this->assertTrue($changed);
    $group = $data['third_party_settings']['field_group']['group_pb_display_colors'];
    $this->assertSame(10, $group['weight']);
    $this->assertSame(
      ['pb_display_text', 'pb_display_link', 'pb_display_link_hover'],
      $group['children'],
    );
    // One weight change plus one child-order change.
    $this->assertSame(2, $stats['groups']);
  }

  /**
   * A child the site dragged into a shipped group is kept, not dropped.
   */
  public function testSiteAddedGroupChildIsPreserved(): void {
    [, $data] = $this->realign(
      [
        'third_party_settings' => [
          'field_group' => [
            'group_pb_tab_content' => [
              'weight' => 1,
              'children' => ['pb_content_body', 'field_site_custom', 'pb_content_title'],
            ],
          ],
        ],
      ],
      [
        'third_party_settings' => [
          'field_group' => [
            'group_pb_tab_content' => [
              'weight' => 1,
              'children' => ['pb_content_title', 'pb_content_body'],
            ],
          ],
        ],
      ],
    );

    $this->assertSame(
      ['pb_content_title', 'pb_content_body', 'field_site_custom'],
      $data['third_party_settings']['field_group']['group_pb_tab_content']['children'],
    );
  }

  /**
   * A group this module does not ship is left entirely alone.
   */
  public function testUnshippedFieldGroupIsNotTouched(): void {
    $site_group = ['weight' => 99, 'children' => ['field_b', 'field_a']];
    [$changed, $data] = $this->realign(
      ['third_party_settings' => ['field_group' => ['group_site_own' => $site_group]]],
      ['third_party_settings' => ['field_group' => ['group_pb_display_colors' => ['weight' => 10]]]],
    );

    $this->assertFalse($changed);
    $this->assertSame($site_group, $data['third_party_settings']['field_group']['group_site_own']);
  }

  /**
   * A display that already matches is reported unchanged — the pass is a no-op.
   */
  public function testAlreadyAlignedDisplayIsUnchanged(): void {
    $config = [
      'content' => ['pb_display_padding' => ['weight' => 28, 'region' => 'content']],
      'hidden' => ['pb_display_shadow' => TRUE],
      'third_party_settings' => [
        'field_group' => [
          'group_pb_tab_display' => ['weight' => 2, 'children' => ['pb_display_padding']],
        ],
      ],
    ];
    [$changed, $data, $stats] = $this->realign($config, $config);

    $this->assertFalse($changed);
    $this->assertSame($config, $data);
    $this->assertSame(['weights' => 0, 'unhidden' => 0, 'groups' => 0], $stats);
  }

  /**
   * No shipped display file lists a component as both visible and hidden.
   *
   * The upgrade path produced 134 of these across 35 form displays. If one ever
   * reaches `config/optional`, a *fresh* install inherits it too and the
   * realign pass cannot help, because the file is what it realigns to.
   */
  #[DataProvider('shippedDisplayFiles')]
  public function testShippedDisplayHasNoContentHiddenOverlap(string $file): void {
    $config = Yaml::parseFile($file);
    $overlap = array_intersect(
      array_keys($config['content'] ?? []),
      array_keys($config['hidden'] ?? []),
    );

    $this->assertSame([], array_values($overlap), sprintf(
      '%s lists %s in both content and hidden.',
      basename($file),
      implode(', ', $overlap),
    ));
  }

  /**
   * Every shipped form/view display file, one per data set.
   *
   * @return array<string, array{string}>
   *   Data sets keyed by config name.
   */
  public static function shippedDisplayFiles(): array {
    $root = dirname(__DIR__, 3);
    $dirs = array_merge(
      [$root . '/config/optional'],
      array_map(
        static fn(string $dir): string => $dir . '/config/optional',
        glob($root . '/modules/*', GLOB_ONLYDIR) ?: [],
      ),
    );

    $sets = [];
    foreach ($dirs as $dir) {
      foreach (glob($dir . '/core.entity_*_display.*.yml') ?: [] as $file) {
        $sets[basename($file, '.yml')] = [$file];
      }
    }
    ksort($sets);
    return $sets;
  }

}
