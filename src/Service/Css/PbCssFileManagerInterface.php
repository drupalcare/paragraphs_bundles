<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Css;

/**
 * Reads, writes, and deletes per-paragraph CSS files.
 *
 * Files live under `public://paragraphs_bundles/` and are
 * **content-addressed**: `pb-{slug}-{id}.{hash}.css` (e.g.
 * `pb-simple-123.a1b2c3d4e5.css`), where `{hash}` is a short digest of the
 * CSS bytes. When a paragraph's display settings change, the regenerated
 * content yields a new hash → new filename → new URL, so Drupal serves the
 * fresh file (rebuilding only that one aggregate) without a cache flush.
 * The library *name* (`dynamic--{slug}-{id}`) stays stable. The slug comes
 * from {@see \Drupal\paragraphs_bundles\Service\Bundle\BundleSluggerInterface}.
 *
 * The class only manages files. CSS *content* is built by
 * {@see PbDynamicCssBuilderInterface}; library entries are advertised
 * by {@see PbDynamicLibraryRegistrarInterface}.
 */
interface PbCssFileManagerInterface {

  /**
   * Public-files subdirectory that holds every per-paragraph CSS file.
   */
  public const string DIRECTORY = 'public://paragraphs_bundles';

  /**
   * Basename of the current content-hash file for a paragraph.
   *
   * @param string $slug
   *   Bundle slug (already kebab-cased).
   * @param int|string $paragraphId
   *   Paragraph entity ID.
   *
   * @return string
   *   Current file's basename (`pb-{slug}-{id}.{hash}.css`), or '' when no
   *   file exists yet.
   */
  public function fileName(string $slug, int|string $paragraphId): string;

  /**
   * Stream-wrapper URI of the current content-hash file for a paragraph.
   *
   * @param string $slug
   *   Bundle slug.
   * @param int|string $paragraphId
   *   Paragraph entity ID.
   *
   * @return string
   *   `public://paragraphs_bundles/pb-{slug}-{id}.{hash}.css`, or '' when no
   *   file exists yet.
   */
  public function fileUri(string $slug, int|string $paragraphId): string;

  /**
   * Build the web-relative URL Drupal can attach as a library asset.
   *
   * @param string $slug
   *   Bundle slug.
   * @param int|string $paragraphId
   *   Paragraph entity ID.
   *
   * @return string
   *   Path relative to the document root for the current hashed file, or ''
   *   when no file exists yet. The content hash in the filename is the
   *   cache-buster — a content change moves the URL, so no query string is
   *   needed or added.
   */
  public function fileUrl(string $slug, int|string $paragraphId): string;

  /**
   * Write a CSS file (creating directories as needed). Overwrites.
   *
   * @param string $slug
   *   Bundle slug.
   * @param int|string $paragraphId
   *   Paragraph entity ID.
   * @param string $css
   *   CSS source. Caller is responsible for cascade-layer wrapping.
   *
   * @return bool
   *   TRUE on success, FALSE on any failure (logged).
   */
  public function write(string $slug, int|string $paragraphId, string $css): bool;

  /**
   * Delete a CSS file. Idempotent — missing files return TRUE.
   *
   * @param string $slug
   *   Bundle slug.
   * @param int|string $paragraphId
   *   Paragraph entity ID.
   *
   * @return bool
   *   TRUE if the file is gone (deleted or never existed).
   */
  public function delete(string $slug, int|string $paragraphId): bool;

  /**
   * Whether the file exists on disk.
   *
   * @param string $slug
   *   Bundle slug.
   * @param int|string $paragraphId
   *   Paragraph entity ID.
   *
   * @return bool
   *   TRUE if `is_file()` succeeds for the resolved file URI.
   */
  public function exists(string $slug, int|string $paragraphId): bool;

  /**
   * Wipe every per-paragraph CSS file. Used by the regenerate command.
   *
   * @return int
   *   Count of files deleted.
   */
  public function deleteAll(): int;

  /**
   * List every per-paragraph CSS file as `{slug, id}` tuples.
   *
   * Used by {@see PbDynamicLibraryRegistrarInterface::buildLibraries}
   * to enumerate libraries; also used by the regenerate command to
   * discover orphaned files (paragraph deleted without unhooking).
   *
   * @return list<array{slug: string, id: int}>
   *   List of slug + paragraph-id pairs, in filesystem order.
   */
  public function listAll(): array;

}
