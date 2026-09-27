<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\paragraphs_bundles_ai\Model\FieldFact;

/**
 * Supplies the picture the module ships, for a field nobody has filled.
 *
 * WHY THIS EXISTS AT ALL. A model can write the words of a hero and cannot
 * write its picture: an image is chosen, not composed. Measured on sts.local
 * 2026-09-17, eight of PB's bundles have a REQUIRED image — hero, image,
 * image_narrow, image_overlay, image_wide, and the card, image-grid and
 * parallax section bundles — so the assistant produced a hero with real
 * headline copy, no picture, and nothing saying a picture was missing. The
 * maintainer's words: a hero without an image is useless.
 *
 * SO THE MODULE BRINGS ITS OWN. One placeholder ships in `images/`, is copied
 * into the site's files on first use, and is put in any empty picture field
 * the assistant creates. The person then sees a section that works and swaps
 * the picture for theirs — which is the point: a visible stand-in they will
 * replace, rather than an empty box they must debug.
 *
 * IT IS CREATED ONCE AND REUSED. Every later section points at the same file
 * and the same media item, so a page of twelve sections adds one file to the
 * site, not twelve.
 *
 * EVERY METHOD MAY RETURN NULL, AND THAT IS A REAL ANSWER. A site without the
 * Media module has no media item to make, a site without the File module has
 * nowhere to put the bytes, and a media type whose source field cannot be
 * found must not be guessed at. NULL means "no picture was supplied"; the
 * caller says so rather than pretending the field is filled.
 */
interface DefaultImageInterface {

  /**
   * The value to write into one picture field, ready for set().
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\FieldFact $fact
   *   The field to fill. Both shapes PB uses are handled: a plain `image`
   *   field, and an `entity_reference` to a media item.
   * @param array{0: int, 1: int}|null $shape
   *   The size the field is shown at, from PictureShapes. The placeholder is
   *   drawn at that size with the size written on it, so the person can see
   *   what to replace it with. NULL means any shape: the shipped picture.
   *
   * @return array<string, mixed>|null
   *   The field value, or NULL when no picture could be supplied — a site
   *   without Media for a media field, or any failure to write the file.
   */
  public function valueFor(FieldFact $fact, ?array $shape = NULL): ?array;

  /**
   * The managed file for a placeholder, creating it on first use.
   *
   * @param array{0: int, 1: int}|null $shape
   *   Width and height to draw it at, or NULL for the shipped picture.
   *
   * @return int|null
   *   The file entity id, or NULL when the file could not be written.
   */
  public function fileId(?array $shape = NULL): ?int;

  /**
   * A media item of one type holding the shipped picture.
   *
   * @param string $mediaBundle
   *   The media type the field accepts, such as `pb_media_image`.
   * @param array{0: int, 1: int}|null $shape
   *   Width and height to draw it at, or NULL for the shipped picture.
   *
   * @return int|null
   *   The media entity id, or NULL when Media is not installed, the type does
   *   not exist, or its source field could not be determined.
   */
  public function mediaId(string $mediaBundle, ?array $shape = NULL): ?int;

}
