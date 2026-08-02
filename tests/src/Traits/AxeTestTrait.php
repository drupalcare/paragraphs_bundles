<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Traits;

/**
 * Runs axe-core accessibility checks inside FunctionalJavascript tests.
 *
 * The axe-core library is loaded from a vendored fixture
 * (tests/fixtures/axe.min.js). When the fixture is unavailable the assertion is
 * skipped rather than failing, so environments without it still run the rest of
 * the suite.
 */
trait AxeTestTrait {

  /**
   * Asserts a rendered subtree has no serious or critical axe violations.
   *
   * The color-contrast rule is disabled: contrast depends on the host theme,
   * not on the bundle markup this suite is responsible for.
   *
   * @param string $selector
   *   CSS selector scoping the axe run to one bundle's rendered markup.
   */
  protected function assertNoAxeViolations(string $selector): void {
    $axe = $this->loadAxeSource();
    if ($axe === NULL) {
      // Skipping locally is fine; skipping in CI is not. axe-core is no longer
      // committed (MPL-2.0, 572 KB), so a missing source in CI means the
      // download step failed — and a silently-skipped a11y suite reads as a
      // green build while asserting nothing. Fail loudly there.
      if (getenv('CI') !== FALSE) {
        $this->fail(
          'axe-core source not found in CI. PB_AXE_CORE_PATH is unset or the '
          . 'download step in .gitlab-ci.yml failed. Refusing to skip the '
          . 'accessibility assertions silently.'
        );
      }
      $this->markTestSkipped(
        'axe-core source not available. Set PB_AXE_CORE_PATH to a local '
        . 'axe.min.js to run accessibility assertions.'
      );
    }

    $session = $this->getSession();
    // Load axe-core into the page.
    $session->executeScript($axe);

    // Kick off the asynchronous analysis and stash the result on window so a
    // synchronous poll can read it (avoids driver execute-async differences).
    $context = json_encode($selector, JSON_THROW_ON_ERROR);
    $session->executeScript(<<<JS
      window.pbAxeDone = false;
      window.pbAxeViolations = [];
      axe.run($context, {
        resultTypes: ['violations'],
        rules: { 'color-contrast': { enabled: false } },
      }).then(function (results) {
        window.pbAxeViolations = results.violations;
        window.pbAxeDone = true;
      }).catch(function () {
        window.pbAxeDone = true;
      });
    JS);
    $this->assertJsCondition('window.pbAxeDone === true', 20000);

    $violations = $session->evaluateScript('window.pbAxeViolations') ?: [];
    $serious = array_values(array_filter(
      $violations,
      static fn (array $v): bool => in_array(
        $v['impact'] ?? '',
        ['serious', 'critical'],
        TRUE,
      ),
    ));

    $this->assertSame([], $serious, $this->formatAxeViolations($serious));
  }

  /**
   * Loads the vendored axe-core source, or NULL when unavailable.
   *
   * @return string|null
   *   The axe-core JavaScript source, or NULL when no fixture is found.
   */
  private function loadAxeSource(): ?string {
    $candidates = [
      (string) (getenv('PB_AXE_CORE_PATH') ?: ''),
      __DIR__ . '/../../fixtures/axe.min.js',
    ];
    foreach ($candidates as $path) {
      if ($path !== '' && is_file($path)) {
        $source = file_get_contents($path);
        return $source === FALSE ? NULL : $source;
      }
    }
    return NULL;
  }

  /**
   * Builds a readable failure message from axe violation records.
   *
   * @param array<int, array<string, mixed>> $violations
   *   The axe violation records.
   *
   * @return string
   *   A human-readable summary of the violations.
   */
  private function formatAxeViolations(array $violations): string {
    if ($violations === []) {
      return '';
    }
    $lines = ['axe-core found accessibility violations:'];
    foreach ($violations as $violation) {
      $targets = [];
      foreach ($violation['nodes'] ?? [] as $node) {
        $targets[] = implode(' ', $node['target'] ?? []);
      }
      $lines[] = sprintf(
        '- [%s] %s: %s (at: %s)',
        (string) ($violation['impact'] ?? 'unknown'),
        (string) ($violation['id'] ?? ''),
        (string) ($violation['help'] ?? ''),
        implode('; ', $targets),
      );
    }
    return implode("\n", $lines);
  }

}
