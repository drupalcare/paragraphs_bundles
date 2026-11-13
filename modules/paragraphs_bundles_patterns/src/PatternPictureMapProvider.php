<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_patterns;

use Drupal\paragraphs_bundles\Service\Picture\PbPictureMapProviderInterface;

/**
 * Hands the parent module's picture binder the `pictures` of these Patterns.
 *
 * The pattern files are the one source; this only reads them through the
 * repository.
 */
final class PatternPictureMapProvider implements PbPictureMapProviderInterface {

  public function __construct(
    private readonly PatternRepository $repository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function slots(): array {
    $slots = [];
    foreach ($this->repository->all() as $patterns) {
      foreach ($patterns as $pattern) {
        // A definition cached before `pictures` existed has no such key.
        foreach (array_keys($pattern['pictures'] ?? []) as $slot) {
          $slots[$slot] = $slot;
        }
      }
    }
    return array_values($slots);
  }

  /**
   * {@inheritdoc}
   */
  public function pictures(string $bundle, string $class): ?array {
    $pattern = $this->repository->byClass($bundle, $class);
    return $pattern === NULL ? NULL : ($pattern['pictures'] ?? []);
  }

}
