<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Css;

/**
 * Default {@see PbDynamicLibraryRegistrarInterface} implementation.
 */
final class PbDynamicLibraryRegistrar implements PbDynamicLibraryRegistrarInterface {

  public function __construct(
    private readonly PbCssFileManagerInterface $files,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function libraryName(string $slug, int|string $paragraphId): string {
    return self::LIBRARY_PREFIX . $slug . '-' . (string) $paragraphId;
  }

  /**
   * {@inheritdoc}
   */
  public function buildLibrary(string $slug, int|string $paragraphId): ?array {
    if (!$this->files->exists($slug, $paragraphId)) {
      return NULL;
    }
    return $this->definition($this->files->fileUri($slug, $paragraphId));
  }

  /**
   * {@inheritdoc}
   */
  public function buildLibraries(): array {
    $libraries = [];
    foreach ($this->files->listAll() as $entry) {
      $uri = $this->files->fileUri($entry['slug'], $entry['id']);
      $libraries[$this->libraryName($entry['slug'], $entry['id'])] = $this->definition($uri);
    }
    return $libraries;
  }

  /**
   * Build a single Drupal library definition.
   *
   * @param string $uri
   *   Stream-wrapper URI produced by
   *   {@see PbCssFileManagerInterface::fileUri}.
   *
   *   It must be the `public://` URI and NOT the URL: the library parser
   *   treats a key starting with `/` as docroot-relative and strips the
   *   slash without removing the site's base path, so on a site installed
   *   in a subdirectory (drupal.org's CI serves `/subdirectory`) the URL
   *   `/subdirectory/sites/x/files/...` became the unreachable
   *   `/subdirectory/subdirectory/sites/x/files/...` and the stylesheet
   *   never applied. A stream-wrapper URI is turned into the right URL by
   *   the asset renderer at output time.
   *
   * @return array{
   *   header: bool,
   *   css: array<string, array<string, array<string, mixed>>>
   *   }
   *   Drupal library definition.
   */
  private function definition(string $uri): array {
    return [
      'header' => FALSE,
      'css' => [
        'theme' => [
          $uri => [
            'weight' => self::ASSET_WEIGHT,
            'minified' => TRUE,
          ],
        ],
      ],
    ];
  }

}
