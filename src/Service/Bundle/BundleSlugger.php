<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Bundle;

/**
 * Default {@see BundleSluggerInterface} implementation.
 */
final class BundleSlugger implements BundleSluggerInterface {

  /**
   * Trailing suffix every v1 paragraph type carries.
   */
  private const string SUFFIX = '_bundle';

  /**
   * {@inheritdoc}
   */
  public function slugify(string $bundle): string {
    $bundle = strtolower(trim($bundle));
    if (str_ends_with($bundle, self::SUFFIX)) {
      $bundle = substr($bundle, 0, -strlen(self::SUFFIX));
    }
    return str_replace('_', '-', $bundle);
  }

}
