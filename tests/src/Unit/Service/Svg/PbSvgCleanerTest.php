<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit\Service\Svg;

use Drupal\Tests\UnitTestCase;
use Drupal\paragraphs_bundles\Service\Svg\PbSvgCleaner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Nothing hostile survives the cleaner, and real drawings stay intact.
 */
#[CoversClass(PbSvgCleaner::class)]
#[Group('paragraphs_bundles')]
final class PbSvgCleanerTest extends UnitTestCase {

  /**
   * Hostile markup, and the text that must not be in the output.
   *
   * @return array<string, array{string, string}>
   *   Input, forbidden text (lower case).
   */
  public static function hostileProvider(): array {
    return [
      'script' => ['<svg><script>alert(1)</script></svg>', 'alert'],
      'onload' => ['<svg onload="alert(1)"></svg>', 'alert'],
      'handler on child' => ['<svg><path onclick="alert(1)"/></svg>', 'alert'],
      'markup in style' => ['<svg><style><img src=x onerror=alert(1)></style></svg>', 'onerror'],
      'markup in cdata' => ['<svg><![CDATA[<img src=x onerror=alert(1)>]]></svg>', 'onerror'],
      'markup in cdata in style' => ['<svg><style><![CDATA[<img src=x onerror=alert(1)>]]></style></svg>', 'onerror'],
      'html element' => ['<svg><img src="x"></svg>', '<img'],
      'html in text' => ['<svg><text><img src="x"></text></svg>', '<img'],
      'html in desc' => ['<svg><desc><iframe src="x"></iframe></desc></svg>', '<iframe'],
      'math' => ['<svg><math><mtext><img src="x"></mtext></math></svg>', '<math'],
      'foreignObject' => ['<svg><foreignObject><div>x</div></foreignObject></svg>', 'foreignobject'],
      'meta refresh' => ['<svg><meta http-equiv="refresh" content="0;url=https://evil.test"></svg>', 'evil.test'],
      'form' => ['<svg><form action="https://evil.test"></form></svg>', 'evil.test'],
      'link' => ['<svg><link rel="stylesheet" href="https://evil.test/x.css"></svg>', '<link'],
      'image' => ['<svg><image src="https://evil.test/x.png"/></svg>', 'evil.test'],
      'href js' => ['<svg><a href="javascript:alert(1)"><text>x</text></a></svg>', 'alert'],
      'href js hidden by newline' => ['<svg><a href="java&#10;script:alert(1)"><text>x</text></a></svg>', 'alert'],
      'xlink js' => ['<svg><a xlink:href="javascript:alert(1)"><text>x</text></a></svg>', 'alert'],
      'external use' => ['<svg><use href="https://evil.test/x.svg#a"/></svg>', 'evil.test'],
      'animation to js' => ['<svg><a><set attributeName="href" to="javascript:alert(1)"/></a></svg>', 'alert'],
      'external url in style attribute' => ['<svg><path style="fill:url(https://evil.test/x)"/></svg>', 'evil.test'],
      'relative url in fill' => ['<svg><path fill="url(evil.svg#a)"/></svg>', 'evil.svg'],
      'external url in style' => ['<svg><style>a{fill:url(https://evil.test/x)}</style></svg>', 'evil.test'],
      'import in style' => ['<svg><style>@import "x.css";</style></svg>', '@import'],
      'processing instruction' => ['<svg><?x ><img src=x onerror=alert(1)>?></svg>', 'onerror'],
      'processing instruction in text' => ['<svg><text><?pi a="><img src=x onerror=alert(1)>"?></text></svg>', 'onerror'],
      'escaped url in style' => ['<svg><style>svg{background:u\\72l(https://evil.test/2)}</style></svg>', 'evil.test'],
      'escaped import' => ['<svg><style>@\\69mport "https://evil.test/3.css";</style></svg>', 'evil.test'],
      'image-set in style' => ['<svg><style>a{background:image-set("https://evil.test/a" 1x)}</style></svg>', 'evil.test'],
      'image-set in attribute' => ['<svg><rect style="background:image-set(\'https://evil.test/6\' 1x)"/></svg>', 'evil.test'],
      'escaped url in attribute' => ['<svg><rect fill="u\\72l(https://evil.test/7#a)"/></svg>', 'evil.test'],
      'ping' => ['<svg><a href="#x" ping="https://evil.test/p"><text>x</text></a></svg>', 'evil.test'],
      'animate spaced href' => ['<svg><a><set attributeName=" href " to="https://evil.test/"/></a></svg>', 'evil.test'],
      'animate handler' => ['<svg><set attributeName="onmouseover" to="alert(1)"/></svg>', 'alert'],
      'animate style' => ['<svg><set attributeName="style" to="evil-test"/></svg>', 'evil-test'],
      'comment' => ['<svg><!-- secret --></svg>', 'secret'],
    ];
  }

  /**
   * Each hostile input loses the hostile part.
   */
  #[DataProvider('hostileProvider')]
  public function testHostileRemoved(string $input, string $forbidden): void {
    $out = (new PbSvgCleaner())->clean($input);
    self::assertNotNull($out);
    self::assertStringNotContainsString($forbidden, strtolower($out));
  }

  /**
   * A real exported drawing keeps everything it needs.
   */
  public function testDrawingKept(): void {
    $out = (new PbSvgCleaner())->clean('<?xml version="1.0"?><!DOCTYPE svg><svg viewBox="0 0 10 10"><title>Car</title><style><![CDATA[.a>.b{fill:url(#g)}]]></style><defs><linearGradient id="g"><stop offset="0"/></linearGradient><clipPath id="c"><rect width="1"/></clipPath><filter id="f"><feGaussianBlur stdDeviation="1"/></filter></defs><path class="a" fill="url(#g)" clip-path="url(\'#c\')" d="M0 0"/><text>hi</text><use xlink:href="#g"/></svg>');
    self::assertNotNull($out);
    self::assertStringStartsWith('<svg', $out);
    foreach (['viewBox="0 0 10 10"', '<title>Car</title>', '<style>.a>.b{fill:url(#g)}</style>', 'linearGradient id="g"', 'clipPath id="c"', 'feGaussianBlur', 'fill="url(#g)"', 'clip-path="url(\'#c\')"', '<text>hi</text>', 'xlink:href="#g"'] as $kept) {
      self::assertStringContainsString($kept, $out);
    }
    self::assertStringNotContainsString('CDATA', $out);
  }

  /**
   * Text without an svg element gives nothing.
   */
  public function testNoSvg(): void {
    self::assertNull((new PbSvgCleaner())->clean('<p>no drawing</p>'));
    self::assertNull((new PbSvgCleaner())->clean(''));
  }

}
