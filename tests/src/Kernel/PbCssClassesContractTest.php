<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Html;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Tests\paragraphs_bundles\Traits\ModuleFilesTrait;
use Drupal\Tests\paragraphs_bundles\Traits\PbOptionalBundleModulesTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The CSS Classes field: config coverage, and the class reaching the wrapper.
 *
 * `pb_display_css_classes` is the one display field that is identical on every
 * PARENT bundle and absent from every section child. That split lives across
 * 31 separate config files, where a missing one is invisible - the bundle
 * simply has no box on its form, and nobody notices for a release.
 *
 * So this ties the ends together: the storage exists, every parent bundle
 * shipped by an enabled module has the instance, no child does, it is on the
 * form and off the rendered output, and a value an author stores actually
 * lands in the rendered class attribute - while a hostile one does not.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbCssClassesContractTest extends KernelTestBase {

  use ModuleFilesTrait;

  use PbOptionalBundleModulesTrait;

  /**
   * The field under test.
   */
  private const string FIELD = 'pb_display_css_classes';

  /**
   * The field group the field must sit in, on every parent bundle.
   */
  private const string GROUP = 'group_pb_display_advanced';

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
    'block',
    'block_content',
    'menu_ui',
    'node',
    'entity_reference_revisions',
    'paragraphs',
    'field_group',
    'paragraphs_bundles',
    // Bundle family whose dependencies ship in the harness. The four needing
    // external contrib (link/views/webform/contact_form) are enabled at
    // runtime by PbOptionalBundleModulesTrait when that contrib is present.
    'paragraph_bundle_content',
    'paragraph_bundle_image',
    'paragraph_bundle_hero',
    'paragraph_bundle_layout',
    'paragraph_bundle_grid',
    'paragraph_bundle_card',
    'paragraph_bundle_accordion',
    'paragraph_bundle_tabs',
    'paragraph_bundle_alert',
    'paragraph_bundle_icon',
    'paragraph_bundle_modal',
    'paragraph_bundle_block',
    'paragraph_bundle_block_content',
    'paragraph_bundle_3d_carousel',
    'paragraph_bundle_3d_flip_box',
    'paragraph_bundle_carousel',
    'paragraph_bundle_image_background',
    'paragraph_bundle_image_grid',
    'paragraph_bundle_image_overlay',
    'paragraph_bundle_node_reference',
    'paragraph_bundle_parallax',
    'paragraph_bundle_slideshow',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('media');
    $this->installConfig(['system']);
    $this->container->get('config.installer')->installOptionalConfig();
    $this->enableOptionalBundleModules();
  }

  /**
   * The shared storage is installed, with the settings the instances assume.
   */
  public function testStorageExists(): void {
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('field_storage_config')
      ->load('paragraph.' . self::FIELD);
    $this->assertNotNull($storage, 'Shared field storage is installed.');
    $this->assertSame('string', $storage->getType());
    $this->assertSame(255, $storage->getSetting('max_length'));
    $this->assertSame(1, $storage->getCardinality());
  }

  /**
   * Every parent bundle has the field; no section child has it.
   */
  public function testEveryParentBundleHasTheFieldAndNoChildDoes(): void {
    $em = $this->container->get('entity_type.manager');
    $types = array_keys($em->getStorage('paragraphs_type')->loadMultiple());
    sort($types);
    $this->assertNotEmpty($types, 'Bundles are installed.');

    $missing = [];
    $leaked = [];
    foreach ($types as $type) {
      $has = (bool) $em->getStorage('field_config')
        ->load('paragraph.' . $type . '.' . self::FIELD);
      if (str_ends_with($type, '_section_bundle')) {
        if ($has) {
          $leaked[] = $type;
        }
        continue;
      }
      if (!$has) {
        $missing[] = $type;
      }
    }

    $this->assertSame([], $missing, 'Every parent bundle carries the CSS Classes field.');
    $this->assertSame([], $leaked, 'No section child carries it; children are styled through their parent.');
  }

  /**
   * Every SHIPPED form display carries the component; no view display does.
   *
   * This reads the module's own config files rather than the installed
   * entities, on purpose. Four bundles - icon, alert, block_content and
   * image_background - have form displays whose other config dependencies are
   * not all met in this harness, so `installOptionalConfig()` skips them and
   * the entity does not exist here at all. It does on a real site (checked
   * against a test site). Asserting on the installed entity would therefore
   * report a defect that is an artefact of the test environment, and - worse -
   * would pass silently on those four if the component were ever dropped from
   * the file.
   */
  public function testEveryShippedFormDisplayHasTheComponent(): void {
    $missing = [];
    $printed = [];
    $checked = 0;

    foreach ($this->shippedDisplayFiles('core.entity_form_display.paragraph.') as $bundle => $data) {
      $checked++;
      if (!isset($data['content'][self::FIELD])) {
        $missing[] = $bundle;
      }
      if (isset($data['hidden'][self::FIELD])) {
        // A field in both `content` and `hidden` is a state core's own API
        // cannot produce and nothing downstream repairs.
        $printed[] = $bundle . ' (also in hidden)';
      }

      // The GROUP has to be in the file too, not just the component.
      //
      // A fresh install imports these files and never calls
      // paragraphs_bundles_pb_nest_display_field_groups() - there is no
      // hook_install anywhere in this module, in the parent or in any of the
      // 30 sub-modules, so every call site is an update hook. Shipping the
      // component without the group put the field OUTSIDE the Display tab on
      // every new site, while every existing site looked correct because the
      // update hook nested it. Measured 2026-09-14 on a from-scratch install.
      $groups = $data['third_party_settings']['field_group'] ?? [];
      $advanced = $groups[self::GROUP] ?? NULL;
      if ($advanced === NULL) {
        $missing[] = $bundle . ' (group)';
        continue;
      }
      if (($advanced['children'] ?? []) !== [self::FIELD]) {
        $missing[] = $bundle . ' (group children)';
      }
      if (($advanced['parent_name'] ?? '') !== 'group_pb_tab_display') {
        $missing[] = $bundle . ' (group parent)';
      }
      // Closed, like its sibling display groups - the maintainer's call.
      if (($advanced['format_settings']['open'] ?? NULL) !== FALSE) {
        $missing[] = $bundle . ' (group should be closed)';
      }
      $tab = $groups['group_pb_tab_display']['children'] ?? [];
      if (!in_array(self::GROUP, $tab, TRUE)) {
        $missing[] = $bundle . ' (not a child of the Display tab)';
      }
    }
    foreach ($this->shippedDisplayFiles('core.entity_view_display.paragraph.') as $bundle => $data) {
      if (isset($data['content'][self::FIELD])) {
        $printed[] = $bundle;
      }
    }

    $this->assertSame([], $missing, 'Every shipped parent form display has the CSS Classes component.');
    $this->assertSame([], $printed, 'No view display prints it as field markup.');
    $this->assertGreaterThan(25, $checked, 'The scan found the shipped form displays.');
  }

  /**
   * Every field on a shipped parent form display sits in a field group.
   *
   * This is the general form of a bug this module has now shipped TWICE: a
   * component added to `content` without being added to any group's
   * `children`. The update hook nests it, so every existing site looks right,
   * while a fresh install renders it outside the Display tab entirely.
   *
   * It happened to `pb_display_alert_state` in the alert-state commit and to
   * `pb_display_css_classes` in this one, and in both cases every test passed.
   * The guard is deliberately about ALL fields rather than this feature's,
   * because the next occurrence will be some other field.
   */
  public function testEveryShippedComponentIsGrouped(): void {
    $ungrouped = [];
    $checked = 0;

    foreach ($this->shippedDisplayFiles('core.entity_form_display.paragraph.') as $bundle => $data) {
      $groups = $data['third_party_settings']['field_group'] ?? [];
      if ($groups === []) {
        // A bundle with no groups at all is a different shape, not this bug.
        continue;
      }
      $grouped = [];
      foreach ($groups as $group) {
        foreach ($group['children'] ?? [] as $child) {
          $grouped[$child] = TRUE;
        }
      }
      foreach (array_keys($data['content'] ?? []) as $field) {
        // Only this module's own fields: a site or another module may add a
        // component here and grouping it is not this module's business.
        if (!str_starts_with((string) $field, 'pb_')) {
          continue;
        }
        $checked++;
        if (!isset($grouped[$field])) {
          $ungrouped[] = $bundle . '.' . $field;
        }
      }
    }

    $this->assertSame(
      [],
      $ungrouped,
      'Every pb_* component on a shipped parent form display belongs to a field group.',
    );
    $this->assertGreaterThan(200, $checked, 'The scan examined the shipped components.');
  }

  /**
   * Where the entity does exist, it agrees with the file.
   */
  public function testInstalledFormDisplaysAgreeWithTheFiles(): void {
    $em = $this->container->get('entity_type.manager');
    $types = array_keys($em->getStorage('paragraphs_type')->loadMultiple());
    sort($types);

    $wrong = [];
    $checked = 0;
    foreach ($types as $type) {
      if (str_ends_with($type, '_section_bundle')) {
        continue;
      }
      $fd = $em->getStorage('entity_form_display')->load('paragraph.' . $type . '.default');
      if ($fd === NULL) {
        // Not installed in this harness; the file is covered by the test above.
        continue;
      }
      $checked++;
      if (!$fd->getComponent(self::FIELD)) {
        $wrong[] = $type;
      }
      // installOptionalConfig() in setUp() is the fresh-install path, so this
      // is the assertion that a NEW site gets the field nested, not floating.
      $groups = $fd->getThirdPartySettings('field_group');
      $advanced = $groups[self::GROUP] ?? NULL;
      if ($advanced === NULL) {
        $wrong[] = $type . ' (no Advanced group after a fresh install)';
      }
      elseif (($advanced['format_settings']['open'] ?? NULL) !== FALSE) {
        $wrong[] = $type . ' (Advanced group should be closed)';
      }
      $vd = $em->getStorage('entity_view_display')->load('paragraph.' . $type . '.default');
      if ($vd !== NULL && $vd->getComponent(self::FIELD)) {
        $wrong[] = $type . ' (printed on view display)';
      }
    }

    $this->assertSame([], $wrong, 'Installed form displays carry the field; view displays do not print it.');
    // Guard against the loop quietly covering nothing if installation changes.
    $this->assertGreaterThan(20, $checked, 'The loop examined the installed parent form displays.');
  }

  /**
   * Reads this module's shipped display config for parent bundles.
   *
   * @param string $prefix
   *   Config name prefix to match.
   *
   * @return array<string, array<string, mixed>>
   *   Parsed config keyed by bundle.
   */
  private function shippedDisplayFiles(string $prefix): array {
    $root = dirname(__DIR__, 3);
    $found = [];
    $rii = $this->moduleFiles($root);
    foreach ($rii as $file) {
      if (!$file->isFile()) {
        continue;
      }
      $name = $file->getFilename();
      if (!str_starts_with($name, $prefix) || !str_ends_with($name, '.default.yml')) {
        continue;
      }
      $bundle = substr($name, strlen($prefix), -strlen('.default.yml'));
      if (str_ends_with($bundle, '_section_bundle')) {
        continue;
      }
      // paragraphs_bundles_ai's plain-image fallback holds ONE image field and
      // nothing else, by the maintainer's design (2026-09-17): no title, no
      // display fields. It is inserted into other bundles' image slots, so it
      // is styled through its parent like a section child.
      if ($bundle === 'ai_image_bundle') {
        continue;
      }
      $found[$bundle] = Yaml::decode((string) file_get_contents($file->getPathname())) ?? [];
    }
    ksort($found);
    return $found;
  }

  /**
   * The Advanced group exists, is collapsed, and sits last.
   */
  public function testAdvancedGroupDefinition(): void {
    $this->container->get('module_handler')
      ->loadInclude('paragraphs_bundles', 'inc', 'paragraphs_bundles.display');
    $groups = paragraphs_bundles_pb_display_subgroup_definitions();

    $this->assertArrayHasKey('group_pb_display_advanced', $groups);
    $group = $groups['group_pb_display_advanced'];
    $this->assertSame([self::FIELD], $group['fields']);
    // Collapsed, like every other display group. Opening it was tried and
    // rejected by the maintainer: the Display tab is a stack of closed
    // sections and one standing open breaks that rhythm.
    $this->assertFalse($group['open'], 'The Advanced group is collapsed by default.');
    $weights = array_map(static fn (array $g): int => $g['weight'], $groups);
    $this->assertSame(max($weights), $group['weight'], 'Advanced is last on the Display tab.');
  }

  /**
   * A stored value reaches the rendered class attribute, on every parent.
   */
  public function testStoredClassesReachTheWrapper(): void {
    $em = $this->container->get('entity_type.manager');
    $view_builder = $em->getViewBuilder('paragraph');
    $renderer = $this->container->get('renderer');
    $types = array_keys($em->getStorage('paragraphs_type')->loadMultiple());
    sort($types);

    $asserted = 0;
    foreach ($types as $type) {
      if (str_ends_with($type, '_section_bundle')) {
        continue;
      }
      $paragraph = Paragraph::create([
        'type' => $type,
        self::FIELD => 'pb-probe-class md:w-full',
      ]);
      $paragraph->save();

      $build = $view_builder->view($paragraph);
      $html = (string) $renderer->renderRoot($build);
      $this->assertMatchesRegularExpression(
        '/class="[^"]*\bpb-probe-class\b[^"]*"/',
        $html,
        sprintf('Bundle %s renders the stored custom class on an element.', $type),
      );
      // The Tailwind token survives intact rather than being rewritten.
      $this->assertStringContainsString(
        'md:w-full',
        $html,
        sprintf('Bundle %s keeps a Tailwind token unmangled.', $type),
      );
      $asserted++;
    }
    $this->assertGreaterThan(20, $asserted, 'The loop covered the parent bundles.');
  }

  /**
   * An author is told which classes were refused, and why.
   *
   * The render-time filter is silent by design - it cannot interrupt a page
   * to explain itself. Without this constraint the author's only signal is a
   * class that mysteriously does nothing, which is the failure this feature
   * would otherwise ship with.
   */
  public function testRejectedClassesAreReported(): void {
    $paragraph = Paragraph::create([
      'type' => 'simple_bundle',
      self::FIELD => 'keep-me a<script>b onmouseover=x',
    ]);

    $violations = $paragraph->get(self::FIELD)->validate();
    $this->assertGreaterThan(0, $violations->count(), 'A refused class raises a violation.');

    $text = '';
    foreach ($violations as $violation) {
      $text .= (string) $violation->getMessage();
    }
    // Read the message the way the author eventually does. Drupal wraps a
    // %placeholder in `<em class="placeholder">` and escapes its contents, so
    // the raw message holds `a&lt;script&gt;b`. That escaping is correct - the
    // token is hostile and this message is rendered as markup - so the test
    // decodes rather than asserting on the raw string, which would either fail
    // (as the first version did) or lock in Drupal's escaping as a contract.
    $shown = Html::decodeEntities(strip_tags($text));

    // The message must NAME the offending tokens - "some classes were invalid"
    // sends the author back to guess which.
    $this->assertStringContainsString('a<script>b', $shown);
    $this->assertStringContainsString('onmouseover=x', $shown);
    // And it must not scold them about the ones that were fine.
    $this->assertStringNotContainsString('keep-me', $shown);
  }

  /**
   * A clean value validates without complaint.
   */
  public function testAcceptedClassesReportNothing(): void {
    $paragraph = Paragraph::create([
      'type' => 'simple_bundle',
      self::FIELD => 'my-brand-box md:w-full bg-[#1da1f2] top-1/2 !font-bold',
    ]);
    $this->assertCount(
      0,
      $paragraph->get(self::FIELD)->validate(),
      'Tailwind utilities and ordinary classes validate cleanly.',
    );
  }

  /**
   * An empty field - the default on every bundle - never complains.
   */
  public function testEmptyValueReportsNothing(): void {
    foreach (['', '   '] as $value) {
      $paragraph = Paragraph::create(['type' => 'simple_bundle', self::FIELD => $value]);
      $this->assertCount(0, $paragraph->get(self::FIELD)->validate());
    }
    $paragraph = Paragraph::create(['type' => 'simple_bundle']);
    $this->assertCount(0, $paragraph->get(self::FIELD)->validate());
  }

  /**
   * What the validator accepts is exactly what the renderer emits.
   *
   * These are two different code paths reading one rule. If they ever drift,
   * the module either warns about a class it then uses, or silently drops one
   * it said was fine - and both are worse than having no validator.
   */
  public function testValidatorAndRendererAgree(): void {
    $raw = 'ok-one a<script>b md:w-full onmouseover=x ok-two';
    $resolver = $this->container->get('paragraphs_bundles.display_class_resolver');
    $filtered = $resolver->filterClassTokens($raw);

    $paragraph = Paragraph::create(['type' => 'simple_bundle', self::FIELD => $raw]);
    $paragraph->save();
    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('paragraph')->view($paragraph);
    $html = (string) $this->container->get('renderer')->renderRoot($build);

    foreach ($filtered['accepted'] as $class) {
      $this->assertStringContainsString($class, $html, sprintf('Accepted class %s is emitted.', $class));
    }
    foreach ($filtered['rejected'] as $class) {
      $this->assertStringNotContainsString($class, $html, sprintf('Rejected class %s is not emitted.', $class));
    }
    $this->assertSame(['ok-one', 'md:w-full', 'ok-two'], $filtered['accepted']);
    $this->assertSame(['a<script>b', 'onmouseover=x'], $filtered['rejected']);
  }

  /**
   * A hostile value never reaches the markup.
   *
   * The field is free text that lands in an attribute, so this is the test
   * that matters most: an author with paragraph-edit rights must not be able
   * to close the attribute or open a tag. Clean tokens in the same value are
   * still emitted, which is what makes rejection per-token rather than
   * all-or-nothing.
   */
  public function testHostileValueIsRefused(): void {
    $view_builder = $this->container->get('entity_type.manager')
      ->getViewBuilder('paragraph');
    $renderer = $this->container->get('renderer');

    $paragraph = Paragraph::create([
      'type' => 'simple_bundle',
      self::FIELD => 'safe-one "onmouseover=alert(1) x<script>y safe-two',
    ]);
    $paragraph->save();

    $build = $view_builder->view($paragraph);
    $html = (string) $renderer->renderRoot($build);

    $this->assertMatchesRegularExpression('/class="[^"]*\bsafe-one\b[^"]*"/', $html);
    $this->assertMatchesRegularExpression('/class="[^"]*\bsafe-two\b[^"]*"/', $html);
    $this->assertStringNotContainsString('onmouseover', $html);
    $this->assertStringNotContainsString('alert(1)', $html);
    $this->assertStringNotContainsString('<script>', $html);
    $this->assertStringNotContainsString('&lt;script&gt;', $html);
  }

}
