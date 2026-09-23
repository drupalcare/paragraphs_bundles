<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The Material Symbols fonts ship, and no document may claim otherwise.
 *
 * WHY THIS TEST EXISTS. A 2.0.x-dev commit removed the three bundled `.woff2`
 * files and required every site to supply the font itself. That silently turned
 * every icon into its ligature name in plain text on upgrade — a paragraph set
 * to `home` rendered the word "home" — with no update path and nothing logged.
 * It was reverted in 4ea0ce5, and the files have shipped ever since.
 *
 * The revert restored the CODE. It did not restore the DOCUMENTATION, and for
 * two releases' worth of dev history four documents described the same fact
 * with two of them wrong. Measured 2026-09-22: CHANGELOG.md and the icon
 * README.md correctly said the fonts ship, while docs/UPGRADE-V1-TO-V2.md told
 * upgraders their icons would render as plain words "until your site supplies
 * the font", and docs/WHATS-NEW-IN-2.0.md counted the 11 MB among things no
 * longer in the package. Both sent a reader to do work that was already done
 * for them, and the UPGRADE passage additionally asserted that Apache-2.0 is
 * incompatible with this project's licence, which is wrong — GPL-2.0-OR-LATER
 * reaches GPLv3 through the "or later" clause, and Apache-2.0 is
 * GPLv3-compatible. The README had the reasoning right the whole time.
 *
 * Nothing in the repository could go red about any of that, which is the only
 * reason it survived. This test is that enforcement.
 *
 * WHAT THIS DELIBERATELY DOES NOT CATCH, so the next reader is not misled:
 *
 * - It matches present-tense CLAIMS that the fonts are absent, never the
 *   HISTORY of the reverted removal. CHANGELOG.md and the icon README.md both
 *   narrate that history correctly and at length, using the same vocabulary a
 *   false claim would use. A guard that fired on those would be red on the two
 *   documents that are right, so the patterns below are assertions, not words.
 * - It cannot catch a NEW phrasing nobody has written yet. A bullet quietly
 *   re-listing the fonts under a "removed" heading in prose this file has never
 *   seen would pass. The structural half — the files exist, and `git archive`
 *   ships them — is what makes that survivable, because a false claim with the
 *   fonts still present is a documentation bug, not a broken install.
 * - "no longer in the package" is NOT a pattern, though it reads like the
 *   obvious one. It is true of the 28 vendored LICENSE files and of
 *   `.pb-u-animate--fading`, and both CHANGELOG.md and WHATS-NEW-IN-2.0.md say
 *   it about them correctly.
 *
 * @group paragraphs_bundles
 */
#[Group('paragraphs_bundles')]
final class PbBundledFontDocsTest extends TestCase {

  /**
   * The bundled font files, relative to the module root.
   *
   * Google's own hashed release filenames, referenced by `src: url(...)` in
   * css/material-symbols-{outlined,rounded,sharp}.css. Renaming one here means
   * renaming it in the matching stylesheet or the @font-face resolves to
   * nothing and every icon falls back to its ligature name again.
   */
  private const FONT_FILES = [
    'modules/paragraph_bundle_icon/fonts/kJEhBvYX7BgnkSrUwT8OhrdQw4oELdPIeeII9v6oFsLjBuVY.woff2',
    'modules/paragraph_bundle_icon/fonts/sykg-zNym6YjUruM-QrEh7-nyTnjDwKNJ_190FjzaqkNCeE.woff2',
    'modules/paragraph_bundle_icon/fonts/gNMVW2J8Roq16WD5tFNRaeLQk6-SHQ_R00k4aWHSSmlN.woff2',
  ];

  /**
   * Sentences that assert the fonts are not shipped. Each one is false.
   *
   * These are the actual claims that were live in the repository. They are
   * phrased as assertions about the present, so that a document describing the
   * reverted removal in the past tense stays green.
   *
   * ⚠ WRITTEN AS PROSE, MATCHED ACROSS LINE BREAKS, AND THAT IS THE WHOLE
   * POINT. The first version of this guard matched these line by line, and it
   * would have passed the very passage it was written for: Markdown wraps at
   * 79 columns, so the live text read "v2 does **not**" at the end of one line
   * and "ship the font" at the start of the next, and "do not" / "belong in a
   * GPL project's" split the same way. Only one of the five phrases happened
   * to fall inside a single line. A guard that reports one of two bad files as
   * clean is worse than none, because the green is believed. claimPattern()
   * therefore joins the words with `\s+`, which in PHP spans newlines.
   */
  private const FALSE_CLAIMS = [
    'no longer bundled',
    'does **not** ship the font',
    'does not ship the font',
    'until your site supplies the font',
    "incompatible with this project's GPL",
    'do not belong in a GPL project',
  ];

  /**
   * One prose phrase, as a regex that survives Markdown line wrapping.
   *
   * @param string $phrase
   *   The phrase as a person would write it, with single spaces.
   *
   * @return string
   *   A case-insensitive pattern whose words may be separated by any run of
   *   whitespace, including a newline.
   */
  private function claimPattern(string $phrase): string {
    $words = preg_split('/\s+/', trim($phrase)) ?: [];
    $quoted = array_map(
      static fn (string $word): string => preg_quote($word, '/'),
      $words,
    );

    return '/' . implode('\s+', $quoted) . '/i';
  }

  /**
   * The module root.
   *
   * @return string
   *   Absolute path to the module directory.
   */
  private function moduleRoot(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * Every Markdown file in the project.
   *
   * @return list<string>
   *   Absolute paths.
   */
  private function markdownFiles(): array {
    $out = [];
    $dir = new \RecursiveDirectoryIterator($this->moduleRoot());
    $iterator = new \RecursiveIteratorIterator($dir);

    foreach ($iterator as $file) {
      if (!$file->isFile() || $file->getExtension() !== 'md') {
        continue;
      }
      if (str_contains($file->getPathname(), '/.git/')) {
        continue;
      }
      $out[] = $file->getPathname();
    }

    sort($out);

    return $out;
  }

  /**
   * The fonts are on disk, which is the premise everything else rests on.
   */
  public function testTheBundledFontsArePresent(): void {
    foreach (self::FONT_FILES as $relative) {
      $path = $this->moduleRoot() . '/' . $relative;

      self::assertFileExists(
        $path,
        basename($relative) . ' is missing. Removing a bundled font turns every '
        . 'icon on every upgrading site into its ligature name in plain text, '
        . 'with no update path and nothing logged. That shipped once and was '
        . 'reverted in 4ea0ce5 — see this class docblock before restoring it.',
      );
    }
  }

  /**
   * Attribution ships with the binaries, because Apache-2.0 requires it.
   */
  public function testTheFontLicenceShipsWithTheFonts(): void {
    self::assertFileExists(
      $this->moduleRoot() . '/modules/paragraph_bundle_icon/fonts/LICENSE-Material-Symbols.txt',
      'The Material Symbols fonts are redistributed under Apache-2.0, which '
      . 'permits redistribution WITH attribution. The licence file is that '
      . 'attribution and is not optional.',
    );
  }

  /**
   * The release tarball carries the fonts too, not just the git repository.
   *
   * Releases are built by drupal.org with `git archive`, which honours
   * export-ignore. A `fonts/` entry in .gitattributes would leave the files
   * present for every developer and absent for every site installing from a
   * release — the original bug, reintroduced where nobody testing from a git
   * checkout could see it.
   */
  public function testTheFontsAreNotExcludedFromTheReleaseTarball(): void {
    $path = $this->moduleRoot() . '/.gitattributes';

    if (!is_file($path)) {
      self::assertTrue(TRUE, 'No .gitattributes, so nothing is export-ignored.');
      return;
    }

    $lines = explode("\n", (string) file_get_contents($path));

    foreach ($lines as $number => $line) {
      if (str_starts_with(trim($line), '#') || !str_contains($line, 'export-ignore')) {
        continue;
      }

      self::assertStringNotContainsString(
        'font',
        strtolower($line),
        sprintf(
          '.gitattributes:%d export-ignores a font path, so `git archive` '
          . 'would build a release tarball without the Material Symbols '
          . 'fonts while every git checkout still has them: %s',
          $number + 1,
          trim($line),
        ),
      );
    }
  }

  /**
   * No document tells a reader the fonts are absent, because they are not.
   */
  public function testNoDocumentClaimsTheFontsAreUnbundled(): void {
    $found = [];

    foreach ($this->markdownFiles() as $path) {
      $source = (string) file_get_contents($path);

      foreach (self::FALSE_CLAIMS as $phrase) {
        $matches = [];

        if (preg_match($this->claimPattern($phrase), $source, $matches, PREG_OFFSET_CAPTURE) !== 1) {
          continue;
        }

        [$text, $offset] = $matches[0];

        $found[] = sprintf(
          '%s:%d  %s',
          str_replace($this->moduleRoot() . '/', '', $path),
          substr_count(substr($source, 0, (int) $offset), "\n") + 1,
          preg_replace('/\s+/', ' ', (string) $text),
        );
      }
    }

    self::assertSame(
      [],
      $found,
      "These lines tell a reader the Material Symbols fonts are not shipped. "
      . "They are shipped — see modules/paragraph_bundle_icon/fonts/ and this "
      . "class docblock. Describing the reverted removal in the PAST tense is "
      . "fine and is what CHANGELOG.md and the icon README.md do:\n"
      . implode("\n", $found),
    );
  }

}
