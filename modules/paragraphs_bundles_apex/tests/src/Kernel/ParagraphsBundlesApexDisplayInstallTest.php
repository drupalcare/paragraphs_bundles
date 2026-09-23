<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_apex\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\MediaType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The shipped view modes install, bind to APEX, and backfill exactly once.
 *
 * WHY THIS CLASS EXISTS AND WHAT IT IS ACTUALLY PINNING.
 * This module's entire product is six config entities: three media view modes
 * and three entity view displays that wire pb_image_field to the
 * apex_responsive_image formatter. It ships no PHP that renders anything. So
 * the only thing that can break is the config arriving, arriving complete, or
 * arriving twice -- and all three are silent.
 *
 * ⚠ KernelTestBase DOES NOT IMPORT config/optional. Enabling a module in a
 * Kernel test runs neither hook_install() nor the optional-config import, so
 * asserting "the module is enabled, therefore the view modes exist" would
 * assert nothing at all. That is why this class calls
 * paragraphs_bundles_apex_ensure_displays() explicitly: it is the one function
 * BOTH real paths go through -- hook_install() on a fresh install, and
 * paragraphs_bundles_apex_update_10001() on a site that enabled APEX later.
 * Testing it directly tests both, and testing it in Kernel is what makes the
 * strict config-schema checking below free.
 *
 * 🚨 THE DEFECT THIS WAS WRITTEN AGAINST, AND IT SHIPPED ONCE.
 * ensure_displays() originally called installOptionalConfig($source, ['config'
 * => $name]) once per file. The second argument of installOptionalConfig() is
 * a FILTER meaning "install optional config that DEPENDS ON this thing", not
 * "install this thing". Nothing depends on those files, so the call created
 * exactly ZERO config and returned normally -- and the hook then reported
 * success. A backfill that silently creates nothing and says it worked is the
 * precise failure this module exists to prevent, so it gets a test rather than
 * a comment.
 *
 * FIXTURE NOTE. The media type and its two fields are built here rather than
 * installed from paragraph_bundle_image, deliberately. Those live in that
 * module's own config/optional and pulling them in would drag field_group,
 * paragraphs and entity_reference_revisions into a Kernel test to prove
 * something none of them own. What the shipped displays actually require is
 * that config entities with these exact NAMES exist, because that is what
 * their dependencies key lists -- so creating them by hand exercises the same
 * dependency resolution the real install performs. The names are therefore
 * load-bearing and must not be "tidied".
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles_apex')]
#[RunTestsInSeparateProcesses]
final class ParagraphsBundlesApexDisplayInstallTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    // responsive_image's theme hooks autowire BreakpointManagerInterface, so
    // breakpoint must be listed explicitly -- a Kernel test resolves no
    // module dependencies for you, and the failure is a container-build
    // RuntimeException that names responsive_image rather than breakpoint.
    'breakpoint',
    'responsive_image',
    'apex',
    'apex_theme_settings',
    'apex_layout',
    'apex_width',
    'apex_responsive_image',
  ];

  /**
   * The media bundle the bridge extends, owned by paragraph_bundle_image.
   */
  private const BUNDLE = 'pb_media_image';

  /**
   * The image field inside that bundle -- the one that gets the formatter.
   */
  private const IMAGE_FIELD = 'pb_image_field';

  /**
   * The caption field, present only because the displays reference it.
   */
  private const CAPTION_FIELD = 'pb_image_caption';

  /**
   * Each shipped view mode and the APEX responsive-image mode it must select.
   *
   * This map is the module's promise. A display that installs but points at
   * the wrong APEX mode renders a real image at the wrong aspect ratio, which
   * looks like a design mistake rather than a config one.
   *
   * @var array<string, string>
   */
  private const MODE_MAP = [
    'pb_apex_wide' => 'apex_hero',
    'pb_apex_narrow' => 'apex_content',
    'pb_apex_card' => 'apex_card',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installConfig(['field', 'file', 'image', 'media']);

    MediaType::create([
      'id' => self::BUNDLE,
      'label' => 'PB Image',
      'source' => 'image',
      'source_configuration' => ['source_field' => self::IMAGE_FIELD],
    ])->save();

    $this->createField(self::IMAGE_FIELD, 'image');
    $this->createField(self::CAPTION_FIELD, 'string');

    // The module is discovered but NOT enabled: enabling it would drag the
    // whole paragraphs_bundles chain in to prove nothing this class asserts.
    // ensure_displays() resolves its own path through extension.list.module,
    // which lists discovered modules regardless of installed state.
    //
    // ⚠ NOT loadInclude(). ModuleHandler::loadInclude() only knows modules
    // that are INSTALLED, so for a merely-discovered module it returns FALSE
    // and loads nothing -- and the next line then fails with "undefined
    // function", which reads as a missing function rather than a test that
    // never loaded the file. Requiring the path directly is what makes the
    // deliberate not-enabled fixture above actually workable.
    $path = \Drupal::service('extension.list.module')
      ->getPath('paragraphs_bundles_apex');
    require_once \Drupal::root() . '/' . $path . '/paragraphs_bundles_apex.install';

    $this->assertTrue(
      function_exists('paragraphs_bundles_apex_ensure_displays'),
      'The install file did not load, so every assertion below would report a missing function instead of a real result.',
    );
  }

  /**
   * A first run creates all six config entities, wired to the right modes.
   *
   * The count is asserted before the contents: a partial install is the
   * outcome a dependency mistake actually produces, and "three of six arrived"
   * would otherwise pass every per-item assertion that happened to run.
   */
  public function testFirstRunInstallsEveryShippedDisplay(): void {
    // CONTROL. If these already existed the test below could pass without
    // ensure_displays() doing anything whatsoever.
    foreach (array_keys(self::MODE_MAP) as $mode) {
      $this->assertTrue(
        \Drupal::config('core.entity_view_mode.media.' . $mode)->isNew(),
        sprintf('View mode %s existed before the module installed it, so this test cannot prove it was created.', $mode),
      );
    }

    $created = \paragraphs_bundles_apex_ensure_displays();

    $this->assertCount(
      6,
      $created,
      'ensure_displays() did not create all three view modes and all three displays. A backfill that creates nothing and reports success is the defect this module exists to prevent.',
    );

    foreach (self::MODE_MAP as $viewMode => $apexMode) {
      $display = \Drupal::entityTypeManager()
        ->getStorage('entity_view_display')
        ->load('media.' . self::BUNDLE . '.' . $viewMode);

      $this->assertNotNull(
        $display,
        sprintf('The display for view mode %s did not install.', $viewMode),
      );

      $component = $display->getComponent(self::IMAGE_FIELD);
      $this->assertIsArray(
        $component,
        sprintf('%s is not shown at all in view mode %s, so the formatter can never run.', self::IMAGE_FIELD, $viewMode),
      );
      $this->assertSame(
        'apex_responsive_image',
        $component['type'],
        sprintf('View mode %s does not use the APEX formatter, so the bridge does nothing.', $viewMode),
      );
      $this->assertSame(
        $apexMode,
        $component['settings']['apex_responsive_image_mode'],
        sprintf('View mode %s selects the wrong APEX mode: the image renders at the wrong aspect ratio.', $viewMode),
      );
    }
  }

  /**
   * A second run creates nothing, so the hook is safe to re-run.
   *
   * This is the half that protects a site operator's own work.
   * ensure_displays() is reachable from an update hook, and an update hook
   * runs on every deploy;
   * one that reinstalled its config each time would silently revert a display
   * the site had customised.
   *
   * The first call is the control: without it, an ensure_displays() that was
   * broken and always returned an empty array would pass this test.
   */
  public function testSecondRunIsIdempotentAndOverwritesNothing(): void {
    $first = \paragraphs_bundles_apex_ensure_displays();
    $this->assertNotSame([], $first, 'Nothing was created on the first run, so idempotence below proves nothing.');

    // A site operator changes a shipped display.
    $display = \Drupal::entityTypeManager()
      ->getStorage('entity_view_display')
      ->load('media.' . self::BUNDLE . '.pb_apex_wide');

    // load() returns NULL for a missing entity, and getComponent() returns
    // NULL for a field the display does not show. Asserting both keeps a
    // failure here readable as "the display did not install" rather than
    // arriving as a method call on NULL further down.
    $this->assertNotNull($display, 'The wide display did not install, so the customisation below cannot be made.');
    $component = $display->getComponent(self::IMAGE_FIELD);
    $this->assertIsArray($component, self::IMAGE_FIELD . ' is not shown in the wide display.');

    $component['settings']['apex_responsive_image_mode'] = 'apex_card';
    $display->setComponent(self::IMAGE_FIELD, $component)->save();

    $second = \paragraphs_bundles_apex_ensure_displays();

    $this->assertSame(
      [],
      $second,
      'A second run recreated config that already existed.',
    );

    $reloaded = \Drupal::entityTypeManager()
      ->getStorage('entity_view_display')
      ->load('media.' . self::BUNDLE . '.pb_apex_wide');
    $this->assertNotNull($reloaded, 'The wide display vanished during the re-run.');

    $reloadedComponent = $reloaded->getComponent(self::IMAGE_FIELD);
    $this->assertIsArray($reloadedComponent, self::IMAGE_FIELD . ' was dropped from the display by the re-run.');

    $this->assertSame(
      'apex_card',
      $reloadedComponent['settings']['apex_responsive_image_mode'],
      "The site operator's customisation was overwritten by a re-run.",
    );
  }

  /**
   * The update hook reports what it did, and never claims work it skipped.
   *
   * The message is asserted because it is the ONLY signal a site operator gets
   * from `drush updatedb`. The shipped defect returned the "already present"
   * message while having created nothing, which is indistinguishable from
   * success to the person running it.
   */
  public function testTheUpdateHookReportsHonestly(): void {
    $onFreshSite = \paragraphs_bundles_apex_update_10001();
    $this->assertStringContainsString(
      'Created:',
      $onFreshSite,
      'The update hook did not report creating anything on a site that had none of its config.',
    );

    $onCompleteSite = \paragraphs_bundles_apex_update_10001();
    $this->assertStringContainsString(
      'already present',
      $onCompleteSite,
      'The update hook did not report that there was nothing to do.',
    );
    $this->assertStringNotContainsString(
      'Created:',
      $onCompleteSite,
      'The update hook claimed to create config on a site that already had all of it.',
    );
  }

  /**
   * Creates one field on the media bundle under its exact shipped name.
   */
  private function createField(string $name, string $type): void {
    FieldStorageConfig::create([
      'field_name' => $name,
      'entity_type' => 'media',
      'type' => $type,
    ])->save();

    FieldConfig::create([
      'field_name' => $name,
      'entity_type' => 'media',
      'bundle' => self::BUNDLE,
      'label' => $name,
    ])->save();
  }

}
