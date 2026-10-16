<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit\Migration;

use Drupal\paragraphs_bundles\Migration\PbMigrationPaths;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Tests the target, report and write rules of `drush pb:migrate-theme`.
 *
 * Builds a throwaway tree in the system temp directory:
 * base/web (the Drupal root), base/web2 (a sibling whose name starts with
 * "web"), base/outside, and inside web: core, modules/pb, sites/files and
 * themes/custom/{mytheme,linked -> base/outside}.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
class PbMigrationPathsTest extends TestCase {

  /**
   * The real path of the scratch base directory.
   */
  private string $base = '';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $base = sys_get_temp_dir() . '/pb-migrate-paths-' . bin2hex(random_bytes(6));
    foreach (['web/core', 'web/modules/pb', 'web/sites/files', 'web/themes/custom/mytheme', 'web2/themes', 'outside'] as $dir) {
      mkdir($base . '/' . $dir, 0777, TRUE);
    }
    $this->base = (string) realpath($base);
    symlink($this->base . '/outside', $this->base . '/web/themes/custom/linked');
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if ($this->base !== '') {
      exec('rm -rf ' . escapeshellarg($this->base));
    }
    parent::tearDown();
  }

  /**
   * A theme name and a relative directory both resolve inside the root.
   */
  public function testThemeNameAndRelativePathResolve(): void {
    $paths = $this->paths();
    $expected = $this->base . '/web/themes/custom/mytheme';
    $this->assertSame($expected, $paths->resolveTarget('mytheme'));
    $this->assertSame($expected, $paths->resolveTarget('themes/custom/mytheme'));
    $this->assertSame($expected, $paths->resolveTarget($expected));
    $this->assertSame('themes/custom/mytheme', $paths->relative($expected));
  }

  /**
   * A relative path is read from the invoking folder first, then the root.
   */
  public function testRelativeTargetUsesCwdFirst(): void {
    $paths = $this->paths();
    $expected = $this->base . '/web/themes/custom/mytheme';
    // From the project folder (parent of web/), as the docs say.
    $this->assertSame($expected, $paths->resolveTarget('web/themes/custom/mytheme', $this->base));
    // Not found from the cwd: falls back to the Drupal root.
    $this->assertSame($expected, $paths->resolveTarget('themes/custom/mytheme', $this->base));
    // Found from the cwd but outside the root: still refused.
    $this->expectException(\InvalidArgumentException::class);
    $paths->resolveTarget('outside', $this->base);
  }

  /**
   * A report folder that is not writable is refused before any work.
   */
  public function testReportFolderNotWritableRefused(): void {
    $dir = $this->base . '/outside/ro';
    mkdir($dir);
    chmod($dir, 0555);
    try {
      $this->paths()->resolveReport($dir . '/r.md', '/');
      $this->fail('accepted');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertStringContainsString('not writable', $e->getMessage());
    }
    finally {
      chmod($dir, 0777);
    }
  }

  /**
   * From web/sites/<site>, every way of naming the target works.
   */
  public function testTargetFromSiteFolder(): void {
    touch($this->base . '/composer.json');
    mkdir($this->base . '/web/sites/ah', 0777, TRUE);
    $paths = $this->paths();
    $site = $this->base . '/web/sites/ah';
    $theme = $this->base . '/web/themes/custom/mytheme';
    $this->assertSame($this->base, $paths->project());
    // Project-relative, root-relative, theme name, and a whole folder.
    $this->assertSame($theme, $paths->resolveTarget('web/themes/custom/mytheme', $site));
    $this->assertSame($theme, $paths->resolveTarget('themes/custom/mytheme', $site));
    $this->assertSame($theme, $paths->resolveTarget('mytheme', $site));
    $this->assertSame($this->base . '/web/themes/custom', $paths->resolveTarget('web/themes/custom', $site));
  }

  /**
   * The not-found error lists every absolute path it tried.
   */
  public function testNotFoundListsEveryPathTried(): void {
    touch($this->base . '/composer.json');
    mkdir($this->base . '/web/sites/ah', 0777, TRUE);
    try {
      $this->paths()->resolveTarget('web/themes/nope', $this->base . '/web/sites/ah');
      $this->fail('accepted');
    }
    catch (\InvalidArgumentException $e) {
      foreach (['/web/sites/ah/web/themes/nope', '/web/themes/nope', '/web/web/themes/nope'] as $tail) {
        $this->assertStringContainsString($this->base . $tail, $e->getMessage());
      }
    }
  }

  /**
   * A relative report that lands in the web root moves to the project folder.
   */
  public function testReportRelocatedToProjectFolder(): void {
    touch($this->base . '/composer.json');
    mkdir($this->base . '/web/sites/ah', 0777, TRUE);
    $result = $this->paths()->resolveReport('pb-report.md', $this->base . '/web/sites/ah');
    $this->assertSame($this->base . '/pb-report.md', $result['path']);
    $this->assertSame($this->base . '/web/sites/ah/pb-report.md', $result['moved_from']);
    // Outside the root: kept where asked, not moved.
    $result = $this->paths()->resolveReport('r.md', $this->base . '/outside');
    $this->assertNull($result['moved_from']);
    // An absolute path inside the root is still refused.
    $this->expectException(\InvalidArgumentException::class);
    $this->paths()->resolveReport($this->base . '/web/sites/ah/r.md', '/');
  }

  /**
   * With no Composer project folder, a relative report in the root is refused.
   */
  public function testReportRefusedWithoutProjectFolder(): void {
    mkdir($this->base . '/web/sites/ah', 0777, TRUE);
    $this->assertNull($this->paths()->project());
    try {
      $this->paths()->resolveReport('pb-report.md', $this->base . '/web/sites/ah');
      $this->fail('accepted');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertStringContainsString('--report=' . $this->base . '/pb-report.md', $e->getMessage());
    }
  }

  /**
   * Targets outside the root, or covering refused folders, are refused.
   */
  public function testRefusedTargets(): void {
    $cases = [
      'parent segments' => ['themes/../../outside', 'outside the Drupal root'],
      'absolute outside' => [$this->base . '/outside', 'outside the Drupal root'],
      'sibling prefix' => [$this->base . '/web2/themes', 'outside the Drupal root'],
      'symlink out' => ['themes/custom/linked', 'symbolic link'],
      'root itself' => ['.', 'whole Drupal root'],
      'core' => ['core', 'Drupal core'],
      'module itself' => ['modules/pb', 'Drupal core'],
      'contains module' => ['modules', 'Drupal core'],
      'files dir' => ['sites/files', 'Drupal core'],
      'missing' => ['nothing_here', 'not a theme or an existing directory'],
    ];
    foreach ($cases as $label => [$target, $message]) {
      try {
        $this->paths()->resolveTarget($target);
        $this->fail("$label: $target was accepted");
      }
      catch (\InvalidArgumentException $e) {
        $this->assertStringContainsString($message, $e->getMessage(), $label);
      }
    }
  }

  /**
   * A relative report resolves against the invoking directory.
   */
  public function testReportResolvesAgainstCwd(): void {
    $paths = $this->paths();
    $this->assertSame($this->base . '/pb-report.md', $paths->resolveReport('../pb-report.md', $this->base . '/web2')['path']);
    $this->assertSame($this->base . '/outside/r.md', $paths->resolveReport('r.md', $this->base . '/outside')['path']);
    $this->assertSame($this->base . '/outside/r.md', $paths->resolveReport($this->base . '/outside/r.md', '/')['path']);
  }

  /**
   * Reports inside the root, existing files, symlinks and folders are refused.
   */
  public function testRefusedReports(): void {
    touch($this->base . '/outside/old.md');
    symlink($this->base . '/outside/old.md', $this->base . '/outside/link.md');
    symlink($this->base . '/outside/none.md', $this->base . '/outside/dangling.md');
    $cases = [
      'relative from root' => ['pb-report.md', $this->base . '/web', 'web server'],
      'inside a theme' => ['themes/custom/mytheme/style.css', $this->base . '/web', 'web server'],
      'files dir' => [$this->base . '/web/sites/files/r.md', '/', 'web server'],
      'existing file' => [$this->base . '/outside/old.md', '/', 'already exists'],
      'symlink' => [$this->base . '/outside/link.md', '/', 'already exists'],
      'dangling symlink' => [$this->base . '/outside/dangling.md', '/', 'already exists'],
      'missing folder' => [$this->base . '/nope/r.md', '/', 'does not exist'],
      'folder' => [$this->base . '/outside/', '/', 'file name'],
    ];
    foreach ($cases as $label => [$report, $cwd, $message]) {
      try {
        $this->paths()->resolveReport($report, $cwd);
        $this->fail("$label: $report was accepted");
      }
      catch (\InvalidArgumentException $e) {
        $this->assertStringContainsString($message, $e->getMessage(), $label);
      }
    }
  }

  /**
   * The safe example in the refusal is outside the root.
   */
  public function testRefusalNamesSafeExample(): void {
    try {
      $this->paths()->resolveReport('r.md', $this->base . '/web');
      $this->fail('accepted');
    }
    catch (\InvalidArgumentException $e) {
      $this->assertStringContainsString('--report=' . $this->base . '/pb-report.md', $e->getMessage());
    }
  }

  /**
   * An atomic write replaces content, keeps permissions and leaves no temp.
   */
  public function testWriteAtomic(): void {
    $file = $this->base . '/outside/a.css';
    file_put_contents($file, 'old');
    chmod($file, 0640);
    $this->assertTrue(PbMigrationPaths::writeAtomic($file, 'new'));
    $this->assertSame('new', file_get_contents($file));
    clearstatcache();
    $this->assertSame(0640, fileperms($file) & 0777);
    $this->assertSame(['a.css'], array_values(array_diff((array) scandir($this->base . '/outside'), ['.', '..'])));
  }

  /**
   * A failed atomic write leaves the original intact.
   */
  public function testWriteAtomicFailureKeepsOriginal(): void {
    $dir = $this->base . '/outside/ro';
    mkdir($dir);
    $file = $dir . '/a.css';
    file_put_contents($file, 'old');
    chmod($dir, 0555);
    try {
      $this->assertFalse(@PbMigrationPaths::writeAtomic($file, 'new'));
      $this->assertSame('old', file_get_contents($file));
    }
    finally {
      chmod($dir, 0777);
    }
  }

  /**
   * Returns the rules for the scratch tree.
   */
  private function paths(): PbMigrationPaths {
    $web = $this->base . '/web';
    return new PbMigrationPaths(
      $web,
      static fn (string $name): ?string => $name === 'mytheme' ? 'themes/custom/mytheme' : NULL,
      ['core', 'modules/pb', $web . '/sites/files'],
      [$web . '/sites/files'],
    );
  }

}
