# Paragraph Bundle Image Overlay

`image_overlay_bundle` paragraph type: responsive image with configurable hover
overlay effects (fade, zoom, slide directions, title bar).

## Dependencies

- `paragraphs_bundles` (parent)
- `paragraph_bundle_image` (`pb_media_image`, responsive wide view mode)
- Core `options`
- `paragraphs`

## Enable

```bash
drush en paragraph_bundle_image_overlay -y
drush cr
drush pb:regenerate-css
```

If the module was already enabled before v2 shared fields shipped, reinstall
so optional config imports title tag/alignment + link color fields:

```bash
drush pmu paragraph_bundle_image_overlay -y
drush en paragraph_bundle_image_overlay -y
drush cr
```

## Demo QA

1. Add an Image Overlay paragraph with a media image and optional title/link.
2. Try each overlay style (fade, zoom, slide, title).
3. Set Display colors; confirm dynamic CSS on overlay container and title bar.
4. Set border color; confirm border on inner wrapper.

## v2 notes

- Split dynamic CSS: border/link on `#pb-image-overlay-{id} > .pb-ui-inner`;
  bg/text on `.pb-overlay__container` and `.pb-overlay__title`.
- Overlay style class `pb-image-overlay--{key}` on the outer wrapper (v1 shipped
  `pb-image-overlay-{key}`; the stored field value is unchanged).
- Border radius class on `.pb-overlay__container`; padding class on title.
