<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraph_bundle_3d_carousel\Kernel;

use Drupal\Component\Utility\Html;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The carousel wrapper carries the 1.x type class, 3d-carousel-bundle.
 *
 * 1.x hard-coded `paragraph--type--3d-carousel-bundle`. 2.0.0 built it with
 * `paragraph.bundle|clean_class`, and Html::getClass() turns a LEADING DIGIT
 * into an underscore, so 2.0.0 emitted `paragraph--type--_d-carousel-bundle`
 * and every theme rule written against the 1.x class stopped matching.
 * Both are emitted now: the 1.x class is restored and the 2.0.0 one is kept,
 * so a theme written against either release keeps matching.
 *
 * @group paragraphs_bundles
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
#[Group('paragraphs_bundles')]
#[RunTestsInSeparateProcesses]
final class Pb3dcClassTest extends KernelTestBase {

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
    'media',
    'node',
    'entity_reference_revisions',
    'paragraphs',
    'field_group',
    'paragraphs_bundles',
    'paragraph_bundle_3d_carousel',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('media');
    $this->installConfig(['system']);
    $this->container->get('config.installer')->installOptionalConfig();
  }

  /**
   * The rendered wrapper holds both the 1.x and the 2.0.0 type class.
   */
  public function testWrapperCarriesTheV1TypeClass(): void {
    $paragraph = Paragraph::create(['type' => '3d_carousel_bundle']);
    $paragraph->save();

    // A variable, because renderRoot() takes its argument by reference.
    $build = $this->container->get('entity_type.manager')
      ->getViewBuilder('paragraph')
      ->view($paragraph);
    $html = (string) $this->container->get('renderer')->renderRoot($build);

    $classes = $this->wrapperClasses($html);

    // CONTROL: the carousel template rendered at all. Without it an empty
    // render would fail for the wrong reason, or a fallback template would
    // pass for one.
    self::assertContains('pb-3d-carousel', $classes, 'The 3D carousel template did not render, so the class assertion would measure some other template.');

    self::assertContains('paragraph--type--3d-carousel-bundle', $classes, 'The carousel wrapper lost the 1.x class paragraph--type--3d-carousel-bundle; themes written for 1.x no longer match it.');

    $type_classes = array_values(array_filter($classes, static fn (string $class): bool => str_starts_with($class, 'paragraph--type--')));
    sort($type_classes);
    self::assertSame(['paragraph--type--3d-carousel-bundle', 'paragraph--type--_d-carousel-bundle'], $type_classes, 'The wrapper must carry the 1.x class and keep the 2.0.0 class, and no other type class.');
  }

  /**
   * The class tokens of the element with role="region" — the wrapper.
   *
   * @param string $html
   *   Rendered markup.
   *
   * @return list<string>
   *   Class tokens, or [] when no wrapper was rendered.
   */
  private function wrapperClasses(string $html): array {
    $xpath = new \DOMXPath(Html::load($html));
    $nodes = $xpath->query('//*[@role="region"]');
    if ($nodes === FALSE || $nodes->length !== 1) {
      return [];
    }
    $node = $nodes->item(0);
    if (!$node instanceof \DOMElement) {
      return [];
    }

    return array_values(preg_split('/\s+/', trim($node->getAttribute('class'))) ?: []);
  }

}
