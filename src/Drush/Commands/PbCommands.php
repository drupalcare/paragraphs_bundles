<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Drush\Commands;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_bundles\Service\Bundle\BundleSluggerInterface;
use Drupal\paragraphs_bundles\Service\Css\PbCssFileManagerInterface;
use Drupal\paragraphs_bundles\Service\Css\PbDynamicCssBuilder;
use Drupal\paragraphs_bundles\Service\Css\PbDynamicCssBuilderInterface;
use Drupal\paragraphs_bundles\Service\Form\PbFormDisplayVerifierInterface;
use Drupal\paragraphs_bundles\Service\Form\PbViewDisplayVerifierInterface;
use Drush\Attributes\Command;
use Drush\Attributes\Option;
use Drush\Attributes\Usage;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for paragraphs_bundles.
 */
final class PbCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PbCssFileManagerInterface $files,
    private readonly PbDynamicCssBuilderInterface $builder,
    private readonly BundleSluggerInterface $slugger,
    private readonly PbFormDisplayVerifierInterface $formDisplayVerifier,
    private readonly PbViewDisplayVerifierInterface $viewDisplayVerifier,
  ) {
    parent::__construct();
  }

  /**
   * Regenerate every per-entity dynamic CSS file.
   *
   * Walks paragraph entities and pb_content nodes, rebuilds each
   * `pb-{slug}-{id}.css` file, and busts the library_info cache.
   *
   * @param array<string, mixed> $options
   *   Drush options.
   */
  #[Command(name: 'pb:regenerate-css', aliases: ['pb-regen'])]
  #[Option(name: 'purge', description: 'Delete all existing files first.')]
  #[Usage(name: 'drush pb:regenerate-css', description: 'Rebuild CSS for every paragraph and pb_content node.')]
  #[Usage(name: 'drush pb:regenerate-css --purge', description: 'Wipe public://paragraphs_bundles/ first, then rebuild.')]
  public function regenerateCss(array $options = ['purge' => FALSE]): void {
    if (!empty($options['purge'])) {
      $deleted = $this->files->deleteAll();
      $this->logger()->success(dt('Purged @n existing CSS file(s).', ['@n' => $deleted]));
    }

    $written = 0;
    $removed = 0;

    $paragraphStorage = $this->entityTypeManager->getStorage('paragraph');
    $paragraphIds = $paragraphStorage->getQuery()->accessCheck(FALSE)->execute();
    if ($paragraphIds) {
      foreach (array_chunk($paragraphIds, 100) as $chunk) {
        /** @var \Drupal\paragraphs\ParagraphInterface[] $paragraphs */
        $paragraphs = $paragraphStorage->loadMultiple($chunk);
        foreach ($paragraphs as $paragraph) {
          if (!$paragraph instanceof ParagraphInterface) {
            continue;
          }
          $slug = $this->slugger->slugify($paragraph->bundle());
          $id = (int) $paragraph->id();
          $css = $this->builder->build($paragraph);
          if ($css === '') {
            if ($this->files->delete($slug, $id)) {
              $removed++;
            }
            continue;
          }
          if ($this->files->write($slug, $id, $css)) {
            $written++;
          }
        }
      }
    }

    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $nodeIds = $nodeStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'pb_content')
      ->execute();
    if ($nodeIds) {
      foreach (array_chunk($nodeIds, 100) as $chunk) {
        /** @var \Drupal\node\NodeInterface[] $nodes */
        $nodes = $nodeStorage->loadMultiple($chunk);
        foreach ($nodes as $node) {
          if (!$node instanceof NodeInterface) {
            continue;
          }
          $slug = PbDynamicCssBuilder::NODE_PB_CONTENT_SLUG;
          $id = (int) $node->id();
          $css = $this->builder->buildForNode($node);
          if ($css === '') {
            if ($this->files->delete($slug, $id)) {
              $removed++;
            }
            continue;
          }
          if ($this->files->write($slug, $id, $css)) {
            $written++;
          }
        }
      }
    }

    Cache::invalidateTags(['library_info']);

    $this->logger()->success(dt('Regenerated @w CSS file(s); removed @r empty file(s).', [
      '@w' => $written,
      '@r' => $removed,
    ]));
  }

  /**
   * Verify Display tab layout on every PB bundle form display (active config).
   *
   * Checks link colors sit in Display → Colors, legacy group_tab_* is gone,
   * and title field order when the bundle ships pb_content_title.
   */
  #[Command(name: 'pb:verify-form-displays', aliases: ['pb-verify-fd'])]
  #[Usage(name: 'drush pb:verify-form-displays', description: 'Scan all paragraph bundles with pb_display_bg.')]
  public function verifyFormDisplays(): int {
    $schema = (int) \Drupal::keyValue('system.schema')->get('paragraphs_bundles');
    if ($schema < 9006) {
      $this->logger()->warning(dt('paragraphs_bundles schema is @s; run drush updb (9006+) for form and view display parity.', [
        '@s' => $schema,
      ]));
    }

    $fieldStorage = $this->entityTypeManager->getStorage('field_config');
    $formStorage = $this->entityTypeManager->getStorage('entity_form_display');
    $viewStorage = $this->entityTypeManager->getStorage('entity_view_display');

    $bgIds = $fieldStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', 'paragraph')
      ->condition('field_name', 'pb_display_bg')
      ->execute();

    $bundles = [];
    foreach ($fieldStorage->loadMultiple($bgIds) as $field) {
      if ($field instanceof FieldConfig) {
        $bundles[] = $field->getTargetBundle();
      }
    }
    sort($bundles);

    $failed = 0;
    $skipped = 0;
    $passed = 0;

    foreach ($bundles as $bundle) {
      $formDisplay = $formStorage->load('paragraph.' . $bundle . '.default');
      if ($formDisplay === NULL) {
        $this->logger()->warning(dt('SKIP @bundle: no default form display.', [
          '@bundle' => $bundle,
        ]));
        $skipped++;
        continue;
      }

      $titleId = 'paragraph.' . $bundle . '.pb_content_title';
      $expectsTitle = (bool) $fieldStorage->load($titleId);

      $export = [
        'bundle' => $bundle,
        'content' => $formDisplay->get('content'),
        'third_party_settings' => $formDisplay->get('third_party_settings'),
        'dependencies' => $formDisplay->get('dependencies'),
      ];

      $errors = $this->formDisplayVerifier->verify(
        $export,
        $expectsTitle,
        TRUE,
      );

      $viewDisplay = $viewStorage->load('paragraph.' . $bundle . '.default');
      if ($viewDisplay !== NULL) {
        $viewExport = [
          'content' => $viewDisplay->get('content'),
          'hidden' => $viewDisplay->get('hidden'),
        ];
        $errors = array_merge($errors, $this->viewDisplayVerifier->verify($viewExport));
      }

      if ($errors === []) {
        $this->output()->writeln("OK: {$bundle}");
        $passed++;
        continue;
      }

      $this->output()->writeln("FAIL: {$bundle}");
      foreach ($errors as $error) {
        $this->output()->writeln("  - {$error}");
      }
      $failed++;
    }

    $this->output()->writeln('');
    $this->logger()->notice(dt('@ok passed, @fail failed, @skip skipped (@total bundles).', [
      '@ok' => $passed,
      '@fail' => $failed,
      '@skip' => $skipped,
      '@total' => count($bundles),
    ]));

    return $failed > 0 ? 1 : 0;
  }

}
