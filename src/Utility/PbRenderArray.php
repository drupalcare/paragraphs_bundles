<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Utility;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Template\Attribute;

/**
 * Appends to nested lists inside render arrays and preprocess variables.
 *
 * Hooks receive `array<string, mixed>`, so every `$build['#attached']
 * ['library'][] = …` reads an offset on a `mixed` intermediate: correct at
 * runtime whenever the intermediate is an array or absent, and a fatal when
 * it is a scalar. Ten hook classes across five modules wrote that line by
 * hand. This is the one place that knows how to widen the intermediate.
 */
final class PbRenderArray {

  /**
   * Appends values to a nested list, creating the intermediate levels.
   *
   * A non-array value already sitting at `$parents` (or at any level above
   * it) is replaced rather than appended to — it cannot be a render-array
   * list, so keeping it would only defer the fatal to the render layer.
   *
   * @param array<string, mixed> $build
   *   Render array or preprocess variables, by reference.
   * @param list<string> $parents
   *   Key path to the list, outermost first.
   * @param mixed ...$values
   *   Values to append, in order.
   */
  public static function append(array &$build, array $parents, mixed ...$values): void {
    if ($values === []) {
      return;
    }
    $existing = NestedArray::getValue($build, $parents);
    $list = is_array($existing) ? $existing : [];
    foreach ($values as $value) {
      $list[] = $value;
    }
    NestedArray::setValue($build, $parents, $list, TRUE);
  }

  /**
   * Attaches library names to `#attached`.
   *
   * @param array<string, mixed> $build
   *   Render array or preprocess variables, by reference.
   * @param string ...$libraries
   *   Library ids, `extension/library-name`.
   */
  public static function attachLibraries(array &$build, string ...$libraries): void {
    self::append($build, ['#attached', 'library'], ...$libraries);
  }

  /**
   * Adds classes to a variables array, honouring an Attribute object.
   *
   * Preprocess hooks see `attributes` as a plain array before the theme
   * layer converts it, but a hook running after that conversion — or a
   * template that pre-built the object — must not have it flattened.
   *
   * @param array<string, mixed> $variables
   *   Preprocess variables, by reference.
   * @param string ...$classes
   *   Classes to append.
   */
  public static function addClass(array &$variables, string ...$classes): void {
    if ($classes === []) {
      return;
    }
    $attributes = $variables['attributes'] ?? NULL;
    if ($attributes instanceof Attribute) {
      $attributes->addClass(...$classes);
      return;
    }
    self::append($variables, ['attributes', 'class'], ...$classes);
  }

}
