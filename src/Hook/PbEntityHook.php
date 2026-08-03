<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_bundles\Service\Bundle\BundleSluggerInterface;
use Drupal\paragraphs_bundles\Service\Css\PbCssFileManagerInterface;
use Drupal\paragraphs_bundles\Service\Css\PbDynamicCssBuilderInterface;

/**
 * Entity lifecycle hooks for the per-paragraph CSS pipeline.
 *
 * On every paragraph save we (re)build its CSS file; on delete we
 * remove the file. Library cache is cleared at the very end of the
 * request so the new file becomes addressable as soon as Drupal
 * rebuilds its library registry on the next page request.
 */
final class PbEntityHook {

  public function __construct(
    private readonly PbCssFileManagerInterface $files,
    private readonly PbDynamicCssBuilderInterface $builder,
    private readonly BundleSluggerInterface $slugger,
  ) {}

  /**
   * Implements hook_paragraph_insert().
   */
  #[Hook('paragraph_insert')]
  public function paragraphInsert(EntityInterface $entity): void {
    $this->writeForParagraph($entity);
  }

  /**
   * Implements hook_paragraph_update().
   */
  #[Hook('paragraph_update')]
  public function paragraphUpdate(EntityInterface $entity): void {
    $this->writeForParagraph($entity);
  }

  /**
   * Implements hook_paragraph_delete().
   */
  #[Hook('paragraph_delete')]
  public function paragraphDelete(EntityInterface $entity): void {
    if (!$entity instanceof ParagraphInterface) {
      return;
    }
    $slug = $this->slugger->slugify($entity->bundle());
    $id = (int) $entity->id();
    if ($this->files->fileName($slug, $id) === '') {
      // Nothing on disk, so no library existed to invalidate.
      return;
    }
    $this->files->delete($slug, $id);
    $this->invalidateLibraryInfo();
  }

  /**
   * Build and write the CSS file for one paragraph; remove if empty.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity from the hook callback. Non-paragraph entities are
   *   ignored.
   */
  private function writeForParagraph(EntityInterface $entity): void {
    if (!$entity instanceof ParagraphInterface) {
      return;
    }
    $slug = $this->slugger->slugify($entity->bundle());
    $id = (int) $entity->id();

    // File names are content-addressed, so the name only moves when the CSS
    // actually changes — and the library definition is just that URL. Compare
    // before and after and invalidate only on a real change; re-saving a
    // paragraph whose display settings did not change now costs nothing.
    $before = $this->files->fileName($slug, $id);

    $css = $this->builder->build($entity);
    if ($css === '') {
      if ($before !== '') {
        $this->files->delete($slug, $id);
        $this->invalidateLibraryInfo();
      }
      return;
    }
    $this->files->write($slug, $id, $css);
    if ($this->files->fileName($slug, $id) !== $before) {
      $this->invalidateLibraryInfo();
    }
  }

  /**
   * Invalidates the `library_info` tag for a real change to a CSS file.
   *
   * Deliberately not de-duplicated per request. Collapsing repeated
   * invalidations to the first one looks like an easy win and is wrong: a tag
   * invalidation only invalidates entries older than it, so anything cached
   * *between* two writes in the same request would survive a change it should
   * not have. The saving that matters is not invalidating at all when nothing
   * changed, which the caller checks; repeating a tag write for genuine
   * changes is cheap next to serving stale CSS.
   */
  private function invalidateLibraryInfo(): void {
    Cache::invalidateTags(['library_info']);
  }

}
