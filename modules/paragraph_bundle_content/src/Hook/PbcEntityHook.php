<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_content\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Drupal\paragraphs_bundles\Service\Css\PbCssFileManagerInterface;
use Drupal\paragraphs_bundles\Service\Css\PbDynamicCssBuilder;
use Drupal\paragraphs_bundles\Service\Css\PbDynamicCssBuilderInterface;

/**
 * Node lifecycle hooks for pb_content page-background dynamic CSS.
 */
final class PbcEntityHook {

  public function __construct(
    private readonly PbCssFileManagerInterface $files,
    private readonly PbDynamicCssBuilderInterface $builder,
  ) {}

  /**
   * Implements hook_node_insert().
   */
  #[Hook('node_insert')]
  public function nodeInsert(EntityInterface $entity): void {
    $this->writeForNode($entity);
  }

  /**
   * Implements hook_node_update().
   */
  #[Hook('node_update')]
  public function nodeUpdate(EntityInterface $entity): void {
    $this->writeForNode($entity);
  }

  /**
   * Implements hook_node_delete().
   */
  #[Hook('node_delete')]
  public function nodeDelete(EntityInterface $entity): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'pb_content') {
      return;
    }
    $this->files->delete(PbDynamicCssBuilder::NODE_PB_CONTENT_SLUG, (int) $entity->id());
    Cache::invalidateTags(['library_info']);
  }

  /**
   * Build and write the CSS file for one pb_content node; remove if empty.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity from the hook callback.
   */
  private function writeForNode(EntityInterface $entity): void {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'pb_content') {
      return;
    }
    $slug = PbDynamicCssBuilder::NODE_PB_CONTENT_SLUG;
    $id = (int) $entity->id();
    $css = $this->builder->buildForNode($entity);
    if ($css === '') {
      $this->files->delete($slug, $id);
      Cache::invalidateTags(['library_info']);
      return;
    }
    $this->files->write($slug, $id, $css);
    Cache::invalidateTags(['library_info']);
  }

}
