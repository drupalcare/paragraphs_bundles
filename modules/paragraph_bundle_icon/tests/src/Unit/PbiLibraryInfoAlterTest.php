<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraph_bundle_icon\Unit;

use Drupal\paragraph_bundle_icon\Hook\PbiLibraryInfoAlterHook;
use Drupal\paragraph_bundle_icon\Service\PbiFontUrlBuilder;
use Drupal\paragraph_bundle_icon\Service\PbiIconUsageInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Pins the SHAPE the library alter mutates, because getting it wrong is silent.
 *
 * 🚨 THIS TEST EXISTS BECAUSE THE FIRST VERSION OF THE HOOK DID NOTHING AT ALL,
 * AND EVERY OTHER SIGNAL SAID IT WORKED. Measured 2026-09-23: `php -l` clean,
 * PHPCS exit 0 across 16 files, the full suite 355 tests green, and the page
 * rendering perfectly — while the 3.4 MB bundled font went on loading and the
 * subset was never requested. Nothing in the repository could go red. The only
 * thing that caught it was asking `library.discovery` at runtime what it
 * actually returned afterwards.
 *
 * THE MISTAKE, so it is not repeated. `var_export()` of a library from
 * `getLibraryByName()` was read as though it were the hook's INPUT. It is the
 * output — fully processed, `css` flattened into a numerically-indexed list of
 * asset arrays with `data`, `type` and `group` keys. Core settles the ordering:
 * `LibraryDiscoveryParser::buildByExtension()` calls `parseLibraryInfo()` at
 * line 82, the alter fires inside it at line 343, and the loop that builds
 * those asset arrays does not start until line 85 — AFTER. So the hook receives
 * the raw YAML shape: `css => [group => [path => options]]`, file path as the
 * KEY, and no `data` key anywhere.
 *
 * A control did not save me either: the only other `library_info_alter` in this
 * project touches `dependencies`, a key identical in both shapes, so it worked
 * under either reading and proved nothing.
 *
 * Every assertion below is written against the YAML shape on purpose. If
 * someone later "corrects" this file to the built shape, it fails — which is
 * the entire point.
 *
 * @group paragraphs_bundles
 */
#[Group('paragraphs_bundles')]
final class PbiLibraryInfoAlterTest extends TestCase {

  /**
   * The stylesheet path exactly as `.libraries.yml` declares it.
   */
  private const LOCAL_CSS = 'css/material-symbols-outlined.css';

  /**
   * A usage service returning a fixed map, with no container involved.
   *
   * @param array<string, list<string>> $map
   *   Style => icon names.
   *
   * @return \Drupal\paragraph_bundle_icon\Service\PbiIconUsageInterface
   *   Stub.
   */
  private function usage(array $map): PbiIconUsageInterface {
    return new class($map) implements PbiIconUsageInterface {

      /**
       * @param array<string, list<string>> $map
       *   Style => icon names.
       */
      public function __construct(private readonly array $map) {}

      /**
       * {@inheritdoc}
       */
      public function iconsByStyle(): array {
        return $this->map;
      }

    };
  }

  /**
   * The hook under test, wired to a fixed icon map.
   *
   * @param array<string, list<string>> $map
   *   Style => icon names.
   *
   * @return \Drupal\paragraph_bundle_icon\Hook\PbiLibraryInfoAlterHook
   *   The hook.
   */
  private function hook(array $map): PbiLibraryInfoAlterHook {
    return new PbiLibraryInfoAlterHook($this->usage($map), new PbiFontUrlBuilder());
  }

  /**
   * One library in the YAML shape, as the alter hook really receives it.
   *
   * @return array<string, array<string, mixed>>
   *   Library definitions.
   */
  private function yamlShapedLibraries(): array {
    return [
      'paragraph-bundle-icon-outlined' => [
        'css' => [
          'theme' => [
            self::LOCAL_CSS => ['weight' => 200],
          ],
        ],
      ],
    ];
  }

  /**
   * The bundled stylesheet is replaced by the subset URL.
   */
  public function testYamlShapedStylesheetIsRepointedAtTheSubset(): void {
    $libraries = $this->yamlShapedLibraries();
    $this->hook(['outlined' => ['grain', 'star']])
      ->libraryInfoAlter($libraries, 'paragraph_bundle_icon');

    $theme = $libraries['paragraph-bundle-icon-outlined']['css']['theme'];
    $keys = array_keys($theme);

    self::assertCount(1, $keys, 'The stylesheet was added alongside the old one instead of replacing it.');

    // array_keys() is list<int|string>; the cast keeps static analysis honest
    // without weakening the assertion — a numeric key here would still fail.
    $source = (string) $keys[0];

    self::assertStringStartsWith('https://fonts.googleapis.com/css2?', $source);
    self::assertStringContainsString('icon_names=grain,star', $source);
    self::assertSame('external', $theme[$keys[0]]['type']);
  }

  /**
   * The local path is GONE, not merely shadowed by a second entry.
   *
   * Leaving it behind would load both the subset and the 3.4 MB bundled font,
   * which is worse than doing nothing at all.
   */
  public function testTheLocalStylesheetKeyIsRemoved(): void {
    $libraries = $this->yamlShapedLibraries();
    $this->hook(['outlined' => ['star']])
      ->libraryInfoAlter($libraries, 'paragraph_bundle_icon');

    self::assertArrayNotHasKey(
      self::LOCAL_CSS,
      $libraries['paragraph-bundle-icon-outlined']['css']['theme'],
    );
  }

  /**
   * Existing options on the stylesheet survive the swap.
   */
  public function testStylesheetOptionsAreCarriedOver(): void {
    $libraries = $this->yamlShapedLibraries();
    $this->hook(['outlined' => ['star']])
      ->libraryInfoAlter($libraries, 'paragraph_bundle_icon');

    $theme = $libraries['paragraph-bundle-icon-outlined']['css']['theme'];
    $options = reset($theme);

    self::assertSame(200, $options['weight'], 'The weight was dropped, so cascade order changed.');
  }

  /**
   * A style with no icons in use keeps its bundled font.
   *
   * This is the fallback that separates this change from the one reverted in
   * 4ea0ce5. No icons means nothing to subset, so the local file must stay.
   */
  public function testStyleWithNoIconsKeepsItsBundledFont(): void {
    $libraries = $this->yamlShapedLibraries();
    $this->hook(['sharp' => ['settings']])
      ->libraryInfoAlter($libraries, 'paragraph_bundle_icon');

    self::assertArrayHasKey(
      self::LOCAL_CSS,
      $libraries['paragraph-bundle-icon-outlined']['css']['theme'],
    );
  }

  /**
   * Nothing at all happens when this site uses no icons.
   */
  public function testNoIconsAnywhereChangesNothing(): void {
    $libraries = $this->yamlShapedLibraries();
    $before = $libraries;
    $this->hook([])->libraryInfoAlter($libraries, 'paragraph_bundle_icon');

    self::assertSame($before, $libraries);
  }

  /**
   * Another extension's libraries are never touched.
   */
  public function testAnotherExtensionIsLeftAlone(): void {
    $libraries = $this->yamlShapedLibraries();
    $before = $libraries;
    $this->hook(['outlined' => ['star']])->libraryInfoAlter($libraries, 'paragraphs_bundles');

    self::assertSame($before, $libraries, 'The hook altered a library belonging to another module.');
  }

  /**
   * An unknown style produces no URL, so the library is left alone.
   */
  public function testUnknownStyleLeavesTheLibraryAlone(): void {
    $libraries = $this->yamlShapedLibraries();
    $before = $libraries;
    $this->hook(['squiggly' => ['star']])->libraryInfoAlter($libraries, 'paragraph_bundle_icon');

    self::assertSame($before, $libraries);
  }

}
