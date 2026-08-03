<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The flip box offers a real control instead of an inoperable focus target.
 *
 * Three Level A findings, all from the audit:
 *
 * F-1 (WCAG 2.1.1) the scene carried `tabindex="0"` on a `<div>` with no role,
 * no accessible name and no key handler — a keyboard user could land on it and
 * nothing responded.
 *
 * F-2 (WCAG 1.3.2) `backface-visibility` is a visual property and does not
 * touch the accessibility tree, so both faces were exposed at once.
 *
 * F-3 (WCAG 2.4.3, 2.4.7) links on the face turned away stayed in the tab
 * order while invisible.
 *
 * The isolation itself is `inert`, applied by the behaviour, which a Kernel
 * test cannot execute. What it can hold is the contract the script depends on:
 * the control exists, it is wired to the faces it controls, and the dead focus
 * target is gone. The behaviour is asserted in the FunctionalJavascript suite.
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class PbFlipBoxAriaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * @var list<string>
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'file',
    'image',
    'link',
    'options',
    'views',
    'media',
    'media_library',
    'node',
    'entity_reference_revisions',
    'paragraphs',
    'field_group',
    'paragraphs_bundles',
    'paragraph_bundle_3d_flip_box',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('media');
    $this->installConfig(['system', 'filter']);
    $this->container->get('config.installer')->installOptionalConfig();
  }

  /**
   * Renders a flip box paragraph.
   *
   * @return string
   *   The rendered markup.
   */
  private function renderFlipBox(): string {
    $paragraph = Paragraph::create(['type' => '3d_flip_box_bundle']);
    $paragraph->save();

    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('paragraph')
      ->view($paragraph, 'default');

    return (string) $this->container->get('renderer')->renderRoot($build);
  }

  /**
   * F-1: the scene no longer advertises a focus target that does nothing.
   */
  public function testSceneIsNotFocusable(): void {
    $html = $this->renderFlipBox();

    $this->assertStringContainsString('pb-3d-flip-box__scene', $html);
    $this->assertStringNotContainsString('tabindex="0"', $html);
  }

  /**
   * A real button is rendered, and it names what it does.
   */
  public function testToggleButtonIsRendered(): void {
    $html = $this->renderFlipBox();

    $this->assertStringContainsString('pb-3d-flip-box__toggle', $html);
    $this->assertStringContainsString('type="button"', $html);
    $this->assertStringContainsString('aria-expanded="false"', $html);
    // The icon is decoration; the name comes from the visually-hidden text.
    $this->assertStringContainsString('aria-hidden="true"', $html);
    $this->assertStringContainsString('visually-hidden', $html);
  }

  /**
   * The `aria-controls` target exists on the page.
   *
   * A dangling `aria-controls` is the defect the slideshow shipped with, and
   * the one thing a rendered-markup test can catch that review keeps missing.
   */
  public function testAriaControlsResolves(): void {
    $html = $this->renderFlipBox();

    $matched = preg_match('/aria-controls="([^"]+)"/', $html, $controls);
    $this->assertSame(1, $matched, 'The toggle declares aria-controls.');
    $this->assertStringContainsString('id="' . $controls[1] . '"', $html);
  }

  /**
   * The control starts hidden, so a page without the script shows no control.
   *
   * The faces stay operable in that case — exactly as before this fix — which
   * is why the button, not the markup, is what carries the new behaviour.
   */
  public function testToggleIsHiddenUntilTheBehaviourAttaches(): void {
    $html = $this->renderFlipBox();

    $matched = preg_match('/<button[^>]*pb-3d-flip-box__toggle[^>]*>/', $html, $button);
    $this->assertSame(1, $matched, 'The toggle button is rendered.');
    $this->assertStringContainsString('hidden', $button[0]);
    $this->assertStringNotContainsString('inert', $html);
  }

  /**
   * The bundle ships the script that makes the button work.
   */
  public function testBehaviourIsAttached(): void {
    $library = $this->container->get('library.discovery')
      ->getLibraryByName('paragraph_bundle_3d_flip_box', 'paragraph-bundle-3d-flip-box');

    $this->assertIsArray($library);
    $files = array_column($library['js'] ?? [], 'data');
    $this->assertNotEmpty(
      preg_grep('#paragraph-bundle-3d-flip-box\.js$#', $files),
      'The flip box library ships its behaviour.',
    );
    $this->assertContains('core/once', $library['dependencies'] ?? []);
  }

}
