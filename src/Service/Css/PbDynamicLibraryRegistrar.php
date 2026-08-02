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
    return $this->definition($this->files->fileUrl($slug, $paragraphId));
  }

  /**
   * {@inheritdoc}
   */
  public function buildLibraries(): array {
    $libraries = [];
    foreach ($this->files->listAll() as $entry) {
      $url = $this->files->fileUrl($entry['slug'], $entry['id']);
      $libraries[$this->libraryName($entry['slug'], $entry['id'])] = $this->definition($url);
    }
    return $libraries;
  }

  /**
   * Build a single Drupal library definition.
   *
   * @param string $url
   *   Web-relative URL produced by {@see PbCssFileManagerInterface::fileUrl}.
   *
   * @return array{
   *   header: bool,
   *   css: array<string, array<string, array<string, mixed>>>
   *   }
   *   Drupal library definition.
   */
  private function definition(string $url): array {
    return [
      'header' => FALSE,
      'css' => [
        'theme' => [
          $url => [
            'weight' => self::ASSET_WEIGHT,
            'minified' => TRUE,
          ],
        ],
      ],
    ];
  }

}
