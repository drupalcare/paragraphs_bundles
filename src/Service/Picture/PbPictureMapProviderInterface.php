<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Picture;

/**
 * Hands the picture binder the `pictures` map of one set of Patterns.
 *
 * A module that ships Patterns tags a service implementing this with
 * `paragraphs_bundles.picture_map_provider`. Each Pattern names, per picture
 * slot, the media view mode its picture is drawn with, or, for an `image`
 * field no formatter draws, only the image style (`view_mode` is then '').
 * A slot key is `<paragraph type>.<picture field>`, and it applies to that
 * field anywhere in the Pattern's tree.
 *
 * @phpstan-type PictureSlot array{style: string, responsive: bool, view_mode: string}
 */
interface PbPictureMapProviderInterface {

  /**
   * Returns every slot key any of this provider's Patterns binds.
   *
   * Lets the binder skip, at once, every paragraph field no Pattern names.
   *
   * @return list<string>
   *   Slot keys, `<paragraph type>.<media field>`.
   */
  public function slots(): array;

  /**
   * Returns the pictures map of the Pattern a class names.
   *
   * @param string $bundle
   *   The paragraph type that carries the class.
   * @param string $class
   *   One CSS class from the paragraph's CSS classes field.
   *
   * @return array<string, PictureSlot>|null
   *   The map keyed by slot key; an empty array when the Pattern shows no
   *   picture of its own. NULL when the class names none of this provider's
   *   Patterns.
   */
  public function pictures(string $bundle, string $class): ?array;

}
