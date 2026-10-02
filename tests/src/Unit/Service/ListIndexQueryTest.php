<?php

declare(strict_types=1);

namespace Drupal\Tests\paragraphs_bundles\Unit\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\paragraphs_bundles\Service\ListIndex\ListIndexQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * The request values the Paragraphs List accepts, and the ones it refuses.
 */
#[Group('paragraphs_bundles')]
final class ListIndexQueryTest extends TestCase {

  /**
   * Builds the service with throwaway collaborators; normalize() uses neither.
   */
  private function service(): ListIndexQuery {
    return new ListIndexQuery(
      $this->createMock(Connection::class),
      $this->createMock(CacheBackendInterface::class),
    );
  }

  /**
   * Unknown sorts, directions and modes fall back to the defaults.
   *
   * @param array<string, mixed> $raw
   *   Raw query values.
   * @param array<string, string> $expected
   *   The subset of the normalised criteria that must come out.
   */
  #[DataProvider('provider')]
  public function testNormalize(array $raw, array $expected): void {
    $criteria = $this->service()->normalize($raw);
    foreach ($expected as $key => $value) {
      $this->assertSame($value, $criteria[$key], $key);
    }
  }

  /**
   * The longest possible label survives a cursor round trip, and is accepted.
   *
   * A 191-character label of 4-byte characters is the worst case: about 1050
   * characters of base64url. Too small a cap made Next on a label sort silently
   * return page one.
   */
  public function testWorstCaseLabelCursorRoundTrips(): void {
    $service = $this->service();
    $label = str_repeat("\u{1F600}", 191);
    $columns = ListIndexQuery::SORTS['label'];
    $encode = new \ReflectionMethod($service, 'encodeCursor');
    $decode = new \ReflectionMethod($service, 'decodeCursor');
    $row = [
      'pid' => 1234567,
      'bundle' => 'x',
      'root_type' => '',
      'root_id' => 0,
      'depth' => 0,
      'langcode' => 'en',
      'label' => $label,
    ];

    $cursor = $encode->invoke($service, $row, $columns);
    $this->assertGreaterThan(1000, strlen($cursor), 'This really is the long case.');
    $this->assertLessThanOrEqual(ListIndexQuery::CURSOR_MAX, strlen($cursor));
    $this->assertSame([$label, 1234567], $decode->invoke($service, $cursor, $columns));
    $this->assertSame($cursor, $service->normalize(['after' => $cursor])['after'], 'normalize() does not cut it.');

    $cjk = str_repeat('漢', 191);
    $row['label'] = $cjk;
    $cursor = $encode->invoke($service, $row, $columns);
    $this->assertSame([$cjk, 1234567], $decode->invoke($service, $cursor, $columns));
  }

  /**
   * Cases for testNormalize().
   *
   * @return array<string, array{0: array<string, mixed>, 1: array<string, string>}>
   *   Each case: raw input, expected criteria subset.
   */
  public static function provider(): array {
    return [
      'empty request' => [[], ['sort' => 'pid', 'dir' => 'asc', 'title_mode' => 'starts', 'type' => '']],
      'title starts, no sort: ordered by the title index' => [['title' => 'Al'], ['sort' => 'node_title']],
      'title starts, sort chosen: kept' => [['title' => 'Al', 'sort' => 'pid'], ['sort' => 'pid']],
      'title contains, no sort: pid' => [['title' => 'Al', 'title_mode' => 'contains'], ['sort' => 'pid']],
      'valid sort' => [['sort' => 'nid', 'dir' => 'desc'], ['sort' => 'nid', 'dir' => 'desc']],
      'unknown sort' => [['sort' => 'title; DROP TABLE x'], ['sort' => 'pid']],
      'unknown direction' => [['dir' => 'sideways'], ['dir' => 'asc']],
      'unknown mode' => [['title_mode' => 'regex'], ['title_mode' => 'starts']],
      'contains mode' => [['title_mode' => 'contains'], ['title_mode' => 'contains']],
      'array instead of string' => [['type' => ['a'], 'title' => ['b']], ['type' => '', 'title' => '']],
      'trims and caps' => [['title' => '  ' . str_repeat('x', 400)], ['title' => str_repeat('x', 255)]],
    ];
  }

}
