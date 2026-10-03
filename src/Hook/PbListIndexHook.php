<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_bundles\Service\ListIndex\ListIndexerInterface;
use Psr\Log\LoggerInterface;

/**
 * Keeps the Paragraphs List index in step with the paragraphs.
 *
 * Runs on the entity insert / update / delete hooks, which fire AFTER
 * entity_reference_revisions has given a new host's children their parent id,
 * so a brand-new node's tree is indexed with the real nid.
 */
final class PbListIndexHook {

  /**
   * The queue that rebuilds the whole index in chunks.
   */
  public const REBUILD_QUEUE = 'paragraphs_bundles_list_index_rebuild';

  /**
   * State key: a rebuild was asked for while one was already running.
   */
  public const DIRTY_STATE = 'paragraphs_bundles.list_index_dirty';

  public function __construct(
    private readonly ListIndexerInterface $indexer,
    private readonly QueueFactory $queueFactory,
    private readonly StateInterface $state,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Implements hook_entity_insert().
   */
  #[Hook('entity_insert')]
  public function entityInsert(EntityInterface $entity): void {
    $this->reindex($entity);
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity): void {
    $this->reindex($entity);
  }

  /**
   * Implements hook_entity_delete().
   *
   * The entity_reference_revisions module deletes a deleted host's paragraphs
   * LATER, from a queue; each fires this hook then and drops its own row.
   * Until then the rows are re-derived as orphans, which is what the
   * paragraphs now are.
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity): void {
    if ($entity->id() === NULL) {
      return;
    }
    try {
      if ($entity instanceof ParagraphInterface) {
        $this->indexer->removeParagraph((int) $entity->id());
      }
      elseif ($entity instanceof ContentEntityInterface && $this->indexer->hostHasParagraphs($entity)) {
        $this->indexer->indexDeletedHost($entity->getEntityTypeId(), (int) $entity->id());
      }
    }
    catch (\Throwable $e) {
      $this->failed($entity, $e);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for field_config.
   *
   * A paragraphs field's label is part of every label beneath it.
   */
  #[Hook('field_config_update')]
  public function fieldConfigUpdate(EntityInterface $field): void {
    if (!$field instanceof FieldConfigInterface) {
      return;
    }
    // Only a paragraphs field's label appears in a paragraph's label.
    if ($field->getType() !== 'entity_reference_revisions'
      || $field->getFieldStorageDefinition()->getSetting('target_type') !== 'paragraph') {
      return;
    }
    // getOriginal(), never ->original: the magic property is deprecated in
    // 11.2 and REMOVED in 12.0, where `->original ?? NULL` quietly yields NULL
    // and this hook would never queue the rebuild.
    $original = $field->getOriginal();
    if (!$original instanceof FieldConfigInterface || $original->getLabel() === $field->getLabel()) {
      return;
    }
    $this->queueRebuild();
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for paragraphs_type.
   *
   * An orphan's label names its paragraph type.
   */
  #[Hook('paragraphs_type_update')]
  public function paragraphsTypeUpdate(EntityInterface $type): void {
    // getOriginal(): see fieldConfigUpdate().
    $original = $type->getOriginal();
    if ($original instanceof EntityInterface && $original->label() !== $type->label()) {
      $this->queueRebuild();
    }
  }

  /**
   * Queues a chunked rebuild of the whole index.
   *
   * If one is already running, a flag is set instead; the worker restarts from
   * the beginning when its chain ends, so a change made after the cursor has
   * passed is not lost.
   */
  public function queueRebuild(): void {
    if (!$this->indexer->isAvailable()) {
      return;
    }
    $queue = $this->queueFactory->get(self::REBUILD_QUEUE);
    if ($queue->numberOfItems() === 0) {
      $queue->createItem(['after' => 0]);
    }
    else {
      $this->state->set(self::DIRTY_STATE, TRUE);
    }
  }

  /**
   * Reindexes a saved paragraph, or the tree beneath a saved host.
   */
  private function reindex(EntityInterface $entity): void {
    try {
      if ($entity instanceof ParagraphInterface) {
        $this->indexer->indexParagraph($entity);
      }
      elseif ($entity instanceof ContentEntityInterface && $this->indexer->hostHasParagraphs($entity)) {
        $this->indexer->indexHost($entity);
      }
    }
    catch (\Throwable $e) {
      $this->failed($entity, $e);
    }
  }

  /**
   * Records an index failure without letting it escape.
   *
   * The index is derived and repairable; these hooks run inside the entity
   * save's transaction, so an exception here would roll back the editor's
   * content. Log it and queue a rebuild instead.
   */
  private function failed(EntityInterface $entity, \Throwable $e): void {
    $this->logger->error('Paragraphs List index update failed for @type @id: @message', [
      '@type' => $entity->getEntityTypeId(),
      '@id' => $entity->id(),
      '@message' => $e->getMessage(),
    ]);
    try {
      $this->queueRebuild();
    }
    catch (\Throwable $queue_error) {
      $this->logger->error('Paragraphs List index: could not queue a rebuild: @message', ['@message' => $queue_error->getMessage()]);
    }
  }

}
