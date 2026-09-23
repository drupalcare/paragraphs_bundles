<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_icon\Service;

/**
 * Default {@see PbiIconNameRegistryInterface} implementation.
 *
 * The name list ships as a plain newline-delimited text file rather than a PHP
 * array, and that is a measured choice, not a style preference. Measured
 * 2026-09-22 for the same 4,284 names: 57,485 bytes as text, 66,055 as JSON,
 * and 112,093 as a `var_export()`ed PHP array — the PHP form is roughly double
 * the size and buys nothing, because the file is read once per request at
 * most and immediately flipped into a hash for lookup.
 *
 * The list is Google's own `.codepoints` data from google/material-design-icons
 * with the codepoints stripped, because only the names are needed. All three
 * styles ship byte-identical name sets (verified: zero names unique to any one
 * style), so one list serves Outlined, Rounded and Sharp.
 */
final class PbiIconNameRegistry implements PbiIconNameRegistryInterface {

  /**
   * Lookup table, name => TRUE. NULL until the file is read.
   *
   * @var array<string, true>|null
   */
  private ?array $names = NULL;

  /**
   * Absolute path to the newline-delimited name list.
   */
  private readonly string $file;

  /**
   * Constructor.
   *
   * @param string|null $file
   *   Path to the name list. Defaults to the file shipped with this module.
   *   Injectable so a test can point at a small fixture instead of parsing
   *   4,284 names it does not need.
   */
  public function __construct(?string $file = NULL) {
    $this->file = $file ?? dirname(__DIR__, 2) . '/data/material-symbols-names.txt';
  }

  /**
   * {@inheritdoc}
   */
  public function isValid(string $name): bool {
    return isset($this->lookup()[trim($name)]);
  }

  /**
   * {@inheritdoc}
   */
  public function filter(array $names): array {
    $known = $this->lookup();
    $out = [];

    foreach ($names as $name) {
      $name = trim($name);
      if ($name !== '' && isset($known[$name])) {
        $out[$name] = TRUE;
      }
    }

    $out = array_keys($out);
    sort($out);

    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function all(): array {
    return array_keys($this->lookup());
  }

  /**
   * The name lookup, read from disk on first use.
   *
   * A missing or unreadable file yields an empty list rather than an error.
   * That is deliberate: every caller treats "no valid names" as "attach no
   * stylesheet", which degrades to the bundled font still on disk. Throwing
   * here would turn a packaging mistake into a white screen on a content page.
   *
   * @return array<string, true>
   *   Icon name => TRUE.
   */
  private function lookup(): array {
    if ($this->names !== NULL) {
      return $this->names;
    }

    if (!is_file($this->file) || !is_readable($this->file)) {
      $this->names = [];
      return $this->names;
    }

    $lines = file($this->file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === FALSE) {
      $this->names = [];
      return $this->names;
    }

    $this->names = [];
    foreach ($lines as $line) {
      $line = trim($line);
      if ($line !== '') {
        $this->names[$line] = TRUE;
      }
    }

    return $this->names;
  }

}
