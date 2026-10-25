<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles_ai\Unit;

use Drupal\paragraphs_bundles_ai\SectionSource\SectionSourceInterface;
use Drupal\paragraphs_bundles_ai\SectionSource\SectionSources;
use PHPUnit\Framework\TestCase;

/**
 * The bar's "source:<id>:<key>" values map back to the right source.
 *
 * WHY THIS EXISTS. draft() hands a chosen value to resolve(), and a value that
 * resolves when it should not would send a bundle name, or a forged value, to
 * a source's create(). The form re-checks the key against the source's
 * options, so this guards the step before that: only a registered source with
 * a non-empty key comes back.
 *
 * Extends PHPUnit's TestCase for the reason TranslatableStringsTest gives:
 * this project's Unit bootstrap has no Drupal UnitTestCase.
 */
final class SectionSourcesTest extends TestCase {

  /**
   * Builds the registry with one source, plus one whose id is refused.
   */
  private function sources(): SectionSources {
    $good = $this->createMock(SectionSourceInterface::class);
    $good->method('id')->willReturn('patterns');
    $bad = $this->createMock(SectionSourceInterface::class);
    $bad->method('id')->willReturn('Bad-Id');
    return new SectionSources([$good, $bad, new \stdClass()]);
  }

  /**
   * Only a well-formed source id is registered.
   */
  public function testOnlyValidSourcesAreKept(): void {
    $this->assertSame(['patterns'], array_keys($this->sources()->all()));
  }

  /**
   * A value built by value() resolves to its source and key.
   */
  public function testValueRoundTrips(): void {
    $sources = $this->sources();
    $value = SectionSources::value('patterns', 'hero:split');
    $this->assertSame('source:patterns:hero:split', $value);

    $resolved = $sources->resolve($value);
    $this->assertNotNull($resolved);
    $this->assertSame('patterns', $resolved[0]->id());
    $this->assertSame('hero:split', $resolved[1], 'A key may itself hold a colon.');
  }

  /**
   * Anything else is not a source value.
   */
  public function testOtherValuesDoNotResolve(): void {
    $sources = $this->sources();
    $this->assertNull($sources->resolve('simple_bundle'), 'A bundle name.');
    $this->assertNull($sources->resolve('source:unknown:key'), 'An unregistered source.');
    $this->assertNull($sources->resolve('source:patterns:'), 'An empty key.');
    $this->assertNull($sources->resolve('source:patterns'), 'No key at all.');
    $this->assertNull($sources->resolve('source:Bad-Id:key'), 'A refused source id.');
  }

}
