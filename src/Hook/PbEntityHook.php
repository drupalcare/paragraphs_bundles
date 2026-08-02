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
    $this->files->delete($this->slugger->slugify($entity->bundle()), (int) $entity->id());
    Cache::invalidateTags(['library_info']);
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

    $css = $this->builder->build($entity);
    if ($css === '') {
      $this->files->delete($slug, $id);
      Cache::invalidateTags(['library_info']);
      return;
    }
    $this->files->write($slug, $id, $css);
    Cache::invalidateTags(['library_info']);
  }

}
