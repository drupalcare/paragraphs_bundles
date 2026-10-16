<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Drush\Commands;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_bundles\Migration\PbMigrationPaths;
use Drupal\paragraphs_bundles\Migration\PbThemeClassMigrator;
use Drupal\paragraphs_bundles\Service\Bundle\BundleSluggerInterface;
use Drupal\paragraphs_bundles\Service\Css\PbCssFileManagerInterface;
use Drupal\paragraphs_bundles\Service\Css\PbDynamicCssBuilder;
use Drupal\paragraphs_bundles\Service\Css\PbDynamicCssBuilderInterface;
use Drupal\paragraphs_bundles\Service\Form\PbFormDisplayVerifierInterface;
use Drupal\paragraphs_bundles\Service\Form\PbViewDisplayVerifierInterface;
use Drush\Attributes\Argument;
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
    private readonly PbThemeClassMigrator $themeClassMigrator,
    private readonly ThemeExtensionList $themeList,
    private readonly ModuleExtensionList $moduleList,
    private readonly FileSystemInterface $fileSystem,
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

  /**
   * Rewrite v1 PB class names and ids in a theme to the v2 BEM names.
   *
   * Dry run by default. Reads only .css .scss .sass .less .twig .js files
   * (never .yml, never *.min.*, never a symbolic link or a hard-linked file)
   * and writes only inside the resolved target directory, which must be
   * inside the Drupal root. Files are replaced atomically. The --report file
   * is written even in a dry run, and never inside the Drupal root. There is
   * deliberately no web UI for this.
   *
   * @param string $target
   *   A theme machine name, or a directory path inside the Drupal root.
   * @param array<string, mixed> $options
   *   Drush options.
   */
  #[Command(name: 'pb:migrate-theme', aliases: ['pb-migrate-theme'])]
  #[Argument(name: 'target', description: 'Theme machine name, or a directory inside the Drupal root. A relative path is read from the current folder first, then from the Drupal root.')]
  #[Option(name: 'apply', description: 'Write the changes. Without it nothing in the theme is written.')]
  #[Option(name: 'report', description: 'Write a Markdown report of every change and every item to check by hand to this new file (mode 0600). Written even in a dry run. Must be outside the Drupal root; from a Composer project folder, --report=pb-report.md.')]
  #[Option(name: 'ext', description: 'Comma-separated extensions to process, narrowing the default set. Allowed: css, scss, sass, less, twig, js.')]
  #[Usage(name: 'drush pb:migrate-theme mytheme', description: 'Dry run: count what would change in theme "mytheme".')]
  #[Usage(name: 'drush pb:migrate-theme mytheme --report=pb-report.md', description: 'Dry run, and list every change and review item in a report (run from the project folder, outside the web root).')]
  #[Usage(name: 'drush pb:migrate-theme mytheme --apply', description: 'Write the changes.')]
  #[Usage(name: 'drush pb:migrate-theme modules/custom/my_module --ext=css,scss', description: 'Only stylesheets, in a custom module directory.')]
  public function migrateTheme(string $target, array $options = ['apply' => FALSE, 'report' => NULL, 'ext' => NULL]): int {
    $ext = $options['ext'] ?? NULL;
    $argv = $_SERVER['argv'] ?? [];
    $extCount = count(array_filter(is_array($argv) ? $argv : [], static fn ($arg): bool => is_string($arg) && ($arg === '--ext' || str_starts_with($arg, '--ext='))));
    if ($extCount > 1) {
      $this->logger()->error(dt('Give --ext once, with a comma-separated list, for example --ext=css,js.'));
      return 1;
    }
    $reportOption = $options['report'] ?? NULL;
    if ($this->input()->hasParameterOption('--report') && (!is_string($reportOption) || trim($reportOption) === '')) {
      $this->logger()->error(dt('--report needs a file name, for example --report=pb-report.md.'));
      return 1;
    }
    if ($this->input()->hasParameterOption('--ext') && (!is_string($ext) || trim($ext) === '')) {
      $this->logger()->error(dt('--ext needs a value, for example --ext=css,scss. Leave it out to process all of: @list.', [
        '@list' => implode(', ', PbThemeClassMigrator::EXTENSIONS),
      ]));
      return 1;
    }
    try {
      $extensions = $this->themeClassMigrator->normalizeExtensions(is_string($ext) ? $ext : NULL);
      $paths = $this->migrationPaths();
      $cwd = $this->getConfig()->cwd();
      $root = $paths->resolveTarget($target, $cwd);
      $reportFile = NULL;
      if (is_string($reportOption) && $reportOption !== '') {
        $reportFile = $paths->resolveReport($reportOption, $cwd);
      }
    }
    catch (\InvalidArgumentException $e) {
      $this->logger()->error($e->getMessage());
      return 1;
    }

    // Create the report before touching the theme, so a bad report path
    // stops the run before any theme file is written. 'x' never overwrites,
    // and 0600 keeps theme source lines private on a shared host.
    $reportHandle = NULL;
    if ($reportFile !== NULL) {
      $reportHandle = @fopen($reportFile, 'x');
      if ($reportHandle === FALSE) {
        $error = error_get_last();
        $this->logger()->error(dt('Could not create the report @file: @reason. Nothing was changed.', [
          '@file' => $reportFile,
          '@reason' => $error['message'] ?? 'unknown error',
        ]));
        return 1;
      }
      @chmod($reportFile, 0600);
    }

    $apply = !empty($options['apply']);
    $relativeRoot = $paths->relative($root);
    $report = [
      '# PB v2 class migration report',
      '',
      'Target: ' . $relativeRoot . ' (relative to the Drupal root)',
      'Mode: ' . ($apply ? 'APPLIED' : 'dry run'),
      'Extensions: ' . implode(', ', $extensions),
      '',
    ];
    $review = [];
    $changedLines = 0;
    $changedFiles = 0;
    $failed = 0;

    $scan = $this->themeClassMigrator->scan($root, $extensions);
    $scan['readonly'] = [];
    $scan['owner'] = [];
    $euid = function_exists('posix_geteuid') ? posix_geteuid() : NULL;
    foreach ($scan['files'] as $path) {
      $real = realpath($path);
      if ($real === FALSE || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
        continue;
      }
      $relative = substr($real, strlen($root) + 1);
      // Never replace a read-only file, or one owned by another user (the
      // replacement would belong to whoever runs Drush, e.g. root via sudo).
      if (!is_writable($real)) {
        $scan['readonly'][] = $real;
        continue;
      }
      if ($euid !== NULL && fileowner($real) !== $euid) {
        $scan['owner'][] = $real;
        continue;
      }
      $contents = file_get_contents($real);
      if ($contents === FALSE) {
        $this->logger()->warning(dt('Could not read @file.', ['@file' => $relative]));
        $failed++;
        continue;
      }
      $result = $this->themeClassMigrator->migrate($contents, $real);
      foreach ($result['notes'] as $note) {
        $review[$note['kind']][] = [$relative] + $note;
      }
      if ($result['changes'] === []) {
        continue;
      }
      if ($apply && !PbMigrationPaths::writeAtomic($real, $result['content'])) {
        $this->logger()->error(dt('Could not write @file; it was left unchanged.', ['@file' => $relative]));
        $failed++;
        continue;
      }
      $changedFiles++;
      $changedLines += count($result['changes']);
      $report[] = '## ' . $relative;
      $report[] = '';
      foreach ($result['changes'] as $change) {
        $report[] = '- L' . $change['line'];
        $report[] = '  - ' . self::markdownCode($change['before']);
        $report[] = '  + ' . self::markdownCode($change['after']);
      }
      $report[] = '';
      $this->output()->writeln(dt('@file: @n line(s)', ['@file' => $relative, '@n' => count($result['changes'])]));
    }

    $skipped = [
      'symlinks' => dt('Symbolic links skipped (not followed; migrate their targets separately)'),
      'hardlinks' => dt('Hard-linked files skipped (the same file has another name elsewhere)'),
      'readonly' => dt('Read-only files skipped (make them writable to migrate them)'),
      'owner' => dt('Files owned by another user skipped (run Drush as the user who owns the theme files)'),
    ];
    foreach ($skipped as $key => $label) {
      if ($scan[$key] === []) {
        continue;
      }
      $report[] = sprintf('## %s (%d)', $label, count($scan[$key]));
      $report[] = '';
      foreach ($scan[$key] as $path) {
        $report[] = '- ' . self::markdownCode(substr($path, strlen($root) + 1));
      }
      $report[] = '';
      $this->logger()->warning(dt('@label: @n', ['@label' => $label, '@n' => count($scan[$key])]));
    }

    $reviewCount = 0;
    if ($review !== []) {
      $report[] = '# Needs manual review';
      $report[] = '';
    }
    foreach (PbThemeClassMigrator::NOTE_LABELS as $kind => $label) {
      if (empty($review[$kind])) {
        continue;
      }
      $reviewCount += count($review[$kind]);
      $report[] = sprintf('## %s (%d)', $label, count($review[$kind]));
      $report[] = '';
      $report[] = '| File | Line | Token | v2 | Source |';
      $report[] = '|---|---|---|---|---|';
      foreach ($review[$kind] as $note) {
        $source = mb_substr(str_replace('|', '\\|', $note['source']), 0, 120);
        $report[] = sprintf('| %s | %d | %s | %s | %s |', $note[0], $note['line'], self::markdownCode($note['token']), $note['v2'], self::markdownCode($source));
      }
      $report[] = '';
      $this->output()->writeln(sprintf('  - %-18s %4d  %s', $kind, count($review[$kind]), $label));
    }

    $this->logger()->success(dt('@mode @lines line(s) in @files file(s) @verb; @review item(s) need manual review; @skipped file(s) skipped.', [
      '@mode' => $apply ? 'Applied:' : 'Dry run:',
      '@lines' => $changedLines,
      '@files' => $changedFiles,
      '@verb' => $apply ? 'changed' : 'would change',
      '@review' => $reviewCount,
      '@skipped' => count($scan['symlinks']) + count($scan['hardlinks']) + count($scan['readonly']) + count($scan['owner']),
    ]));

    if ($reportHandle !== NULL && $reportFile !== NULL) {
      $body = implode("\n", $report) . "\n";
      $written = fwrite($reportHandle, $body);
      fclose($reportHandle);
      if ($written !== strlen($body)) {
        $this->logger()->error(dt('Could not write the whole report to @file.', ['@file' => $reportFile]));
        return 1;
      }
      $this->logger()->success(dt('Report written to @file', ['@file' => $reportFile]));
    }
    elseif (!$apply) {
      $this->logger()->success(dt('Nothing was written. Add --report=pb-report.md to list every change, then --apply to write them.'));
    }

    return $failed > 0 ? 1 : 0;
  }

  /**
   * Builds the path rules for pb:migrate-theme.
   *
   * @throws \InvalidArgumentException
   *   When the Drupal root does not resolve.
   */
  private function migrationPaths(): PbMigrationPaths {
    $public = $this->fileSystem->realpath('public://');
    $forbidden = ['core', $this->moduleList->getPath('paragraphs_bundles')];
    if (is_string($public) && $public !== '') {
      $forbidden[] = $public;
    }
    return new PbMigrationPaths(
      DRUPAL_ROOT,
      fn (string $name): ?string => $this->themeList->exists($name) ? $this->themeList->getPath($name) : NULL,
      $forbidden,
      is_string($public) && $public !== '' ? [$public] : [],
    );
  }

  /**
   * Wraps text in a Markdown code span that survives backticks inside it.
   */
  private static function markdownCode(string $text): string {
    $fence = '`';
    while (str_contains($text, $fence)) {
      $fence .= '`';
    }
    $pad = (str_starts_with($text, '`') || str_ends_with($text, '`')) ? ' ' : '';
    return $fence . $pad . $text . $pad . $fence;
  }

}
