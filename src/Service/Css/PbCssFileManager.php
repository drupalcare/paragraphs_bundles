<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Css;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Psr\Log\LoggerInterface;

/**
 * Default {@see PbCssFileManagerInterface} implementation.
 *
 * Mirror of `apex.css.file_manager`: write the CSS to a real file in
 * `public://paragraphs_bundles/` so Drupal's asset pipeline can serve
 * it like any other library asset (cacheable, gzippable, aggregatable).
 *
 * Files are **content-addressed**: the name is
 * `pb-{slug}-{id}.{hash}.css`, where `{hash}` is a short digest of the
 * CSS bytes. When an editor changes a paragraph's display settings the
 * regenerated content produces a new hash → a new filename → a new URL,
 * so Drupal serves the fresh file (and rebuilds only that one aggregate)
 * without a full cache flush. The library *name* stays stable
 * (`dynamic--{slug}-{id}`), so templates and the render cache are
 * unaffected — only the file URL moves when the content actually changes.
 */
final class PbCssFileManager implements PbCssFileManagerInterface {

  /**
   * Hex length of the content hash embedded in each filename.
   */
  private const int HASH_LENGTH = 10;

  /**
   * Per-request index of the CSS directory: "{slug}-{id}" => list of basenames.
   *
   * Every paragraph rendered on a page asks whether its CSS file exists, and
   * each of those questions used to cost a `glob()` — so a page with 60
   * paragraphs made 60 directory scans to answer 60 questions about the same
   * directory. One scan answers all of them. NULL means "not yet scanned";
   * writes and deletes keep it in step rather than dropping it, so a save that
   * touches many paragraphs does not rescan per paragraph either.
   *
   * @var array<string, list<string>>|null
   */
  private ?array $index = NULL;

  public function __construct(
    private readonly FileSystemInterface $fileSystem,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function fileName(string $slug, int|string $paragraphId): string {
    $uri = $this->currentUri($slug, $paragraphId);
    return $uri === NULL ? '' : basename($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function fileUri(string $slug, int|string $paragraphId): string {
    return $this->currentUri($slug, $paragraphId) ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function fileUrl(string $slug, int|string $paragraphId): string {
    $uri = $this->currentUri($slug, $paragraphId);
    return $uri === NULL
      ? ''
      : $this->fileUrlGenerator->generateString($uri);
  }

  /**
   * {@inheritdoc}
   */
  public function write(string $slug, int|string $paragraphId, string $css): bool {
    $directory = self::DIRECTORY;
    if (!$this->fileSystem->prepareDirectory(
      $directory,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
    )) {
      $this->logger->error(
        'Could not prepare PB dynamic-CSS directory @dir.',
        ['@dir' => $directory],
      );
      return FALSE;
    }

    $name = sprintf(
      'pb-%s-%s.%s.css',
      $slug,
      (string) $paragraphId,
      substr(hash('xxh128', $css), 0, self::HASH_LENGTH),
    );
    // Prune any prior hash variant so exactly the current-content file
    // remains: the moved URL is what busts the cache on the next request.
    $this->pruneExcept($slug, $paragraphId, $name);

    $uri = $directory . '/' . $name;
    try {
      $this->fileSystem->saveData($css, $uri, FileExists::Replace);
      // Keep the index in step instead of dropping it: a node save touching
      // many paragraphs would otherwise rescan the directory once per
      // paragraph, which is the cost this index exists to remove.
      if ($this->index !== NULL) {
        $this->index[$this->indexKey($slug, $paragraphId)] = [$name];
      }
      return TRUE;
    }
    catch (\Throwable $e) {
      $this->logger->error(
        'Failed to write PB dynamic CSS @uri: @msg',
        ['@uri' => $uri, '@msg' => $e->getMessage()],
      );
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete(string $slug, int|string $paragraphId): bool {
    $ok = TRUE;
    $uris = $this->matchingUris($slug, $paragraphId);
    if ($this->index !== NULL) {
      unset($this->index[$this->indexKey($slug, $paragraphId)]);
    }
    foreach ($uris as $uri) {
      try {
        $this->fileSystem->delete($uri);
      }
      catch (\Throwable $e) {
        $this->logger->warning(
          'Failed to delete PB dynamic CSS @uri: @msg',
          ['@uri' => $uri, '@msg' => $e->getMessage()],
        );
        $ok = FALSE;
      }
    }
    return $ok;
  }

  /**
   * {@inheritdoc}
   */
  public function exists(string $slug, int|string $paragraphId): bool {
    return $this->currentUri($slug, $paragraphId) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll(): int {
    $directory = $this->fileSystem->realpath(self::DIRECTORY);
    if ($directory === FALSE || !is_dir($directory)) {
      return 0;
    }
    $count = 0;
    foreach ((array) glob($directory . '/pb-*.css') as $path) {
      if (!is_string($path) || !is_file($path)) {
        continue;
      }
      $uri = self::DIRECTORY . '/' . basename($path);
      try {
        $this->fileSystem->delete($uri);
        $count++;
      }
      catch (\Throwable $e) {
        $this->logger->warning(
          'Failed to delete PB dynamic CSS @uri during deleteAll: @msg',
          ['@uri' => $uri, '@msg' => $e->getMessage()],
        );
      }
    }
    $this->index = NULL;
    return $count;
  }

  /**
   * {@inheritdoc}
   */
  public function listAll(): array {
    $directory = $this->fileSystem->realpath(self::DIRECTORY);
    if ($directory === FALSE || !is_dir($directory)) {
      return [];
    }
    $out = [];
    $seen = [];
    foreach ((array) glob($directory . '/pb-*.css') as $path) {
      if (!is_string($path) || !is_file($path)) {
        continue;
      }
      $name = basename($path);
      if (preg_match(
        '/^pb-(?<slug>[a-z0-9][a-z0-9-]*)-(?<id>\d+)\.[a-z0-9]+\.css$/',
        $name,
        $m,
      ) !== 1) {
        continue;
      }
      $key = $m['slug'] . '-' . $m['id'];
      if (isset($seen[$key])) {
        continue;
      }
      $seen[$key] = TRUE;
      $out[] = ['slug' => (string) $m['slug'], 'id' => (int) $m['id']];
    }
    return $out;
  }

  /**
   * URI of the current hashed CSS file for a paragraph, or NULL if none.
   *
   * @param string $slug
   *   Bundle slug.
   * @param int|string $paragraphId
   *   Paragraph entity ID.
   *
   * @return string|null
   *   Stream-wrapper URI, or NULL when the file does not exist.
   */
  private function currentUri(string $slug, int|string $paragraphId): ?string {
    return $this->matchingUris($slug, $paragraphId)[0] ?? NULL;
  }

  /**
   * Stream-wrapper URIs of every hash variant for one paragraph.
   *
   * @param string $slug
   *   Bundle slug.
   * @param int|string $paragraphId
   *   Paragraph entity ID.
   *
   * @return list<string>
   *   Matching `public://` URIs (normally one; more only after a failed
   *   prune).
   */
  private function matchingUris(string $slug, int|string $paragraphId): array {
    $names = $this->index()[$this->indexKey($slug, $paragraphId)] ?? [];
    return array_map(
      static fn (string $name): string => self::DIRECTORY . '/' . $name,
      $names,
    );
  }

  /**
   * Index key for one paragraph's CSS files.
   *
   * @param string $slug
   *   Bundle slug.
   * @param int|string $paragraphId
   *   Paragraph entity ID.
   *
   * @return string
   *   Key into the directory index.
   */
  private function indexKey(string $slug, int|string $paragraphId): string {
    return $slug . '-' . (string) $paragraphId;
  }

  /**
   * Scans the CSS directory once per request and indexes it by paragraph.
   *
   * @return array<string, list<string>>
   *   Basenames keyed by "{slug}-{id}", newest-first is not meaningful — a
   *   paragraph normally has exactly one, more only after a failed prune.
   */
  private function index(): array {
    if ($this->index !== NULL) {
      return $this->index;
    }
    $this->index = [];
    $directory = $this->fileSystem->realpath(self::DIRECTORY);
    if ($directory === FALSE || !is_dir($directory)) {
      return $this->index;
    }
    foreach ((array) glob($directory . '/pb-*.css') ?: [] as $path) {
      if (!is_string($path) || !is_file($path)) {
        continue;
      }
      $name = basename($path);
      // Same shape the listing enforces: the literal dot after the id keeps
      // `pb-x-24.*.css` from matching `pb-x-240.*.css`.
      if (preg_match(
        '/^pb-(?<slug>[a-z0-9][a-z0-9-]*)-(?<id>\d+)\.[a-z0-9]+\.css$/',
        $name,
        $m,
      ) !== 1) {
        continue;
      }
      $this->index[$m['slug'] . '-' . $m['id']][] = $name;
    }
    return $this->index;
  }

  /**
   * Delete every hash variant for a paragraph except one basename.
   *
   * @param string $slug
   *   Bundle slug.
   * @param int|string $paragraphId
   *   Paragraph entity ID.
   * @param string $keepName
   *   Basename to preserve (the file about to be written).
   */
  private function pruneExcept(
    string $slug,
    int|string $paragraphId,
    string $keepName,
  ): void {
    // Pre-hash (v2.0) files were named `pb-{slug}-{id}.css`; remove that
    // legacy variant too so a paragraph migrates fully on its first re-save.
    $stale = $this->matchingUris($slug, $paragraphId);
    $stale[] = self::DIRECTORY . '/'
      . sprintf('pb-%s-%s.css', $slug, (string) $paragraphId);
    foreach ($stale as $uri) {
      if (basename($uri) === $keepName) {
        continue;
      }
      $real = $this->fileSystem->realpath($uri);
      if ($real === FALSE || !is_file($real)) {
        continue;
      }
      try {
        $this->fileSystem->delete($uri);
      }
      catch (\Throwable $e) {
        $this->logger->warning(
          'Failed to prune stale PB dynamic CSS @uri: @msg',
          ['@uri' => $uri, '@msg' => $e->getMessage()],
        );
      }
    }
  }

}
