<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraph_bundle_content\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the pb_content form-display group merge helpers.
 *
 * These live in `paragraph_bundle_content.install` as plain functions, so the
 * file is included directly. It declares functions only — nothing runs on
 * include — and the three helpers under test touch no Drupal services.
 */
#[Group('paragraphs_bundles')]
final class PbcFormDisplayGroupingTest extends TestCase {

  /**
   * {@inheritdoc}
   */
  public static function setUpBeforeClass(): void {
    parent::setUpBeforeClass();
    if (!function_exists('paragraph_bundle_content_core_ungrouped_form_fields')) {
      require_once __DIR__ . '/../../../paragraph_bundle_content.install';
    }
  }

  /**
   * Returns the shipped group tree used by the append tests.
   *
   * @return array<string, mixed>
   *   Field group definitions.
   */
  private static function groupTree(): array {
    return [
      'group_pb_tabs_wrapper' => [
        'children' => ['group_pb_tab_content', 'group_pb_tab_display'],
      ],
      'group_pb_tab_content' => [
        'children' => ['title', 'pb_content_type_body'],
      ],
      'group_pb_tab_display' => [
        'children' => ['pb_display_content_width'],
      ],
    ];
  }

  /**
   * Core node form components are never adopted into the Content tab.
   *
   * `langcode` and `revision_log` sit in `content` on any node form display and
   * belong to no group. Reading them as site-added fields is what put them
   * inside the Content tab on every upgraded site.
   */
  public function testCoreBaseFieldsAreNotAppended(): void {
    $groups = self::groupTree();
    $content = array_fill_keys([
      'title',
      'pb_content_type_body',
      'pb_display_content_width',
      'created',
      'path',
      'uid',
      'promote',
      'sticky',
      'status',
      'langcode',
      'revision_log',
    ], []);

    paragraph_bundle_content_append_custom_fields_to_content_tab($groups, $content);

    $this->assertSame(
      ['title', 'pb_content_type_body'],
      $groups['group_pb_tab_content']['children'],
    );
  }

  /**
   * A field the site added to the type is appended to the Content tab.
   */
  public function testSiteAddedFieldIsAppended(): void {
    $groups = self::groupTree();
    $content = array_fill_keys([
      'title',
      'pb_content_type_body',
      'pb_display_content_width',
      'langcode',
      'field_site_added',
    ], []);

    paragraph_bundle_content_append_custom_fields_to_content_tab($groups, $content);

    $this->assertSame(
      ['title', 'pb_content_type_body', 'field_site_added'],
      $groups['group_pb_tab_content']['children'],
    );
  }

  /**
   * Appending twice over the same content is a no-op the second time.
   *
   * The merge runs on every upgrade; a second run must not move config the
   * first run left alone.
   */
  public function testAppendIsIdempotent(): void {
    $groups = self::groupTree();
    $content = array_fill_keys([
      'title',
      'pb_content_type_body',
      'langcode',
      'revision_log',
      'field_site_added',
    ], []);

    paragraph_bundle_content_append_custom_fields_to_content_tab($groups, $content);
    $after_first = $groups;
    paragraph_bundle_content_append_custom_fields_to_content_tab($groups, $content);

    $this->assertSame($after_first, $groups);
  }

  /**
   * A field placed in another tab is not pulled into the Content tab.
   */
  public function testFieldGroupedElsewhereIsLeftAlone(): void {
    $groups = self::groupTree();
    $content = array_fill_keys(['title', 'pb_display_content_width'], []);

    paragraph_bundle_content_append_custom_fields_to_content_tab($groups, $content);

    $this->assertSame(
      ['title', 'pb_content_type_body'],
      $groups['group_pb_tab_content']['children'],
    );
    $this->assertSame(
      ['pb_display_content_width'],
      $groups['group_pb_tab_display']['children'],
    );
  }

  /**
   * Nested group children are seen as grouped, not as orphans.
   */
  public function testNestedGroupChildrenCountAsGrouped(): void {
    $groups = self::groupTree();
    $groups['group_pb_tab_display'] = [
      'children' => ['group_pb_display_width_colors'],
    ];
    $groups['group_pb_display_width_colors'] = [
      'children' => ['pb_display_content_width'],
    ];
    $content = array_fill_keys(['title', 'pb_display_content_width'], []);

    paragraph_bundle_content_append_custom_fields_to_content_tab($groups, $content);

    $this->assertSame(
      ['title', 'pb_content_type_body'],
      $groups['group_pb_tab_content']['children'],
    );
  }

}
