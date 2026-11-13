<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_patterns;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleExtensionList;

/**
 * Reads the Patterns in `patterns/<paragraph type>/<name>/pattern.yml`.
 *
 * A definition holds:
 * - label: the name shown in the list.
 * - description: one sentence shown on the picker's card.
 * - needs: what the editor is told after inserting it.
 * - settings: field values written to the section when it is inserted.
 * - pictures: per picture slot (`<paragraph type>.<media field>`), the image
 *   style, whether it is responsive, and the media view mode that draws it.
 *
 * The sample words and pictures are in data/samples.json, keyed bundle.name.
 *
 * @phpstan-type PatternDefinition array{id: string, bundle: string, label: string, description: string, needs: list<string>, settings: array<string, mixed>, pictures: array<string, array{style: string, responsive: bool, view_mode: string}>, sample: array<string, mixed>, library: string, class: string, weight: int, css: list<string>, js: list<string>}
 */
final class PatternRepository {

  private const CID = 'paragraphs_bundles_patterns:definitions';

  /**
   * Definitions by paragraph type and Pattern name, memoized for the request.
   *
   * @var array<string, array<string, PatternDefinition>>|null
   */
  private ?array $definitions = NULL;

  public function __construct(
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly CacheBackendInterface $cache,
  ) {}

  /**
   * Returns every Pattern.
   *
   * @return array<string, array<string, PatternDefinition>>
   *   Definitions keyed by paragraph type, then Pattern name.
   */
  public function all(): array {
    if ($this->definitions !== NULL) {
      return $this->definitions;
    }
    $cached = $this->cache->get(self::CID);
    if ($cached !== FALSE && is_array($cached->data)) {
      return $this->definitions = $cached->data;
    }
    $this->definitions = $this->discover();
    $this->cache->set(self::CID, $this->definitions);
    return $this->definitions;
  }

  /**
   * Returns the Patterns of one paragraph type.
   *
   * @param string $bundle
   *   The paragraph type.
   *
   * @return array<string, PatternDefinition>
   *   Definitions keyed by Pattern name.
   */
  public function forBundle(string $bundle): array {
    return $this->all()[$bundle] ?? [];
  }

  /**
   * Returns one Pattern, or NULL when it no longer exists.
   *
   * @param string $bundle
   *   The paragraph type.
   * @param string $name
   *   The Pattern name.
   *
   * @return PatternDefinition|null
   *   The definition.
   */
  public function get(string $bundle, string $name): ?array {
    return $this->all()[$bundle][$name] ?? NULL;
  }

  /**
   * Returns the Pattern a class names on a paragraph type.
   *
   * @param string $bundle
   *   The paragraph type.
   * @param string $class
   *   A CSS class.
   *
   * @return PatternDefinition|null
   *   The definition, or NULL when the class names none.
   */
  public function byClass(string $bundle, string $class): ?array {
    foreach ($this->forBundle($bundle) as $pattern) {
      if ($pattern['class'] === $class) {
        return $pattern;
      }
    }
    return NULL;
  }

  /**
   * Returns the names of the paragraph types the samples use.
   *
   * @return array<string, array{label: string, module: string, module_label: string}>
   *   Names keyed by paragraph type. Read from a file, because a type's module
   *   may not be installed.
   */
  public function bundleInfo(): array {
    $root = $this->moduleExtensionList->getPath('paragraphs_bundles_patterns');
    /** @var array<string, array{label: string, module: string, module_label: string}> $info */
    $info = $this->readJson($root . '/data/bundles.json');
    return $info;
  }

  /**
   * Reads a JSON file of this module.
   *
   * @param string $path
   *   The path.
   *
   * @return array<string, mixed>
   *   The decoded data; empty when the file cannot be read.
   */
  private function readJson(string $path): array {
    $raw = file_get_contents($path);
    $data = $raw === FALSE ? NULL : json_decode($raw, TRUE);
    return is_array($data) ? $data : [];
  }

  /**
   * Finds the Pattern definitions on disk.
   *
   * @return array<string, array<string, PatternDefinition>>
   *   Definitions keyed by paragraph type, then Pattern name.
   */
  private function discover(): array {
    $root = $this->moduleExtensionList->getPath('paragraphs_bundles_patterns');
    $samples = $this->readJson($root . '/data/samples.json');
    $found = [];
    foreach (glob($root . '/patterns/*/*/pattern.yml') ?: [] as $file) {
      $directory = dirname($file);
      $name = basename($directory);
      $bundle = basename(dirname($directory));
      $raw = file_get_contents($file);
      $data = $raw === FALSE ? NULL : Yaml::decode($raw);
      if (!is_array($data) || !is_string($data['label'] ?? NULL)) {
        continue;
      }
      $relative = 'patterns/' . $bundle . '/' . $name . '/';
      $found[$bundle][$name] = [
        'id' => $name,
        'bundle' => $bundle,
        'label' => $data['label'],
        'description' => is_string($data['description'] ?? NULL) ? $data['description'] : '',
        'needs' => array_values(array_filter((array) ($data['needs'] ?? []), 'is_string')),
        'settings' => is_array($data['settings'] ?? NULL) ? $data['settings'] : [],
        'pictures' => $this->pictures($data['pictures'] ?? NULL),
        'sample' => is_array($samples[$bundle . '.' . $name] ?? NULL) ? $samples[$bundle . '.' . $name] : [],
        'library' => 'paragraphs_bundles_patterns/pattern.' . $bundle . '.' . $name,
        'class' => 'pb-pattern--' . str_replace('_', '-', preg_replace('/_bundle$/', '', $bundle) . '-' . $name),
        'weight' => is_int($data['weight'] ?? NULL) ? $data['weight'] : 0,
        'css' => $this->assets($directory, $relative, 'css'),
        'js' => $this->assets($directory, $relative, 'js'),
      ];
    }
    foreach ($found as &$patterns) {
      uasort($patterns, static function (array $a, array $b): int {
        return [$a['weight'], $a['label']] <=> [$b['weight'], $b['label']];
      });
    }
    return $found;
  }

  /**
   * Reads a Pattern's `pictures` map, keeping only complete slots.
   *
   * @param mixed $raw
   *   The `pictures` value of the pattern file.
   *
   * @return array<string, array{style: string, responsive: bool, view_mode: string}>
   *   Slots keyed `<paragraph type>.<picture field>`.
   */
  private function pictures(mixed $raw): array {
    $pictures = [];
    foreach (is_array($raw) ? $raw : [] as $slot => $picture) {
      if (!is_string($slot) || !preg_match('/^[a-z0-9_]+\.[a-z0-9_]+$/', $slot) || !is_array($picture)) {
        continue;
      }
      if (!is_string($picture['style'] ?? NULL) || !is_bool($picture['responsive'] ?? NULL)) {
        continue;
      }
      // An `image` field no formatter draws names only its style; its
      // view_mode is ''. The check script requires view_mode on media slots.
      $viewMode = $picture['view_mode'] ?? '';
      if (!is_string($viewMode)) {
        continue;
      }
      $pictures[$slot] = [
        'style' => $picture['style'],
        'responsive' => $picture['responsive'],
        'view_mode' => $viewMode,
      ];
    }
    return $pictures;
  }

  /**
   * Lists a Pattern's asset files of one type.
   *
   * @param string $directory
   *   The Pattern's directory.
   * @param string $relative
   *   The directory relative to the module.
   * @param string $extension
   *   The file extension.
   *
   * @return list<string>
   *   Paths relative to the module.
   */
  private function assets(string $directory, string $relative, string $extension): array {
    $files = glob($directory . '/*.' . $extension) ?: [];
    return array_map(static fn (string $path): string => $relative . basename($path), $files);
  }

}
