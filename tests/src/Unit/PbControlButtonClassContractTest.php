<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit;

use Drupal\Tests\paragraphs_bundles\Traits\ModuleFilesTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Every navigation or media control button carries the `control-btn` class.
 *
 * WHY THIS EXISTS. A theme that styles every `<button>` (APEX does, outside
 * `nav`) repaints carousel arrows, play/pause, dots, lightbox and close
 * controls with its own padding, fill and hover, and their module design is
 * lost. The maintainer's decision of 2026-10-07 is one shared class,
 * `control-btn`, on every such control so a theme can exclude them.
 *
 * The class is only worth anything if it is on EVERY control. So the rule is
 * stated the strict way round: every `<button>` this project ships carries
 * `control-btn` unless it is on the exclusion list below, and nothing on that
 * list carries it. A new control added without the class goes red here; so
 * does a styled button that picks it up by accident.
 *
 * Buttons built in front-end JavaScript are held to the same rule.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
class PbControlButtonClassContractTest extends TestCase {

  use ModuleFilesTrait;

  /**
   * The class every control button carries.
   */
  private const string CONTROL_CLASS = 'control-btn';

  /**
   * Buttons that are MEANT to look like buttons or tabs, keyed by a marker.
   *
   * A marker is a substring of the opening tag. Their theme styling is the
   * design, so they must NOT carry the control class.
   *
   * @var array<string, string>
   */
  private const array STYLED_BUTTONS = [
    'pb-accordion__button' => 'accordion trigger',
    'pb-accordion__toggle-all' => 'accordion expand/collapse-all',
    'pb-tabs__tab' => 'tab',
    'data-toggle="modal"' => 'the modal bundle open button',
  ];

  /**
   * Admin and AI scripts: their buttons are on admin pages, never controls.
   *
   * Paths are relative to the module root.
   *
   * @var list<string>
   */
  private const array ADMIN_SCRIPTS = [
    'js/paragraphs-bundles-color-picker.js',
    'modules/paragraph_bundle_alert/js/paragraph-bundle-alert-admin.js',
  ];

  /**
   * Template control buttons measured on 2026-10-07, the positive control.
   *
   * If the scan ever finds fewer, the scan is broken, not the templates.
   */
  private const int MIN_TEMPLATE_CONTROLS = 24;

  /**
   * Returns the module root.
   */
  private function root(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * Collects every `<button>` opening tag in the shipped templates.
   *
   * @return list<array{file: string, tag: string}>
   *   One entry per opening tag.
   */
  private function templateButtons(): array {
    $root = $this->root();
    $buttons = [];
    foreach ($this->moduleFiles($root) as $file) {
      if (!$file->isFile() || !str_ends_with($file->getFilename(), '.twig')) {
        continue;
      }
      $relative = substr($file->getPathname(), strlen($root) + 1);
      $source = (string) file_get_contents($file->getPathname());
      // Twig expressions inside the tag may contain `>`; step over them whole.
      preg_match_all('/<button\b(?:\{\{.*?\}\}|\{%.*?%\}|\{#.*?#\}|[^>{])*>/s', $source, $matches);
      foreach ($matches[0] as $tag) {
        $buttons[] = ['file' => $relative, 'tag' => $tag];
      }
    }
    return $buttons;
  }

  /**
   * Returns the static class tokens of an opening tag.
   *
   * Twig expressions are replaced by a space first: `nav-btn{{ x }}` renders
   * as `nav-btn` followed by whatever the expression yields, so a token glued
   * to an expression is still its own class.
   *
   * @return list<string>
   *   The whitespace-separated static tokens of the class attribute.
   */
  private function classTokens(string $tag): array {
    if (!preg_match('/\sclass="([^"]*)"/', $tag, $m)) {
      return [];
    }
    $static = (string) preg_replace('/\{\{.*?\}\}|\{%.*?%\}/s', ' ', $m[1]);
    return preg_split('/\s+/', trim($static)) ?: [];
  }

  /**
   * Returns the styled-button marker an opening tag matches, or NULL.
   */
  private function styledMarker(string $tag): ?string {
    foreach (array_keys(self::STYLED_BUTTONS) as $marker) {
      if (str_contains($tag, $marker)) {
        return $marker;
      }
    }
    return NULL;
  }

  /**
   * Every template control button carries the class; no styled button does.
   */
  public function testTemplateButtons(): void {
    $buttons = $this->templateButtons();
    $missing = [];
    $wrong = [];
    $controls = 0;
    foreach ($buttons as $button) {
      $has = in_array(self::CONTROL_CLASS, $this->classTokens($button['tag']), TRUE);
      $label = $button['file'] . ': ' . preg_replace('/\s+/', ' ', $button['tag']);
      if ($this->styledMarker($button['tag']) !== NULL) {
        if ($has) {
          $wrong[] = $label;
        }
        continue;
      }
      $controls++;
      if (!$has) {
        $missing[] = $label;
      }
    }

    $this->assertGreaterThanOrEqual(self::MIN_TEMPLATE_CONTROLS, $controls, 'The scan found fewer control buttons than were measured; the scan is broken.');
    $this->assertSame([], $missing, 'Control buttons missing the ' . self::CONTROL_CLASS . ' class.');
    $this->assertSame([], $wrong, 'Styled buttons that must NOT carry the ' . self::CONTROL_CLASS . ' class.');
  }

  /**
   * Every button built by front-end JavaScript carries the class.
   */
  public function testScriptBuiltButtons(): void {
    $root = $this->root();
    $missing = [];
    $found = 0;
    foreach ($this->moduleFiles($root) as $file) {
      if (!$file->isFile() || !str_ends_with($file->getFilename(), '.js') || str_ends_with($file->getFilename(), '.min.js')) {
        continue;
      }
      $relative = substr($file->getPathname(), strlen($root) + 1);
      if (in_array($relative, self::ADMIN_SCRIPTS, TRUE) || str_starts_with($relative, 'modules/paragraphs_bundles_ai/')) {
        continue;
      }
      $source = (string) file_get_contents($file->getPathname());
      preg_match_all('/(?:const|let|var)\s+(\w+)\s*=\s*document\.createElement\(\s*[\'"]button[\'"]\s*\)/', $source, $matches);
      foreach ($matches[1] as $var) {
        $found++;
        $pattern = '/\b' . preg_quote($var, '/') . '\.(?:className\s*=|classList\.add\()[^;]*' . preg_quote(self::CONTROL_CLASS, '/') . '/';
        if (!preg_match($pattern, $source)) {
          $missing[] = $relative . ': ' . $var;
        }
      }
    }

    // Positive control: the carousel dots are built in JS.
    $this->assertGreaterThanOrEqual(1, $found, 'No script-built button was found; the scan is broken.');
    $this->assertSame([], $missing, 'Script-built buttons missing the ' . self::CONTROL_CLASS . ' class.');
  }

}
