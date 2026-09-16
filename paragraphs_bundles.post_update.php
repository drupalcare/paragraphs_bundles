<?php

/**
 * @file
 * Post-update hooks for Paragraphs Bundles.
 */

declare(strict_types=1);

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\Cache;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_bundles\Service\Css\PbDynamicCssBuilder;

/**
 * Regenerates every per-paragraph and pb_content dynamic CSS file.
 */
function paragraphs_bundles_post_update_regenerate_dynamic_css(array &$sandbox): string {
  // The docblock above is ONE line and carries no @param/@return, matching
  // core's batched post_updates (node_post_update_create_promote_base_field
  // _overrides, search_post_update_block_with_empty_page_id). `drush
  // updatedb:status` renders the entire docblock — tags included — in a
  // ~40-char column, so anything more turns the screen a site owner reads
  // before upgrading production into an unreadable wall of text.
  //
  // This serves two jobs with one implementation:
  //
  //  1. V1 -> V2 upgrade backfill. V1 stored colour/background values inline on
  //     the rendered markup (style="--pb-bg:rgba(...);"). V2 reads the same
  //     fields and writes one CSS file per paragraph to
  //     public://paragraphs_bundles/.
  //  2. Healing. Rebuilds the cacheable file/library "fast path" after a deploy
  //     or a wiped public:// directory, and invalidates the library registry so
  //     the freshly written files become addressable. The render-time inline
  //     fallback in PbPreprocessHook covers the window in between; to heal on
  //     *every* deploy, add `drush pb:regenerate-css` to the pipeline.
  //
  // WHY A post_update AND NOT A hook_update_N. It reads the pb_display_* fields
  // that updates 9005 and 9006 create and wire. hook_update_N runs in numeric
  // order, so any number below 9006 would read fields that do not exist yet and
  // write empty stylesheets — the exact bug that shipped as the old
  // update_9005. Numbering it above 9006 would only defer the problem to the
  // next update hook added. Drupal runs all post_updates after all update
  // hooks, so the ordering here is structural, not a convention to remember.
  // Confirmed on a real v1 -> v2 run: this executes after every 9005/9006 and
  // after all three sub-module updates.
  //
  // BATCHING. Walks an id cursor per entity type rather than materialising
  // every id into the sandbox. The sandbox is serialised to the database
  // between batches, so a work list of every paragraph on a large site is
  // itself a scaling problem.
  //
  // FAILURE ISOLATION. One unbuildable entity must not strand the whole
  // upgrade. Each entity is handled in its own try/catch: a failure is logged
  // and the cursor still advances, so the batch always makes progress and the
  // run finishes with a count of what was skipped. Without this, a single bad
  // paragraph both aborts `drush updb` and leaves the cursor unmoved, so the
  // retry re-fetches the same batch forever.
  $entityTypeManager = \Drupal::entityTypeManager();
  /** @var \Drupal\paragraphs_bundles\Service\Css\PbCssFileManagerInterface $files */
  $files = \Drupal::service('Drupal\paragraphs_bundles\Service\Css\PbCssFileManagerInterface');
  /** @var \Drupal\paragraphs_bundles\Service\Css\PbDynamicCssBuilderInterface $builder */
  $builder = \Drupal::service('Drupal\paragraphs_bundles\Service\Css\PbDynamicCssBuilderInterface');
  /** @var \Drupal\paragraphs_bundles\Service\Bundle\BundleSluggerInterface $slugger */
  $slugger = \Drupal::service('Drupal\paragraphs_bundles\Service\Bundle\BundleSluggerInterface');
  $logger = \Drupal::logger('paragraphs_bundles');

  $paragraphStorage = $entityTypeManager->getStorage('paragraph');
  $nodeStorage = $entityTypeManager->getStorage('node');

  if (!isset($sandbox['phase'])) {
    $sandbox['phase'] = 'paragraph';
    $sandbox['last_id'] = 0;
    $sandbox['progress'] = 0;
    $sandbox['failed'] = 0;
    $sandbox['total'] = (int) $paragraphStorage->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute()
      + (int) $nodeStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'pb_content')
        ->count()
        ->execute();
  }

  // The sandbox is serialised to the database between batches, so nothing can
  // be assumed about the types coming back. Read it into typed locals once
  // here and write them back at the end, instead of casting at each use.
  $phase = is_string($sandbox['phase']) ? $sandbox['phase'] : 'paragraph';
  $last_id = is_numeric($sandbox['last_id'] ?? NULL) ? (int) $sandbox['last_id'] : 0;
  $progress = is_numeric($sandbox['progress'] ?? NULL) ? (int) $sandbox['progress'] : 0;
  $failed = is_numeric($sandbox['failed'] ?? NULL) ? (int) $sandbox['failed'] : 0;
  $total = is_numeric($sandbox['total'] ?? NULL) ? (int) $sandbox['total'] : 0;

  $batch_size = 50;

  if ($phase === 'paragraph') {
    $query = $paragraphStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('id', $last_id, '>')
      ->sort('id')
      ->range(0, $batch_size);
    $ids = $query->execute();

    if (!$ids) {
      // Paragraphs done; hand the cursor to the node phase.
      $phase = 'node';
      $last_id = 0;
    }
    else {
      foreach ($paragraphStorage->loadMultiple($ids) as $paragraph) {
        // Advance the cursor FIRST. Every exit from this iteration — success,
        // wrong type, or exception — must move past this id, or the next batch
        // re-fetches it and the update never terminates.
        $id = (int) $paragraph->id();
        $last_id = $id;
        $progress++;

        if (!$paragraph instanceof ParagraphInterface) {
          continue;
        }
        try {
          $slug = $slugger->slugify($paragraph->bundle());
          $css = $builder->build($paragraph);
          if ($css === '') {
            $files->delete($slug, $id);
          }
          else {
            $files->write($slug, $id, $css);
          }
        }
        catch (\Throwable $e) {
          $failed++;
          $logger->error(
            'Could not regenerate dynamic CSS for paragraph @id (@bundle): @message',
            [
              '@id' => $id,
              '@bundle' => $paragraph->bundle(),
              '@message' => $e->getMessage(),
            ],
          );
        }
      }
    }
  }
  elseif ($phase === 'node') {
    $ids = $nodeStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'pb_content')
      ->condition('nid', $last_id, '>')
      ->sort('nid')
      ->range(0, $batch_size)
      ->execute();

    if (!$ids) {
      $phase = 'done';
    }
    else {
      foreach ($nodeStorage->loadMultiple($ids) as $node) {
        $id = (int) $node->id();
        $last_id = $id;
        $progress++;

        if (!$node instanceof NodeInterface) {
          continue;
        }
        try {
          $slug = PbDynamicCssBuilder::NODE_PB_CONTENT_SLUG;
          $css = $builder->buildForNode($node);
          if ($css === '') {
            $files->delete($slug, $id);
          }
          else {
            $files->write($slug, $id, $css);
          }
        }
        catch (\Throwable $e) {
          $failed++;
          $logger->error(
            'Could not regenerate dynamic CSS for pb_content node @id: @message',
            ['@id' => $id, '@message' => $e->getMessage()],
          );
        }
      }
    }
  }

  $done = $phase === 'done';

  $sandbox['phase'] = $phase;
  $sandbox['last_id'] = $last_id;
  $sandbox['progress'] = $progress;
  $sandbox['failed'] = $failed;
  // Never report 1 from the ratio alone: `total` is counted once up front, so
  // content created during a long run would let progress reach it while ids
  // remain. Only the exhausted cursor ends the batch.
  $sandbox['#finished'] = $done
    ? 1
    : ($total > 0 ? min(0.99, $progress / $total) : 0.99);

  if ($done) {
    Cache::invalidateTags(['library_info']);

    if ($failed > 0) {
      return (string) t(
        'Regenerated dynamic CSS files: @progress processed, @failed failed (see the paragraphs_bundles log).',
        ['@progress' => $progress, '@failed' => $failed],
      );
    }
    return (string) t('Regenerated dynamic CSS files: @progress processed.', [
      '@progress' => $progress,
    ]);
  }

  return (string) t('Regenerating dynamic CSS files: @progress / @total.', [
    '@progress' => $sandbox['progress'],
    '@total' => $sandbox['total'],
  ]);
}

/**
 * Gives each section a sentence saying what it is for.
 */
function paragraphs_bundles_post_update_bundle_descriptions(): string {
  // WHY AN UPDATE HOOK AT ALL. The sentences ship in config/optional, and
  // config/optional is installed ONCE — when the module that provides it is
  // installed. Config added to a module that is already installed never
  // arrives on its own. Measured on sts.local 2026-09-15 on a different
  // change: the files were right, the site had none of them, and there was
  // nothing pending to explain it. So every site that already has these
  // bundles needs this, and a fresh install ignores it.
  //
  // WHY IT READS THE SHIPPED FILES INSTEAD OF CARRYING THE SENTENCES. A copy
  // of 31 strings in PHP is a second place for them to be edited, and the two
  // would drift the first time somebody improved one. The yml is the source;
  // this only moves it.
  //
  // IT NEVER OVERWRITES A SITE'S OWN WORDS. A description that is already
  // filled in was written by somebody, and their sentence about their site
  // beats the module's. Only empty ones are filled.
  $storage = \Drupal::entityTypeManager()->getStorage('paragraphs_type');
  $directory = \Drupal::root() . '/' . \Drupal::service('extension.list.module')->getPath('paragraphs_bundles');

  $files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
  );

  $filled = 0;
  $kept = 0;

  foreach ($files as $file) {
    $name = $file->getFilename();

    if (!str_starts_with($name, 'paragraphs.paragraphs_type.') || !str_ends_with($name, '.yml')) {
      continue;
    }

    $data = Yaml::decode((string) file_get_contents($file->getPathname()));

    if (!is_array($data)) {
      continue;
    }

    // `id`, not `type`. A node type config keys its machine name as `type`,
    // and a paragraphs type keys it as `id` — measured across all 39 shipped
    // files on 2026-09-16, after the first version of this hook read `type`,
    // matched nothing, and correctly reported "Described 0 section(s)".
    // The hook was honest; the key was wrong.
    $id = (string) ($data['id'] ?? '');
    $sentence = trim((string) ($data['description'] ?? ''));

    // The eight section bundles ship no sentence on purpose: a slide is never
    // chosen from a list, it arrives with its slideshow.
    if ($id === '' || $sentence === '') {
      continue;
    }

    $type = $storage->load($id);

    if ($type === NULL) {
      continue;
    }

    if (trim((string) $type->getDescription()) !== '') {
      $kept++;
      continue;
    }

    $type->set('description', $sentence);
    $type->save();
    $filled++;
  }

  return (string) t('Described @filled section(s); left @kept with the words this site already had.', [
    '@filled' => $filled,
    '@kept' => $kept,
  ]);
}
