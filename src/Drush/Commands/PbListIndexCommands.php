<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Drush\Commands;

use Drupal\paragraphs_bundles\Service\ListIndex\ListIndexerInterface;
use Drush\Attributes\Command;
use Drush\Attributes\Option;
use Drush\Attributes\Usage;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the Paragraphs List index.
 */
final class PbListIndexCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private readonly ListIndexerInterface $indexer,
  ) {
    parent::__construct();
  }

  /**
   * Rebuilds the Paragraphs List index from the paragraphs, in chunks.
   *
   * Safe to run at any size and to interrupt: it walks the paragraphs in id
   * order and rewrites each row from the paragraphs' own parent columns. Use
   * --from to resume after an interruption.
   *
   * @param array<string, mixed> $options
   *   Drush options.
   */
  #[Command(name: 'paragraphs-bundles:list-index:rebuild')]
  #[Option(name: 'chunk', description: 'Paragraphs per chunk.')]
  #[Option(name: 'from', description: 'Resume after this paragraph id.')]
  #[Usage(name: 'drush paragraphs-bundles:list-index:rebuild', description: 'Rebuild every row, then drop rows of deleted paragraphs.')]
  public function rebuild(array $options = ['chunk' => 500, 'from' => 0]): int {
    if (!$this->indexer->isAvailable()) {
      $this->logger()->error(dt('The index table does not exist. Run drush updatedb first.'));
      return self::EXIT_FAILURE;
    }
    $chunk = max((int) $options['chunk'], 1);
    $after = max((int) $options['from'], 0);
    $chunks = 0;
    while (($last = $this->indexer->rebuildChunk($after, $chunk)) !== NULL) {
      $after = $last;
      $chunks++;
      if ($chunks % 20 === 0) {
        $this->logger()->notice(dt('Rebuilt up to paragraph @id.', ['@id' => $after]));
      }
    }
    $removed = $options['from'] ? 0 : $this->indexer->pruneMissing();
    $this->logger()->success(dt('Rebuilt @chunks chunk(s), last paragraph id @id; removed @removed row(s) of deleted paragraphs.', [
      '@chunks' => $chunks,
      '@id' => $after,
      '@removed' => $removed,
    ]));
    return self::EXIT_SUCCESS;
  }

  /**
   * Checks the Paragraphs List index against a fresh derivation.
   *
   * Exits non-zero when any row is missing, extra or different, so a deploy
   * pipeline can stop on it.
   *
   * @param array<string, mixed> $options
   *   Drush options.
   */
  #[Command(name: 'paragraphs-bundles:list-index:verify')]
  #[Option(name: 'all', description: 'Check every paragraph and every row, not a sample.')]
  #[Option(name: 'sample', description: 'Paragraphs to check when not using --all.')]
  #[Usage(name: 'drush paragraphs-bundles:list-index:verify', description: 'Check a sample of 200 paragraphs.')]
  #[Usage(name: 'drush paragraphs-bundles:list-index:verify --all', description: 'Check everything (slow on a large site).')]
  public function verify(array $options = ['all' => FALSE, 'sample' => 200]): int {
    if (!$this->indexer->isAvailable()) {
      $this->logger()->error(dt('The index table does not exist. Run drush updatedb first.'));
      return self::EXIT_FAILURE;
    }
    $result = $this->indexer->verify(!empty($options['all']) ? NULL : max((int) $options['sample'], 1));
    foreach (array_slice($result['mismatches'], 0, 50) as $line) {
      $this->logger()->error($line);
    }
    if ($result['mismatches'] !== []) {
      $this->logger()->error(dt('@bad mismatch(es) in @checked paragraph(s) checked. Run drush paragraphs-bundles:list-index:rebuild.', [
        '@bad' => count($result['mismatches']),
        '@checked' => $result['checked'],
      ]));
      return self::EXIT_FAILURE;
    }
    $this->logger()->success(dt('The index matches the paragraphs (@checked checked).', ['@checked' => $result['checked']]));
    return self::EXIT_SUCCESS;
  }

}
