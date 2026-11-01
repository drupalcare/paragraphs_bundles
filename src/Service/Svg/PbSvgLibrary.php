<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Svg;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Render\Markup;
use Symfony\Component\Finder\Finder;

/**
 * SVG artwork from the front-end theme's `images/pb-svg-icons` folder.
 *
 * A developer drops SVG files in `<theme>/images/pb-svg-icons/` (folders inside
 * it are optional and may nest) in the default front-end theme or any of its
 * base themes. Editors pick one in a field; nothing else can be printed:
 * a value names a theme in that chain and a file inside its pb-svg-icons
 * folder, checked again by real path every time it is used.
 *
 * Values look like `solo_sub:cars/sedan` (theme, path without `.svg`).
 */
final class PbSvgLibrary {

  /**
   * The folder, relative to a theme's root.
   */
  public const string FOLDER = 'images/pb-svg-icons';

  /**
   * The allowed_values_function every pb_*_svg_* field storage names.
   */
  public const string ALLOWED_VALUES_FUNCTION = self::class . '::allowedValues';

  /**
   * Folder levels scanned below pb-svg-icons.
   */
  private const int MAX_DEPTH = 5;

  /**
   * Cache id of the scanned list.
   */
  private const string LIST_CID = 'paragraphs_bundles:svg_library:list';

  /**
   * Characters allowed in a path inside pb-svg-icons (no "..", see resolve()).
   */
  private const string PATH_PATTERN = '#^[A-Za-z0-9 _\-]+(/[A-Za-z0-9 _\-]+)*$#';

  /**
   * The theme chain for this request.
   *
   * @var array<string, string>|null
   */
  private ?array $chain = NULL;

  public function __construct(
    private readonly ThemeHandlerInterface $themeHandler,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly CacheBackendInterface $cache,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    private readonly PbSvgCleaner $cleaner,
    private readonly string $appRoot,
  ) {}

  /**
   * Allowed values callback for the pb_*_svg_* list fields.
   *
   * Core calls this by name (field storage setting allowed_values_function),
   * so it cannot be injected; it hands over to the service at once.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionInterface $definition
   *   The field storage definition.
   * @param \Drupal\Core\Entity\FieldableEntityInterface|null $entity
   *   The entity, when there is one.
   * @param bool $cacheable
   *   Set to FALSE: the list follows the files on disk; the service caches it.
   *
   * @return array<string, string>
   *   Value => label.
   */
  public static function allowedValues(FieldStorageDefinitionInterface $definition, ?FieldableEntityInterface $entity = NULL, bool &$cacheable = TRUE): array {
    $cacheable = FALSE;
    $library = \Drupal::service('paragraphs_bundles.svg_library');
    assert($library instanceof self);
    return $library->options();
  }

  /**
   * Value => label ("Solo Sub / cars / sedan"), in picker order.
   *
   * @return array<string, string>
   *   The options.
   */
  public function options(): array {
    $options = [];
    foreach ($this->items() as $key => $item) {
      $options[$key] = implode(' / ', array_filter([$item['theme_label'], $item['group'], $item['name']]));
    }
    return $options;
  }

  /**
   * Every SVG found, keyed by value.
   *
   * @return array<string, array{theme: string, theme_label: string, group: string, name: string, url: string}>
   *   Items in picker order: default theme first, then its base themes.
   */
  public function items(): array {
    $cached = $this->cache->get(self::LIST_CID);
    $data = $cached ? $cached->data : NULL;
    if (is_array($data) && is_array($data['items'] ?? NULL) && is_array($data['stamps'] ?? NULL) && ($data['chain'] ?? NULL) === array_keys($this->themeChain()) && $this->stampsMatch($data['stamps'])) {
      return $this->validItems($data['items']);
    }
    [$items, $stamps] = $this->scan();
    $this->cache->set(self::LIST_CID, [
      'items' => $items,
      'stamps' => $stamps,
      'chain' => array_keys($this->themeChain()),
    ], CacheBackendInterface::CACHE_PERMANENT, ['config:system.theme']);
    return $items;
  }

  /**
   * The default front-end theme and its base themes, nearest first.
   *
   * @return array<string, string>
   *   Theme machine name => label.
   */
  public function themeChain(): array {
    if ($this->chain !== NULL) {
      return $this->chain;
    }
    $default_setting = $this->configFactory->get('system.theme')->get('default');
    $default = is_string($default_setting) ? $default_setting : '';
    $themes = $this->themeHandler->listInfo();
    if ($default === '' || !isset($themes[$default])) {
      return $this->chain = [];
    }
    $info = $themes[$default]->info;
    $chain = [$default => is_array($info) && is_string($info['name'] ?? NULL) ? $info['name'] : $default];
    // base_themes is root first; walk it backwards so the nearest parent comes
    // right after the default theme.
    $bases = $themes[$default]->base_themes ?? [];
    foreach (array_reverse(is_array($bases) ? $bases : [], TRUE) as $name => $label) {
      if (isset($themes[$name])) {
        $chain[(string) $name] = is_string($label) && $label !== '' ? $label : (string) $name;
      }
    }
    return $this->chain = $chain;
  }

  /**
   * Real path of the file a value names, or NULL when it is not allowed.
   *
   * Independent of the cached list, so a stale list can never widen what is
   * printed: the theme must be in today's chain and the real path must stay
   * inside that theme's pb-svg-icons folder (no "..", no symlink out of it).
   */
  public function resolve(string $value): ?string {
    $parts = explode(':', $value, 2);
    if (count($parts) !== 2) {
      return NULL;
    }
    [$theme, $path] = $parts;
    if (!isset($this->themeChain()[$theme]) || !preg_match(self::PATH_PATTERN, $path)) {
      return NULL;
    }
    $root = $this->folderRealPath($theme);
    if ($root === NULL) {
      return NULL;
    }
    $file = realpath($root . '/' . $path . '.svg');
    if ($file === FALSE || !is_file($file) || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
      return NULL;
    }
    return $file;
  }

  /**
   * Render array for a value, or an empty array when it cannot be shown.
   *
   * @param string $value
   *   The stored field value.
   * @param string $variant
   *   One of: 'icon' (1em, text color), 'art' (fills its box), 'background'.
   *
   * @return array<string, mixed>
   *   A render array.
   */
  public function build(string $value, string $variant): array {
    $file = $this->resolve($value);
    $cache = ['#cache' => ['tags' => ['config:system.theme']]];
    if ($file === NULL) {
      return $cache;
    }
    $svg = $this->cleaned($file);
    if ($svg === NULL) {
      return $cache;
    }
    $attributes = ['class' => ['pb-svg', 'pb-svg--' . $variant]];
    $title = $variant === 'art' ? $this->title($svg) : '';
    if ($title !== '') {
      // An illustration that names itself in <title> is announced by it.
      $attributes['role'] = 'img';
      $attributes['aria-label'] = $title;
    }
    else {
      $attributes['aria-hidden'] = 'true';
    }
    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#attributes' => $attributes,
      // Cleaned by PbSvgCleaner from a developer file; Xss::filter would
      // lower-case viewBox and drop the drawing elements illustrations need.
      '#value' => Markup::create($svg),
      '#attached' => ['library' => ['paragraphs_bundles/svg']],
    ] + $cache;
  }

  /**
   * Cleaned markup of a file, cached per path and modification time.
   */
  private function cleaned(string $file): ?string {
    $cid = 'paragraphs_bundles:svg_library:file:' . hash('sha256', PbSvgCleaner::VERSION . ':' . $file . ':' . (string) filemtime($file));
    $cached = $this->cache->get($cid);
    if ($cached && is_string($cached->data)) {
      return $cached->data === '' ? NULL : $cached->data;
    }
    $raw = file_get_contents($file);
    $svg = $raw === FALSE ? NULL : $this->cleaner->clean($raw);
    $this->cache->set($cid, $svg ?? '', CacheBackendInterface::CACHE_PERMANENT, ['config:system.theme']);
    return $svg;
  }

  /**
   * Text of the svg's own <title>, if it has one.
   */
  private function title(string $svg): string {
    $dom = Html::load($svg);
    $title = $dom->getElementsByTagName('title')->item(0);
    return $title ? trim($title->textContent) : '';
  }

  /**
   * Scans every theme in the chain.
   *
   * @return array{0: array<string, array{theme: string, theme_label: string, group: string, name: string, url: string}>, 1: array<string, int>}
   *   The items, and folder path => modification time for change detection.
   */
  private function scan(): array {
    $items = [];
    $stamps = [];
    foreach ($this->themeChain() as $theme => $label) {
      $themePath = $this->themeHandler->getTheme($theme)->getPath();
      // Stamp the images folder too, so creating pb-svg-icons is noticed.
      $images = $this->appRoot . '/' . $themePath . '/images';
      $stamps[$images] = is_dir($images) ? (int) filemtime($images) : 0;
      $root = $this->folderRealPath($theme);
      if ($root === NULL) {
        continue;
      }
      $stamps[$root] = (int) filemtime($root);
      $finder = Finder::create()->in($root)->depth('< ' . self::MAX_DEPTH)->ignoreDotFiles(TRUE)->ignoreVCS(TRUE)->sortByName(TRUE);
      foreach ((clone $finder)->directories() as $dir) {
        $real = realpath($dir->getPathname());
        if ($real !== FALSE && str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
          $stamps[$real] = (int) $dir->getMTime();
        }
      }
      foreach ((clone $finder)->files()->name('/\.svg$/i') as $file) {
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());
        $path = (string) preg_replace('/\.svg$/i', '', $relative);
        $key = $theme . ':' . $path;
        // Same rules as rendering: a file the resolver would refuse is not
        // offered.
        if ($this->resolve($key) === NULL) {
          continue;
        }
        $group = str_replace('/', ' / ', $file->getRelativePath());
        $items[$key] = [
          'theme' => $theme,
          'theme_label' => $label,
          'group' => $group,
          'name' => basename($path),
          'url' => $this->fileUrlGenerator->generateString($themePath . '/' . self::FOLDER . '/' . $relative),
        ];
      }
    }
    return [$items, $stamps];
  }

  /**
   * TRUE when no stamped folder changed since the list was built.
   *
   * @param array<mixed> $stamps
   *   Folder path => modification time.
   */
  private function stampsMatch(array $stamps): bool {
    clearstatcache();
    foreach ($stamps as $dir => $time) {
      $dir = (string) $dir;
      $now = is_dir($dir) ? (int) filemtime($dir) : 0;
      if ($now !== $time) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * The well-formed items of a cached list.
   *
   * @param array<mixed> $cached
   *   The items as read from the cache.
   *
   * @return array<string, array{theme: string, theme_label: string, group: string, name: string, url: string}>
   *   The same items, typed.
   */
  private function validItems(array $cached): array {
    $items = [];
    foreach ($cached as $key => $item) {
      if (is_array($item) && is_string($item['theme'] ?? NULL) && is_string($item['theme_label'] ?? NULL) && is_string($item['group'] ?? NULL) && is_string($item['name'] ?? NULL) && is_string($item['url'] ?? NULL)) {
        $items[(string) $key] = [
          'theme' => $item['theme'],
          'theme_label' => $item['theme_label'],
          'group' => $item['group'],
          'name' => $item['name'],
          'url' => $item['url'],
        ];
      }
    }
    return $items;
  }

  /**
   * Real path of a theme's pb-svg-icons folder, or NULL.
   */
  private function folderRealPath(string $theme): ?string {
    if (!$this->themeHandler->themeExists($theme)) {
      return NULL;
    }
    $themeRoot = realpath($this->appRoot . '/' . $this->themeHandler->getTheme($theme)->getPath());
    if ($themeRoot === FALSE) {
      return NULL;
    }
    // The theme may be a symlink (a shared checkout); the folder inside it may
    // not, or it could point at the public files directory.
    $expected = $themeRoot . '/' . self::FOLDER;
    return realpath($expected) === $expected && is_dir($expected) ? $expected : NULL;
  }

}
