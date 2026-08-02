<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Css;

/**
 * Builds dynamic library definitions for per-paragraph CSS files.
 *
 * Each rendered paragraph wants its own asset library
 * (`paragraphs_bundles/dynamic--{slug}-{id}`). Drupal calls
 * `hook_library_info_build()` once per cache build; this service
 * collects every on-disk file in `public://paragraphs_bundles/` and
 * returns one library entry per file, weighted to load *after* the
 * theme.
 */
interface PbDynamicLibraryRegistrarInterface {

  /**
   * Library-name prefix for one-paragraph entries.
   *
   * Full library id: `paragraphs_bundles/dynamic--{slug}-{id}`.
   */
  public const string LIBRARY_PREFIX = 'dynamic--';

  /**
   * Asset weight that pins dynamic CSS after every theme stylesheet.
   *
   * Combined with CSS_AGGREGATE_THEME in PbCssAlterHook (same pattern
   * as apex_color region-colors at 999). Weight 1000 keeps editor-set
   * paragraph colours last on the page.
   */
  public const int ASSET_WEIGHT = 1000;

  /**
   * Build the library array for `hook_library_info_build`.
   *
   * @return array<string, array{
   *   header: bool,
   *   css: array<string, array<string, array<string, mixed>>>
   *   }>
   *   Drupal library definitions keyed by library name (without the
   *   `paragraphs_bundles/` prefix).
   */
  public function buildLibraries(): array;

  /**
   * Build a single library entry for one slug+id pair.
   *
   * Used at render time (preprocess) so a paragraph that has just been
   * saved during the same request has its library available without
   * waiting for the next library cache rebuild.
   *
   * @param string $slug
   *   Bundle slug.
   * @param int|string $paragraphId
   *   Paragraph entity ID.
   *
   * @return array{
   *   header: bool,
   *   css: array<string, array<string, array<string, mixed>>>
   *   }|null
   *   Library definition, or NULL when the file is missing.
   */
  public function buildLibrary(string $slug, int|string $paragraphId): ?array;

  /**
   * Compute the library name (without module prefix) for a paragraph.
   *
   * @param string $slug
   *   Bundle slug.
   * @param int|string $paragraphId
   *   Paragraph entity ID.
   *
   * @return string
   *   `dynamic--{slug}-{id}`.
   */
  public function libraryName(string $slug, int|string $paragraphId): string;

}
