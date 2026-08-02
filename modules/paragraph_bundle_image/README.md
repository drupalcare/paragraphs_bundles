# Paragraph Bundle Image

Three paragraph types for placing a media image in content:

- `image_bundle` — image at its original size
- `image_narrow_bundle` — responsive image constrained to the narrow set
- `image_wide_bundle` — responsive image across the wide set

The module also provides the media type every other bundle references for
images, so it is a dependency of `paragraph_bundle_card`,
`paragraph_bundle_hero`, `paragraph_bundle_image_grid`,
`paragraph_bundle_image_overlay`, `paragraph_bundle_node_reference`,
`paragraph_bundle_parallax`, and `paragraph_bundle_slideshow`.

## What it installs

- Media type `pb_media_image`, with form and view displays.
- Media view modes `pb_media_original_size`, `pb_media_responsive_narrow`,
  and `pb_media_responsive_wide`.
- Six breakpoints, `screen-xsm-min` … `screen-xxlg-min`. The smallest carries
  no media query; the rest step 414px, 576px, 992px, 1200px, 1400px.
- Thirteen image styles: `pb_narrow_*` and `pb_wide_*` at six sizes each, plus
  `pb_original_size`.
- Two responsive image styles: `pb_responsive_image_narrow` and
  `pb_responsive_image_wide`.

## Dependencies

Declared in `paragraph_bundle_image.info.yml`:

- `paragraphs_bundles` (parent, which brings `paragraphs` and
  `entity_reference_revisions`)
- Core `media`, `media_library`, `path`, `responsive_image`

`path` is required because the `pb_media_image` media form display uses the
path widget.

## Enable

```bash
drush en paragraph_bundle_image -y
drush cr
drush pb:regenerate-css
```

## Demo QA

1. Add Image, Image Narrow, and Image Wide paragraphs, each referencing a
   `pb_media_image` item.
2. Confirm the narrow and wide bundles emit `<picture>` with the responsive
   image styles, and the plain bundle emits `pb_original_size`.
3. Resize the viewport across the six breakpoints and confirm the source set
   changes.
4. Set Display colors and confirm dynamic CSS lands on
   `#pb__image-{id} > .pb-ui-inner`, `#pb__image-n-{id} > .pb-ui-inner`, and
   `#pb__image-w-{id} > .pb-ui-inner`.
5. Add an image link and link title; confirm the wrapper anchor and its
   `title` attribute.

## v2 notes

- Display colors use per-paragraph dynamic CSS, not inline `--pb-*`.
- The three bundles keep distinct id prefixes (`pb__image-`, `pb__image-n-`,
  `pb__image-w-`), so their dynamic CSS never collides.
- This module ships no CSS of its own; layout comes from the parent's base
  stylesheet.
