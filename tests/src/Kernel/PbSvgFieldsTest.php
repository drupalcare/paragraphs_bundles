<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\KernelTests\KernelTestBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs_bundles\Service\Svg\PbSvgLibrary;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The seven SVG fields: install, update, validation, render, precedence.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbSvgFieldsTest extends KernelTestBase {

  /**
   * Module, bundle, field, picture field.
   */
  private const array FIELDS = [
    ['paragraph_bundle_icon', 'icon_bundle', 'pb_content_svg_icon', 'pb_content_image_icon'],
    ['paragraph_bundle_hero', 'hero_bundle', 'pb_content_svg_hero', 'pb_content_image'],
    ['paragraph_bundle_image', 'image_bundle', 'pb_content_svg_image', 'pb_content_image'],
    ['paragraph_bundle_image', 'image_narrow_bundle', 'pb_content_svg_image_narrow', 'pb_content_image'],
    ['paragraph_bundle_image', 'image_wide_bundle', 'pb_content_svg_image_wide', 'pb_content_image'],
    ['paragraph_bundle_card', 'card_image_section_bundle', 'pb_content_svg_card', 'pb_content_image'],
    ['paragraph_bundle_image_background', 'image_background_bundle', 'pb_display_svg_background', 'pb_display_background_image'],
  ];

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
    'path',
    'path_alias',
    'media',
    'media_library',
    'views',
    'responsive_image',
    'breakpoint',
    'node',
    'entity_reference_revisions',
    'paragraphs',
    'field_group',
    'paragraphs_bundles',
    'paragraph_bundle_icon',
    'paragraph_bundle_hero',
    'paragraph_bundle_image',
    'paragraph_bundle_card',
    'paragraph_bundle_image_background',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('paragraph');
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['system', 'filter', 'image', 'media']);
    $this->container->get('config.installer')->installOptionalConfig();
    $this->setSetting('extension_discovery_scan_tests', TRUE);
    $this->container->get('theme_installer')->install(['pb_svg_test_child']);
    $this->config('system.theme')->set('default', 'pb_svg_test_child')->save();
    $this->container->get('kernel')->rebuildContainer();
  }

  /**
   * Renders a paragraph.
   *
   * @param array<string, mixed> $values
   *   Field values, with `type`.
   */
  private function renderParagraph(array $values): string {
    $paragraph = Paragraph::create($values);
    $paragraph->save();
    $build = $this->container->get('entity_type.manager')->getViewBuilder('paragraph')->view($paragraph);
    return (string) $this->container->get('renderer')->renderRoot($build);
  }

  /**
   * A fresh install ships all seven: own storage, picker, hidden in view.
   */
  public function testFreshInstall(): void {
    foreach (self::FIELDS as [, $bundle, $field, $picture]) {
      $storage = FieldStorageConfig::loadByName('paragraph', $field);
      self::assertNotNull($storage, $field);
      self::assertSame(PbSvgLibrary::ALLOWED_VALUES_FUNCTION, $storage->getSetting('allowed_values_function'));
      self::assertSame(1, $storage->getCardinality());
      self::assertNotNull(FieldConfig::loadByName('paragraph', $bundle, $field));
      $picture_field = FieldConfig::loadByName('paragraph', $bundle, $picture);
      self::assertNotNull($picture_field);
      self::assertFalse($picture_field->isRequired(), "$bundle $picture is optional");
      $form = EntityFormDisplay::load("paragraph.$bundle.default");
      self::assertSame('pb_svg_picker', $form?->getComponent($field)['type'] ?? NULL, $field);
      self::assertNull(EntityViewDisplay::load("paragraph.$bundle.default")?->getComponent($field), $field);
    }
  }

  /**
   * The shipped SVG config names no other bundle module.
   */
  public function testNoCrossBundleDependency(): void {
    foreach (self::FIELDS as [$module, $bundle, $field]) {
      foreach ([FieldStorageConfig::loadByName('paragraph', $field), FieldConfig::loadByName('paragraph', $bundle, $field)] as $config) {
        self::assertNotNull($config);
        foreach ($config->getDependencies()['module'] ?? [] as $dependency) {
          self::assertTrue(!str_starts_with($dependency, 'paragraph_bundle_') || $dependency === $module, "$field needs $dependency");
        }
      }
    }
  }

  /**
   * Allowed values come from the theme chain; other values do not validate.
   */
  public function testValidation(): void {
    foreach (self::FIELDS as [, $bundle, $field, $picture]) {
      $paragraph = Paragraph::create(['type' => $bundle, $field => 'pb_svg_test_child:cars/sedan']);
      $paths = static fn (Paragraph $p): array => array_map(static fn ($v): string => $v->getPropertyPath(), iterator_to_array($p->validate()));
      self::assertFalse(self::hasPath($paths($paragraph), $field), "$field accepts a theme file");
      self::assertFalse(self::hasPath($paths($paragraph), $picture), "$bundle needs no image with an SVG");

      $paragraph->set($field, 'pb_svg_test_parent:arrow');
      self::assertFalse(self::hasPath($paths($paragraph), $field), "$field accepts a base theme file");

      $paragraph->set($field, 'pb_svg_test_child:../../secret');
      self::assertTrue(self::hasPath($paths($paragraph), $field), "$field refuses a path outside");

      $paragraph->set($field, NULL);
      if ($bundle === 'image_background_bundle') {
        // The background picture stayed optional before this feature.
        continue;
      }
      if ($bundle === 'icon_bundle') {
        // The Google icon field has a default; empty it to reach the rule.
        $paragraph->set('pb_content_gms_icon_name', NULL);
      }
      $missing = $bundle === 'icon_bundle' ? 'pb_content_image_icon' : $picture;
      self::assertTrue(self::hasPath($paths($paragraph), $missing), "$bundle asks for an image or an SVG: " . implode(', ', $paths($paragraph)));
    }
  }

  /**
   * TRUE when a violation path starts with the field name.
   *
   * @param list<string> $paths
   *   Violation paths.
   */
  private static function hasPath(array $paths, string $field): bool {
    foreach ($paths as $path) {
      if ($path === $field || str_starts_with($path, $field . '.')) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Every type prints the chosen file inline, and only cleaned markup.
   */
  public function testRenderEveryType(): void {
    foreach (self::FIELDS as [, $bundle, $field]) {
      $html = $this->renderParagraph(['type' => $bundle, $field => 'pb_svg_test_child:cars/sedan']);
      self::assertStringContainsString('id="pb-sedan"', $html, $bundle);
      self::assertStringContainsString('pb-svg', $html, $bundle);
      self::assertStringNotContainsString('<?xml', $html, $bundle);

      $html = $this->renderParagraph(['type' => $bundle, $field => 'pb_svg_test_child:hostile']);
      self::assertStringContainsString('id="pb-hostile"', $html, $bundle);
      foreach (['alert(', 'onerror', 'onload', '<script'] as $bad) {
        self::assertStringNotContainsString($bad, $html, "$bundle: $bad");
      }

      // A file gone from disk prints nothing and does not break the page.
      $html = $this->renderParagraph(['type' => $bundle, $field => 'pb_svg_test_child:gone']);
      self::assertStringNotContainsString('pb-svg--', $html, $bundle);
    }
  }

  /**
   * The icon: SVG wins over the Google icon font.
   */
  public function testIconOrder(): void {
    $html = $this->renderParagraph(['type' => 'icon_bundle', 'pb_content_gms_icon_name' => 'home_pin', 'pb_content_svg_icon' => 'pb_svg_test_child:cars/sedan']);
    self::assertStringContainsString('pb-svg--icon', $html);
    self::assertStringNotContainsString('home_pin', $html);
    $html = $this->renderParagraph(['type' => 'icon_bundle', 'pb_content_gms_icon_name' => 'home_pin']);
    self::assertStringContainsString('home_pin', $html);
    self::assertStringNotContainsString('pb-svg--icon', $html);
  }

  /**
   * An image always wins over the SVG, in every type that has both.
   */
  public function testImageWins(): void {
    // A visitor must be able to see the image for it to win.
    Role::create(['id' => RoleInterface::ANONYMOUS_ID, 'label' => 'Anonymous', 'permissions' => ['view media']])->save();
    $uri = 'public://pb-svg-test.png';
    file_put_contents($uri, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M/wHwAEBgIApD5fRAAAAABJRU5ErkJggg=='));
    $file = File::create(['uri' => $uri, 'status' => 1]);
    $file->save();
    $media = Media::create(['bundle' => 'pb_media_image', 'name' => 'x', 'field_media_image' => ['target_id' => $file->id(), 'alt' => 'x']]);
    $media->save();

    foreach (self::FIELDS as [, $bundle, $field, $picture]) {
      $value = $picture === 'pb_content_image' ? ['target_id' => $media->id()] : ['target_id' => $file->id(), 'alt' => 'x'];
      $html = $this->renderParagraph(['type' => $bundle, $field => 'pb_svg_test_child:cars/sedan', $picture => $value]);
      self::assertStringNotContainsString('id="pb-sedan"', $html, "$bundle: the image wins");
    }
  }

  /**
   * The update gives an existing site the shipped field, and runs twice safely.
   */
  public function testUpdateIsIdempotent(): void {
    $this->container->get('module_handler')->loadInclude('paragraphs_bundles', 'install');
    foreach (self::FIELDS as [$module, $bundle, $field, $picture]) {
      $form_before = EntityFormDisplay::load("paragraph.$bundle.default")?->getComponent($field);
      FieldStorageConfig::loadByName('paragraph', $field)?->delete();
      $picture_field = FieldConfig::loadByName('paragraph', $bundle, $picture);
      self::assertNotNull($picture_field);
      $picture_field->setRequired(TRUE)->save();
      self::assertNull(FieldStorageConfig::loadByName('paragraph', $field));
      $after = $bundle === 'hero_bundle' || $bundle === 'card_image_section_bundle' || str_starts_with($bundle, 'image_') && $bundle !== 'image_background_bundle' ? 'pb_content_image' : $picture;

      paragraphs_bundles_svg_field_install($module, $bundle, $field, $after);
      self::assertNotNull(FieldStorageConfig::loadByName('paragraph', $field), $field);
      self::assertNotNull(FieldConfig::loadByName('paragraph', $bundle, $field), $field);
      $form = EntityFormDisplay::load("paragraph.$bundle.default");
      self::assertSame($form_before['type'] ?? NULL, $form?->getComponent($field)['type'] ?? NULL, $field);
      self::assertNull(EntityViewDisplay::load("paragraph.$bundle.default")?->getComponent($field), $field);
      $picture_field = FieldConfig::loadByName('paragraph', $bundle, $picture);
      self::assertFalse((bool) $picture_field?->isRequired(), "$bundle $picture became optional");

      $export = $form?->toArray();
      paragraphs_bundles_svg_field_install($module, $bundle, $field, $after);
      self::assertSame($export, EntityFormDisplay::load("paragraph.$bundle.default")?->toArray(), "$field: second run changes nothing");
    }
  }

  /**
   * Removing a field removes its own storage and no other.
   */
  public function testRemovingFieldRemovesItsStorage(): void {
    FieldConfig::loadByName('paragraph', 'card_image_section_bundle', 'pb_content_svg_card')?->delete();
    self::assertNull(FieldStorageConfig::loadByName('paragraph', 'pb_content_svg_card'));
    self::assertNotNull(FieldStorageConfig::loadByName('paragraph', 'pb_content_svg_icon'));
  }

  /**
   * Deleting the paragraph types leaves no storage naming the callback.
   *
   * PB refuses to uninstall while its field types are in use, so this is the
   * road to uninstalling it: nothing may stay behind that names missing code.
   */
  public function testDeletingTypesLeavesNoSvgStorage(): void {
    foreach (self::FIELDS as [, $bundle]) {
      ParagraphsType::load($bundle)?->delete();
    }
    foreach (self::FIELDS as [, , $field]) {
      self::assertNull(FieldStorageConfig::loadByName('paragraph', $field), $field);
    }
  }

}
