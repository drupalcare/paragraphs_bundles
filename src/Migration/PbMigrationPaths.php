<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Migration;

/**
 * Path rules for `drush pb:migrate-theme`: target, report file and writes.
 *
 * Kept apart from the Drush command so the boundary checks are unit-tested.
 * Every check works on realpath() results, so `..` segments and symbolic
 * links are resolved before a path is compared with a boundary, and every
 * prefix comparison appends a directory separator, so `/x/web2` never counts
 * as inside `/x/web`.
 */
final class PbMigrationPaths {

  /**
   * The real Drupal root, without a trailing separator.
   */
  private string $root;

  /**
   * Real directories that may never be a target, nor contain one.
   *
   * @var list<string>
   */
  private array $forbidden = [];

  /**
   * Real directories a report may not be written into.
   *
   * @var list<string>
   */
  private array $reportForbidden = [];

  /**
   * Returns a theme's path relative to the Drupal root, or NULL.
   *
   * @var \Closure(string): ?string
   */
  private \Closure $themePath;

  /**
   * Constructs the path rules.
   *
   * @param string $drupalRoot
   *   The Drupal root.
   * @param callable(string): ?string $themePath
   *   Returns a theme's path relative to the Drupal root, or NULL when no
   *   theme has that machine name.
   * @param list<string> $forbiddenTargets
   *   Directories (absolute, or relative to the Drupal root) that may not be
   *   migrated: core, this module, the public files directory.
   * @param list<string> $forbiddenReportDirs
   *   Extra directories a report may not be written into, besides the Drupal
   *   root itself (for example a public files directory outside it).
   *
   * @throws \InvalidArgumentException
   *   When the Drupal root does not resolve.
   */
  public function __construct(string $drupalRoot, callable $themePath, array $forbiddenTargets = [], array $forbiddenReportDirs = []) {
    $root = realpath($drupalRoot);
    if ($root === FALSE || !is_dir($root)) {
      throw new \InvalidArgumentException(sprintf('Cannot resolve the Drupal root (%s).', $drupalRoot));
    }
    $this->root = rtrim($root, DIRECTORY_SEPARATOR);
    $this->themePath = \Closure::fromCallable($themePath);
    foreach ($forbiddenTargets as $dir) {
      $real = realpath(self::isAbsolute($dir) ? $dir : $this->root . DIRECTORY_SEPARATOR . $dir);
      if ($real !== FALSE) {
        $this->forbidden[] = $real;
      }
    }
    $this->reportForbidden = [$this->root];
    foreach ($forbiddenReportDirs as $dir) {
      $real = realpath($dir);
      if ($real !== FALSE) {
        $this->reportForbidden[] = $real;
      }
    }
  }

  /**
   * Returns the real Drupal root.
   */
  public function root(): string {
    return $this->root;
  }

  /**
   * Resolves a theme name or directory to a real directory inside the root.
   *
   * @param string $target
   *   A theme machine name, or a directory path: absolute, relative to $cwd
   *   when it exists there, otherwise relative to the Drupal root.
   * @param string|null $cwd
   *   The folder Drush was run in, or NULL.
   *
   * @return string
   *   The real directory.
   *
   * @throws \InvalidArgumentException
   *   With a message for the user when the target is refused.
   */
  public function resolveTarget(string $target, ?string $cwd = NULL): string {
    $candidate = NULL;
    if (preg_match('/^[a-z][a-z0-9_]*$/', $target) === 1) {
      $relative = ($this->themePath)($target);
      if (is_string($relative) && $relative !== '') {
        $candidate = $this->root . DIRECTORY_SEPARATOR . $relative;
      }
    }
    if ($candidate === NULL && self::isAbsolute($target)) {
      $candidate = $target;
    }
    if ($candidate === NULL && $cwd !== NULL && realpath(rtrim($cwd, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $target) !== FALSE) {
      // A relative path that exists from the folder Drush was run in, such
      // as web/themes/custom/mytheme from a Composer project folder.
      $candidate = rtrim($cwd, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $target;
    }
    if ($candidate === NULL) {
      $candidate = $this->root . DIRECTORY_SEPARATOR . $target;
    }

    $real = realpath($candidate);
    if ($real === FALSE || !is_dir($real)) {
      throw new \InvalidArgumentException(sprintf('%s is not a theme or an existing directory.', $target));
    }
    if ($real === $this->root) {
      throw new \InvalidArgumentException('Refused: the whole Drupal root is not a valid target. Name one theme or module folder.');
    }
    if (!$this->isInside($real, $this->root)) {
      if (is_link(rtrim($candidate, DIRECTORY_SEPARATOR))) {
        throw new \InvalidArgumentException(sprintf(
          'Refused: %s is a symbolic link to %s, which is outside the Drupal root (%s). Run the command on a copy of the theme inside the Drupal root.',
          $target,
          $real,
          $this->root,
        ));
      }
      throw new \InvalidArgumentException(sprintf('Refused: %s is outside the Drupal root (%s).', $real, $this->root));
    }
    foreach ($this->forbidden as $dir) {
      if ($real === $dir || $this->isInside($real, $dir) || $this->isInside($dir, $real)) {
        throw new \InvalidArgumentException(sprintf(
          'Refused: %s is or contains %s (Drupal core, Paragraphs Bundles itself or the public files directory). Point the command at your own theme or module.',
          $this->relative($real),
          $this->relative($dir),
        ));
      }
    }
    return $real;
  }

  /**
   * Resolves the --report path and checks it is safe to create.
   *
   * @param string $report
   *   The --report value.
   * @param string $cwd
   *   The directory the user ran Drush from; a relative path resolves here.
   *
   * @return string
   *   The absolute path of the file to create.
   *
   * @throws \InvalidArgumentException
   *   With a message for the user when the path is refused.
   */
  public function resolveReport(string $report, string $cwd): string {
    $path = self::isAbsolute($report) ? $report : rtrim($cwd, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $report;
    $name = basename($path);
    if ($name === '' || $name === '.' || $name === '..' || str_ends_with($report, DIRECTORY_SEPARATOR)) {
      throw new \InvalidArgumentException(sprintf('--report needs a file name, not a directory: %s', $report));
    }
    $parent = realpath(dirname($path));
    if ($parent === FALSE || !is_dir($parent)) {
      throw new \InvalidArgumentException(sprintf('The folder for the report does not exist: %s', dirname($path)));
    }
    foreach ($this->reportForbidden as $dir) {
      if ($parent === $dir || $this->isInside($parent, $dir)) {
        throw new \InvalidArgumentException(sprintf(
          'Refused: %s is inside %s, where a web server may serve it. Write the report outside the web root, for example --report=%s',
          $parent . DIRECTORY_SEPARATOR . $name,
          $dir,
          dirname($this->root) . DIRECTORY_SEPARATOR . 'pb-report.md',
        ));
      }
    }
    if (!is_writable($parent)) {
      throw new \InvalidArgumentException(sprintf('Refused: the folder %s is not writable. Choose a folder you can write to.', $parent));
    }
    $final = $parent . DIRECTORY_SEPARATOR . $name;
    if (is_link($final) || file_exists($final)) {
      throw new \InvalidArgumentException(sprintf('Refused: %s already exists. Choose a new file name, or delete the old report first.', $final));
    }
    return $final;
  }

  /**
   * Returns a path relative to the Drupal root, or the path unchanged.
   */
  public function relative(string $path): string {
    if ($path === $this->root) {
      return '.';
    }
    return $this->isInside($path, $this->root) ? substr($path, strlen($this->root) + 1) : $path;
  }

  /**
   * Replaces a file's contents atomically.
   *
   * Writes a temporary file in the same directory, checks every byte was
   * written, copies the permissions and renames it over the original. A
   * failure leaves the original untouched and removes the temporary file.
   * rename() replaces a symbolic link rather than following it.
   *
   * @return bool
   *   TRUE when the file now holds $contents.
   */
  public static function writeAtomic(string $path, string $contents): bool {
    $dir = dirname($path);
    $tmp = tempnam($dir, '.pbmig');
    if ($tmp === FALSE) {
      return FALSE;
    }
    if (dirname($tmp) !== $dir) {
      // tempnam() fell back to the system temp directory: not atomic.
      @unlink($tmp);
      return FALSE;
    }
    $written = file_put_contents($tmp, $contents);
    $perms = fileperms($path);
    if ($written !== strlen($contents) || $perms === FALSE || !chmod($tmp, $perms & 0777) || !rename($tmp, $path)) {
      @unlink($tmp);
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Whether a path is strictly inside a directory.
   */
  private function isInside(string $path, string $dir): bool {
    return str_starts_with($path, rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
  }

  /**
   * Whether a path is absolute.
   */
  private static function isAbsolute(string $path): bool {
    return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
  }

}
