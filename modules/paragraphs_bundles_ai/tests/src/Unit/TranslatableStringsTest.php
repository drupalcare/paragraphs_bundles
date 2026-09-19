<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Every sentence a person reads is translatable, and the model's stays English.
 *
 * WHY A SOURCE SCAN AND NOT A BEHAVIOUR TEST. The defect this guards is a
 * sentence that was never wrapped, and an unwrapped sentence behaves perfectly
 * — it renders, it reads correctly, and every functional test passes. It is
 * only wrong for the people who do not read English, and none of them is in
 * the test suite. Measured 2026-09-18: 58 user-facing sentences across six
 * files were untranslated while the whole kernel suite was green.
 *
 * THE THIRD TEST IS THE ONE NOBODY EXPECTS, and it guards the opposite
 * mistake. SectionDrafter builds a system prompt and a JSON schema for the
 * MODEL. Those are not user interface: translating them would send a French
 * site's model a French instruction to produce content in a schema whose
 * property descriptions no longer match the field names, which is a defect
 * dressed as an improvement. A future pass that sees `sprintf()` in that file
 * and "finishes the job" is exactly what this catches.
 *
 * This is a Unit test on purpose: it reads files and needs no database, so it
 * runs anywhere, including where the kernel suite cannot.
 */
final class TranslatableStringsTest extends UnitTestCase {

  /**
   * Sinks whose contents are read by a person.
   *
   * Each is a place a complete sentence is handed to somebody: a refusal, a
   * note, a warning, a form label, a status message.
   */
  private const USER_SINKS = [
    '/::refused\(/',
    '/\$notes\[\]\s*=/',
    '/\$problems\[\]\s*=/',
    '/\$warnings\[\]\s*=/',
    '/addWarning\(|addStatus\(|addError\(/',
    "/'#(title|description|placeholder|markup|empty_option)'\s*=>/",
    '/->say\(/',
  ];

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
   * Every .php file under src/.
   *
   * @return list<string>
   *   Absolute paths.
   */
  private function sourceFiles(): array {
    $out = [];
    $dir = new \RecursiveDirectoryIterator($this->moduleRoot() . '/src');

    foreach (new \RecursiveIteratorIterator($dir) as $file) {
      if ($file->isFile() && $file->getExtension() === 'php') {
        $out[] = $file->getPathname();
      }
    }

    sort($out);

    return $out;
  }

  /**
   * No sentence handed to a person is left untranslated.
   */
  public function testEveryUserFacingSentenceIsTranslated(): void {
    $bare = [];

    foreach ($this->sourceFiles() as $path) {
      $lines = explode("\n", (string) file_get_contents($path));

      foreach ($lines as $number => $line) {
        // Comments describe these sinks constantly; they are not code.
        if (preg_match('#^\s*(//|\*|/\*)#', $line) === 1) {
          continue;
        }

        $isSink = FALSE;

        foreach (self::USER_SINKS as $pattern) {
          if (preg_match($pattern, $line) === 1) {
            $isSink = TRUE;
            break;
          }
        }

        if (!$isSink) {
          continue;
        }

        // The call may wrap over the next couple of lines.
        $window = implode("\n", array_slice($lines, $number, 3));

        if (preg_match('/->t\(|formatPlural\(|TranslatableMarkup/', $window) === 1) {
          continue;
        }

        // Only a literal carrying real words is a finding: a variable, an
        // array or a machine name passed through is not a sentence.
        if (preg_match("/'[^']*[a-z]{3}[^']*\s[^']*'/", $window) !== 1) {
          continue;
        }

        $bare[] = sprintf(
          '%s:%d  %s',
          basename($path),
          $number + 1,
          trim($line),
        );
      }
    }

    self::assertSame(
      [],
      $bare,
      "These sentences reach a person untranslated. Wrap each in \$this->t() "
      . "— or formatPlural() when it counts something:\n" . implode("\n", $bare),
    );
  }

  /**
   * Severity is carried, never recovered by reading the English.
   *
   * THE BUG THIS REPLACES SHIPPED. The form decided whether a note was a
   * warning with `str_starts_with($note, 'CAREFUL:')`. That is a question only
   * English can answer, so the moment the producers were translated — which is
   * what the test above now requires — every warning in every other language
   * would have quietly become a status message, and nothing would have gone
   * red. The two tests are therefore a pair: the first one makes the second
   * one necessary.
   */
  public function testSeverityIsNotRecoveredFromEnglish(): void {
    foreach ($this->sourceFiles() as $path) {
      $code = (string) file_get_contents($path);
      $code = preg_replace('#/\*.*?\*/#s', '', $code) ?? $code;
      $code = preg_replace('#^\s*//.*$#m', '', $code) ?? $code;

      self::assertDoesNotMatchRegularExpression(
        '/(str_starts_with|str_contains|strpos)\s*\(\s*\$(note|warning|message)\b/',
        $code,
        basename($path) . ' decides how serious a message is by reading its '
        . 'words. Carry the severity on the result instead — see '
        . 'CreationResult::$warnings.',
      );
    }
  }

  /**
   * What is sent to the model stays in English.
   *
   * The inverse guard. See the class docblock: these are instructions and a
   * JSON schema, not interface text, and translating them breaks the contract
   * the model answers inside.
   */
  public function testThePromptSentToTheModelStaysEnglish(): void {
    $drafter = (string) file_get_contents(
      $this->moduleRoot() . '/src/Service/SectionDrafter.php'
    );

    // The system prompt is assembled into $lines and handed to
    // setSystemPrompt(). Not one of those lines may be translated.
    preg_match_all('/\$lines\[\]\s*=\s*(.+)$/m', $drafter, $matches);

    self::assertNotEmpty($matches[1], 'The system prompt was not found at all.');

    foreach ($matches[1] as $line) {
      self::assertStringNotContainsString(
        '$this->t(',
        $line,
        'A system-prompt line is being translated. The model is not a reader '
        . 'of this interface: it answers inside a schema whose property names '
        . 'are English field names, and a translated instruction desynchronises '
        . 'the two. Leave it as it is.',
      );
    }
  }

}
