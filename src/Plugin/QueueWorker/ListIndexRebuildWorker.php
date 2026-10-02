<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paragraphs_bundles\Hook\PbListIndexHook;
use Drupal\paragraphs_bundles\Service\ListIndex\ListIndexerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Rebuilds the Paragraphs List index one chunk at a time.
 *
 * Each item rebuilds a chunk of paragraphs after a given id and queues the
 * next chunk, so a rebuild never holds one long request, however many
 * paragraphs the site has.
 */
#[QueueWorker(
  id: PbListIndexHook::REBUILD_QUEUE,
  title: new TranslatableMarkup('Paragraphs List index rebuild'),
  cron: ['time' => 60],
)]
final class ListIndexRebuildWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Paragraphs rebuilt per queue item.
   */
  public const CHUNK = 500;

  /**
   * Constructs the worker.
   *
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\paragraphs_bundles\Service\ListIndex\ListIndexerInterface $indexer
   *   The indexer.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected ListIndexerInterface $indexer,
    protected QueueFactory $queueFactory,
    protected StateInterface $state,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(ListIndexerInterface::class),
      $container->get('queue'),
      $container->get('state'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $after = is_array($data) && isset($data['after']) ? (int) $data['after'] : 0;
    $last = $this->indexer->rebuildChunk($after, self::CHUNK);
    if ($last !== NULL) {
      $this->queueFactory->get(PbListIndexHook::REBUILD_QUEUE)->createItem(['after' => $last]);
    }
    elseif ($this->state->get(PbListIndexHook::DIRTY_STATE, FALSE)) {
      // A rebuild was asked for while this one ran: ids below the cursor may be
      // stale, so go round again from the start.
      $this->state->delete(PbListIndexHook::DIRTY_STATE);
      $this->queueFactory->get(PbListIndexHook::REBUILD_QUEUE)->createItem(['after' => 0]);
    }
  }

}
