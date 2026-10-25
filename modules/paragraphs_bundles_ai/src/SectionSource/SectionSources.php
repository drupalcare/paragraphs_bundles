<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\SectionSource;

/**
 * The section sources other modules tagged, by id.
 *
 * The bar lists each source item as "source:<id>:<key>". No bundle machine
 * name can contain a colon, so a source item and a bundle never collide.
 */
final class SectionSources {

  /**
   * The prefix of a source item's value in the "Add section" list.
   */
  public const PREFIX = 'source:';

  /**
   * Sources keyed by id.
   *
   * @var array<string, \Drupal\paragraphs_bundles_ai\SectionSource\SectionSourceInterface>
   */
  private array $sources = [];

  /**
   * Constructs the collection.
   *
   * @param iterable<mixed> $sources
   *   The services tagged `paragraphs_bundles_ai.section_source`.
   */
  public function __construct(iterable $sources) {
    foreach ($sources as $source) {
      if ($source instanceof SectionSourceInterface && preg_match('/^[a-z0-9_]+$/', $source->id()) === 1) {
        $this->sources[$source->id()] = $source;
      }
    }
  }

  /**
   * Every source.
   *
   * @return array<string, \Drupal\paragraphs_bundles_ai\SectionSource\SectionSourceInterface>
   *   Keyed by id.
   */
  public function all(): array {
    return $this->sources;
  }

  /**
   * The list value of one source item.
   *
   * @param string $sourceId
   *   The source id.
   * @param string $key
   *   The item's key.
   *
   * @return string
   *   The value.
   */
  public static function value(string $sourceId, string $key): string {
    return self::PREFIX . $sourceId . ':' . $key;
  }

  /**
   * The source and key a list value names, or NULL when it names none.
   *
   * @param string $value
   *   A submitted "Add section" value.
   *
   * @return array{0: \Drupal\paragraphs_bundles_ai\SectionSource\SectionSourceInterface, 1: string}|null
   *   The source and the item key.
   */
  public function resolve(string $value): ?array {
    if (!str_starts_with($value, self::PREFIX)) {
      return NULL;
    }
    $rest = substr($value, strlen(self::PREFIX));
    $colon = strpos($rest, ':');
    if ($colon === FALSE) {
      return NULL;
    }
    $source = $this->sources[substr($rest, 0, $colon)] ?? NULL;
    $key = substr($rest, $colon + 1);
    return $source === NULL || $key === '' ? NULL : [$source, $key];
  }

}
