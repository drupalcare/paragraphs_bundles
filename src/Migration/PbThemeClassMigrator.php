<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Migration;

/**
 * Rewrites v1 Paragraphs Bundles class names and ids in theme files to v2.
 *
 * Pure logic plus the file walk; no Drush, no database, no web UI. The rename
 * data comes from two files and nothing is hard-coded here:
 * - PbClassRenameMap.php: structural renames, ids, generic and context tokens
 *   (kept in step with docs/CSS-CLASS-MIGRATION.md by a drift test).
 * - ../Twig/PbValueClassMap.php: stored display value -> rendered class. It
 *   is what the `pb_class` filter renders, so it wins over the document.
 *
 * Rules (see docs/CSS-CLASS-MIGRATION.md "Migrate a theme with Drush"):
 * 1. Tokens match on class-name boundaries, longest first, so `pb__card`
 *    never rewrites inside `pb__card-image`.
 * 2. Only stylesheets, Twig and JavaScript are read. Never `.yml`; `*.min.*`,
 *    node_modules, vendor, dist and .git are skipped, and so is every symlink.
 * 3. Display-value classes are rewritten in stylesheets only. In Twig and JS
 *    the same string is usually a stored-value comparison: reported.
 * 4. Per-paragraph ids are rewritten as ids and as classes when followed by
 *    a digit, `{{` or a quote. Ambiguous v1 ids are reported only.
 * 5. Generic tokens (`dot`, `active`, ...) are rewritten only when chained
 *    onto a PB class in a stylesheet; elsewhere they are reported, and only on
 *    lines that mention a PB class.
 * 6. Context-dependent tokens and removed classes are reported only.
 * 7. A second run changes nothing.
 */
final class PbThemeClassMigrator {

  /**
   * Extensions the migrator reads; also the only values `--ext` accepts.
   *
   * @var list<string>
   */
  public const array EXTENSIONS = ['css', 'scss', 'sass', 'less', 'twig', 'js'];

  /**
   * Stylesheet extensions, where display-value classes are rewritten.
   *
   * @var list<string>
   */
  private const array STYLESHEETS = ['css', 'scss', 'sass', 'less'];

  /**
   * Directory names never descended into.
   *
   * @var list<string>
   */
  private const array SKIP_DIRS = ['node_modules', 'vendor', '.git', 'dist'];

  /**
   * Characters a class name or id is made of.
   */
  private const string TOKEN_CHARS = 'A-Za-z0-9_-';

  /**
   * Report labels, keyed by note kind, in report order.
   *
   * @var array<string, string>
   */
  public const array NOTE_LABELS = [
    'pcre-error' => 'Lines the pattern engine could not process (left unchanged; check by hand)',
    'scss-nesting' => 'Nested selectors built from a parent with & or #{} (not rewritten; rename by hand)',
    'file-reference' => 'File names that contain a v1 name (not rewritten; the files on disk keep their names)',
    'unknown-v1-token' => 'v1-style tokens not in any table (may be theme-owned; check by hand)',
    'removed' => 'v1 classes with no v2 element',
    'value-in-template' => 'Display-value classes in Twig/JS (often stored-value comparisons; change only if used as a CSS class)',
    'context-dependent' => 'Context-dependent classes (pick the right variant)',
    'generic-token' => 'Generic state/effect tokens next to PB selectors',
    'ambiguous-id' => 'v1 ids shared by two bundles (pick the right one)',
  ];

  /**
   * Structural renames: v1 class => v2 class.
   *
   * @var array<string, string>
   */
  private array $structural = [];

  /**
   * Display-value classes: v1 value class => rendered v2 class.
   *
   * @var array<string, string>
   */
  private array $values = [];

  /**
   * Id prefixes: v1 prefix => v2 prefix.
   *
   * @var array<string, string>
   */
  private array $ids = [];

  /**
   * Ambiguous id prefixes: v1 prefix => description of the candidates.
   *
   * @var array<string, string>
   */
  private array $ambiguousIds = [];

  /**
   * Generic tokens: bare v1 token => v2 class.
   *
   * @var array<string, string>
   */
  private array $generic = [];

  /**
   * Context-dependent tokens: v1 token => description of the candidates.
   *
   * @var array<string, string>
   */
  private array $context = [];

  /**
   * Removed classes: v1 class => explanation.
   *
   * @var array<string, string>
   */
  private array $removed = [];

  /**
   * Tokens rendered verbatim in v2.
   *
   * @var array<string, true>
   */
  private array $frozen = [];

  /**
   * PCRE error messages raised while processing the current line.
   *
   * @var list<string>
   */
  private array $pcreErrors = [];

  /**
   * Compiled patterns, keyed by purpose; NULL when the table is empty.
   *
   * @var array<string, string|null>
   */
  private array $patterns = [];

  public function __construct() {
    $map = self::loadArray(__DIR__ . '/PbClassRenameMap.php');
    $valueMap = self::stringMap(self::loadArray(__DIR__ . '/../Twig/PbValueClassMap.php'));

    $this->structural = self::stringMap($map['structural'] ?? []);
    $this->ids = self::stringMap($map['ids'] ?? []);
    $this->ambiguousIds = self::stringMap($map['ambiguous_ids'] ?? []);
    $this->generic = self::stringMap($map['generic'] ?? []);
    $this->context = self::stringMap($map['context'] ?? []);
    $this->removed = self::stringMap($map['removed'] ?? []);

    // PbValueClassMap is what pb_class actually renders; it wins.
    $this->values = self::stringMap($map['values'] ?? []);
    foreach ($valueMap as $old => $new) {
      $this->values[$old] = $new;
    }
    foreach (array_keys($this->values) as $old) {
      unset($this->structural[$old]);
    }

    $frozen = $map['frozen'] ?? [];
    if (is_array($frozen)) {
      foreach ($frozen as $token) {
        if (is_string($token) && !isset($this->values[$token])) {
          $this->frozen[$token] = TRUE;
        }
      }
    }

    $lb = '(?<![' . self::TOKEN_CHARS . '])';
    $la = '(?![' . self::TOKEN_CHARS . '])';
    $this->patterns = [
      'struct' => self::compile($this->structural, $lb . '(', ')' . $la),
      'value' => self::compile($this->values, $lb . '(', ')' . $la),
      'id' => self::compile($this->ids, $lb . '(', ')(?=\d|\{\{|\'|")'),
      'ambiguous' => self::compile($this->ambiguousIds, $lb . '(', ')(?=\d|\{\{|\'|")'),
      'generic' => self::compile($this->generic, '(?<=[.\s"\'])(', ')' . $la),
      'context' => self::compile($this->context, $lb . '(', ')' . $la),
      'chained' => self::compile($this->generic, '(\.pb-[' . self::TOKEN_CHARS . ']+)\.(', ')' . $la),
      'removed' => self::compile($this->removed, $lb . '(', ')' . $la),
    ];
  }

  /**
   * Validates and normalizes an `--ext` list.
   *
   * @param string|null $extensions
   *   Comma-separated extensions, case-insensitive, leading dots tolerated.
   *   NULL (option not given) selects the full default set; an empty value
   *   is refused, because the option can only narrow the set.
   *
   * @return list<string>
   *   The selected extensions, lower case, without dots.
   *
   * @throws \InvalidArgumentException
   *   When any value is not one of self::EXTENSIONS.
   */
  public function normalizeExtensions(?string $extensions): array {
    if ($extensions === NULL) {
      return self::EXTENSIONS;
    }
    $selected = [];
    $refused = [];
    foreach (explode(',', $extensions) as $raw) {
      $ext = strtolower(ltrim(trim($raw), '.'));
      if ($ext === '') {
        continue;
      }
      if (!in_array($ext, self::EXTENSIONS, TRUE)) {
        $refused[] = $ext;
        continue;
      }
      $selected[$ext] = $ext;
    }
    if ($refused !== []) {
      throw new \InvalidArgumentException(sprintf(
        'Refused extension(s): %s. Allowed: %s.',
        implode(', ', $refused),
        implode(', ', self::EXTENSIONS),
      ));
    }
    if ($selected === []) {
      throw new \InvalidArgumentException(sprintf(
        'No extension given. Allowed: %s.',
        implode(', ', self::EXTENSIONS),
      ));
    }
    return array_values($selected);
  }

  /**
   * Lists the files under a directory that the migrator would read.
   *
   * @param string $root
   *   An existing directory (already resolved with realpath()).
   * @param list<string> $extensions
   *   Extensions to include, from normalizeExtensions().
   *
   * @return list<string>
   *   Absolute file paths, sorted.
   */
  public function listFiles(string $root, array $extensions): array {
    return $this->scan($root, $extensions)['files'];
  }

  /**
   * Walks a directory: files to migrate, and what was skipped and why.
   *
   * Symbolic links (files and directories) are never followed. Regular files
   * with more than one hard link are skipped too, because rewriting one would
   * change the other names of the same file outside the theme.
   *
   * @param string $root
   *   An existing directory (already resolved with realpath()).
   * @param list<string> $extensions
   *   Extensions to include, from normalizeExtensions().
   *
   * @return array{files: list<string>, symlinks: list<string>, hardlinks: list<string>}
   *   Absolute paths, each list sorted.
   */
  public function scan(string $root, array $extensions): array {
    $files = [];
    $symlinks = [];
    $hardlinks = [];
    $directory = new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS);
    $filter = new \RecursiveCallbackFilterIterator(
      $directory,
      static function (\SplFileInfo $item) use (&$symlinks): bool {
        if ($item->isLink()) {
          $symlinks[] = $item->getPathname();
          return FALSE;
        }
        if ($item->isDir()) {
          return !in_array($item->getFilename(), self::SKIP_DIRS, TRUE);
        }
        return TRUE;
      },
    );
    foreach (new \RecursiveIteratorIterator($filter) as $item) {
      if (!$item instanceof \SplFileInfo || !$item->isFile()) {
        continue;
      }
      if (!$this->accepts($item->getFilename(), $extensions)) {
        continue;
      }
      $stat = @stat($item->getPathname());
      if ($stat !== FALSE && $stat['nlink'] > 1) {
        $hardlinks[] = $item->getPathname();
        continue;
      }
      $files[] = $item->getPathname();
    }
    sort($files);
    sort($symlinks);
    sort($hardlinks);
    return ['files' => $files, 'symlinks' => $symlinks, 'hardlinks' => $hardlinks];
  }

  /**
   * Whether a file name is one the migrator reads.
   *
   * @param string $filename
   *   The file name or path.
   * @param list<string> $extensions
   *   Extensions to include, from normalizeExtensions().
   */
  public function accepts(string $filename, array $extensions): bool {
    $base = basename($filename);
    if (str_contains($base, '.min.')) {
      return FALSE;
    }
    $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
    return in_array($ext, $extensions, TRUE) && in_array($ext, self::EXTENSIONS, TRUE);
  }

  /**
   * Migrates the text of one file.
   *
   * @param string $contents
   *   The file contents.
   * @param string $filename
   *   The file name; its extension decides the stylesheet-only rules.
   *
   * @return array{content: string, changes: list<array{line: int, before: string, after: string}>, notes: list<array{line: int, kind: string, token: string, v2: string, source: string}>}
   *   The migrated text, the changed lines and the manual-review notes.
   */
  public function migrate(string $contents, string $filename): array {
    $this->pcreErrors = [];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $isCss = in_array($ext, self::STYLESHEETS, TRUE);
    $nests = in_array($ext, ['scss', 'sass', 'less'], TRUE);
    $lines = explode("\n", $contents);
    $changes = [];
    $notes = [];
    $out = [];
    // Selector text of each open `{` block, outermost first (SCSS/Less only).
    $stack = [];
    foreach ($lines as $index => $orig) {
      $n = $index + 1;
      $errorMark = count($this->pcreErrors);
      $line = $orig;

      if ($nests) {
        if ($this->isPbNesting($orig, $stack)) {
          $notes[] = self::note($index + 1, 'scss-nesting', trim(strtok(trim($orig), '{') ?: trim($orig)), '?', $orig);
        }
        $stack = self::updateSelectorStack($orig, $stack);
      }

      // A v1 name that is part of a file name (pb__card.png) is a reference
      // to a file on disk, which this command never renames: protect it from
      // every rewrite below, then report it.
      $protected = [];
      $line = preg_replace_callback(
        '/(?<![' . self::TOKEN_CHARS . '])((?:pb__|paragraph__inner|pb-|h__|v__|js-pb-)[' . self::TOKEN_CHARS . ']*)(?=\.(?:png|svg|jpe?g|gif|webp|html\.twig|twig|js|css|scss)(?![' . self::TOKEN_CHARS . ']))/',
        function (array $m) use (&$protected): string {
          $protected[] = $m[1];
          return "\x00" . (count($protected) - 1) . "\x00";
        },
        $line,
      ) ?? $line;
      $protected = array_values(array_filter($protected, fn (string $token): bool => $this->isKnownV1($token)));
      if ($protected === [] && str_contains($line, "\x00")) {
        $line = $orig;
      }
      $line = $this->replace('id', $line, fn (array $m): string => $this->ids[$m[1]]);
      $line = $this->replace('struct', $line, fn (array $m): string => $this->structural[$m[1]]);
      if ($isCss) {
        $line = $this->replace('value', $line, fn (array $m): string => $this->values[$m[1]]);
        $line = $this->replace('chained', $line, fn (array $m): string => $m[1] . '.' . $this->generic[$m[2]]);
      }
      else {
        foreach ($this->find('value', $line) as $token) {
          $notes[] = self::note($n, 'value-in-template', $token, $this->values[$token], $orig);
        }
      }

      foreach ($this->find('ambiguous', $line) as $token) {
        $notes[] = self::note($n, 'ambiguous-id', '#' . $token . '…', $this->ambiguousIds[$token], $orig);
      }
      if (preg_match('/pb__|pb-|paragraph__inner|paragraph--type/', $line) === 1) {
        foreach ($this->find('generic', $line) as $token) {
          $notes[] = self::note($n, 'generic-token', $token, $this->generic[$token], $orig);
        }
      }
      foreach ($this->find('context', $line) as $token) {
        $notes[] = self::note($n, 'context-dependent', $token, $this->context[$token], $orig);
      }
      foreach ($this->find('removed', $line) as $token) {
        $notes[] = self::note($n, 'removed', $token, $this->removed[$token], $orig);
      }
      $leftover = '/(?<![' . self::TOKEN_CHARS . '])(pb__[' . self::TOKEN_CHARS . ']*|paragraph__inner)(?![' . self::TOKEN_CHARS . '])/';
      if (preg_match_all($leftover, $line, $matches) > 0) {
        foreach ($matches[1] as $token) {
          if (isset($this->frozen[$token]) || isset($this->removed[$token])) {
            continue;
          }
          if (isset($this->values[$token]) && !$isCss) {
            // Already reported as value-in-template.
            continue;
          }
          foreach (array_keys($this->ambiguousIds) as $prefix) {
            if (str_starts_with($token, $prefix)) {
              continue 2;
            }
          }
          $notes[] = self::note($n, 'unknown-v1-token', $token, '?', $orig);
        }
      }

      if (str_contains($line, "\x00")) {
        $line = $this->restoreProtected($line, $orig);
        foreach ($protected as $token) {
          $notes[] = self::note($n, 'file-reference', $token, $this->v2For($token), $orig);
        }
      }

      $lineErrors = array_slice($this->pcreErrors, $errorMark);
      if ($lineErrors !== []) {
        $notes[] = self::note($n, 'pcre-error', implode(', ', array_unique($lineErrors)), '?', $orig);
      }
      if ($line !== $orig) {
        $changes[] = ['line' => $n, 'before' => trim($orig), 'after' => trim($line)];
      }
      $out[] = $line;
    }
    return ['content' => implode("\n", $out), 'changes' => $changes, 'notes' => $notes];
  }

  /**
   * Whether a line uses SCSS/Less parent nesting on or around a PB class.
   *
   * @param string $line
   *   The original line.
   * @param list<string> $stack
   *   Selector text of the open blocks, outermost first.
   */
  private function isPbNesting(string $line, array $stack): bool {
    $hint = '/pb__|paragraph__inner|\.pb(?![A-Za-z0-9_])|\.pb-/';
    $enclosing = implode(' ', $stack);
    // Every selector on the line: the text before each `{`, so a one-line
    // `.pb { &__card {} }` is seen too.
    $pieces = explode('{', $line);
    array_pop($pieces);
    $selector = implode(' ', $pieces);
    $suffix = preg_match('/&(?:-|__)/', $selector) === 1;
    $interp = str_contains($selector, '#{');
    if (!$suffix && !$interp) {
      return FALSE;
    }
    return preg_match($hint, $enclosing) === 1 || preg_match($hint, $selector) === 1;
  }

  /**
   * Pushes and pops block selectors for the braces on one line.
   *
   * @param string $line
   *   The original line.
   * @param list<string> $stack
   *   Selector text of the open blocks, outermost first.
   *
   * @return list<string>
   *   The stack after the line.
   */
  private static function updateSelectorStack(string $line, array $stack): array {
    $text = '';
    $length = strlen($line);
    for ($i = 0; $i < $length; $i++) {
      $char = $line[$i];
      if ($char === '{') {
        $stack[] = trim($text);
        $text = '';
      }
      elseif ($char === '}') {
        array_pop($stack);
        $text = '';
      }
      elseif ($char === ';') {
        $text = '';
      }
      else {
        $text .= $char;
      }
    }
    return $stack;
  }

  /**
   * Whether a token is a v1 name this command knows or would report.
   */
  private function isKnownV1(string $token): bool {
    if (str_starts_with($token, 'pb__') || $token === 'paragraph__inner') {
      return TRUE;
    }
    return isset($this->structural[$token]) || isset($this->values[$token]);
  }

  /**
   * Returns the v2 name of a protected token, or '?'.
   */
  private function v2For(string $token): string {
    foreach ([$this->structural, $this->values] as $table) {
      if (isset($table[$token])) {
        return $table[$token];
      }
    }
    foreach ($this->ids as $old => $new) {
      if (str_starts_with($token, $old)) {
        return $new . substr($token, strlen($old));
      }
    }
    return '?';
  }

  /**
   * Puts protected file-name tokens back, from the original line.
   *
   * @param string $line
   *   The rewritten line, holding NUL-delimited placeholders.
   * @param string $orig
   *   The original line.
   */
  private function restoreProtected(string $line, string $orig): string {
    $tokens = [];
    preg_match_all('/(?<![' . self::TOKEN_CHARS . '])((?:pb__|paragraph__inner|pb-|h__|v__|js-pb-)[' . self::TOKEN_CHARS . ']*)(?=\.(?:png|svg|jpe?g|gif|webp|html\.twig|twig|js|css|scss)(?![' . self::TOKEN_CHARS . ']))/', $orig, $matches);
    $tokens = $matches[1];
    return preg_replace_callback('/\x00(\d+)\x00/', static fn (array $m): string => $tokens[(int) $m[1]] ?? '', $line) ?? $line;
  }

  /**
   * Builds a report note.
   *
   * @return array{line: int, kind: string, token: string, v2: string, source: string}
   *   The note.
   */
  private static function note(int $line, string $kind, string $token, string $v2, string $source): array {
    return [
      'line' => $line,
      'kind' => $kind,
      'token' => $token,
      'v2' => $v2,
      'source' => trim($source),
    ];
  }

  /**
   * Applies one compiled pattern with a callback.
   *
   * @param string $key
   *   The pattern key.
   * @param string $line
   *   The line.
   * @param callable(array<int|string, string>): string $callback
   *   Replacement callback.
   */
  private function replace(string $key, string $line, callable $callback): string {
    $pattern = $this->patterns[$key] ?? NULL;
    if ($pattern === NULL) {
      return $line;
    }
    $result = preg_replace_callback($pattern, $callback, $line);
    if ($result === NULL) {
      $this->pcreErrors[] = preg_last_error_msg();
      return $line;
    }
    return $result;
  }

  /**
   * Returns the first capture group of every match of one pattern.
   *
   * @return list<string>
   *   The matched tokens, in order.
   */
  private function find(string $key, string $line): array {
    $pattern = $this->patterns[$key] ?? NULL;
    if ($pattern === NULL) {
      return [];
    }
    $count = preg_match_all($pattern, $line, $matches);
    if ($count === FALSE) {
      $this->pcreErrors[] = preg_last_error_msg();
      return [];
    }
    if ($count < 1) {
      return [];
    }
    return array_values($matches[1]);
  }

  /**
   * Compiles a longest-first alternation of a table's keys.
   *
   * @param array<string, string> $table
   *   The table.
   * @param string $before
   *   Pattern text before the alternation.
   * @param string $after
   *   Pattern text after the alternation.
   */
  private static function compile(array $table, string $before, string $after): ?string {
    if ($table === []) {
      return NULL;
    }
    $keys = array_map('strval', array_keys($table));
    usort($keys, static fn (string $a, string $b): int => strlen($b) <=> strlen($a) ?: strcmp($a, $b));
    $quoted = array_map(static fn (string $k): string => preg_quote($k, '/'), $keys);
    return '/' . $before . implode('|', $quoted) . $after . '/';
  }

  /**
   * Requires a PHP file that returns an array.
   *
   * @return array<mixed>
   *   The array, or an empty array when the file is missing or malformed.
   */
  private static function loadArray(string $file): array {
    $raw = is_file($file) ? require $file : [];
    return is_array($raw) ? $raw : [];
  }

  /**
   * Keeps only the string => string entries of a value.
   *
   * @return array<string, string>
   *   The filtered map.
   */
  private static function stringMap(mixed $raw): array {
    $map = [];
    if (is_array($raw)) {
      foreach ($raw as $key => $value) {
        if (is_string($key) && is_string($value)) {
          $map[$key] = $value;
        }
      }
    }
    return $map;
  }

}
