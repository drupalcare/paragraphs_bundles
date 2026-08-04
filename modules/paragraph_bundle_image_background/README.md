# Paragraph Bundle Image Background

`image_background_bundle` — full-width CSS background image with optional color
overlay, responsive small-screen image swap, and nested content paragraphs.

## Dependencies

- `paragraphs_bundles` (parent)
- Core `image`, `options`
- `paragraphs`, `entity_reference_revisions`

## Enable

```bash
drush en paragraph_bundle_image_background -y
drush cr
```

## Demo QA

1. Add an Image Background paragraph; upload large (and optional small) bg
   images.
2. Set position, sizing, attachment, min-height, and overlay color/opacity.
3. Add nested content in `pb_content_main`.
4. Resize browser below breakpoint — JS swaps to small background image.
5. Confirm outer id `#pb-image-background-{id}` and overlay `.img-color-overlay`.

## v2 notes

- Background, overlay, and sizing values emit in per-paragraph dynamic CSS
  (`pb_dynamic_library`); JS swaps large/small `background-image` on the outer
  wrapper when a small-screen image is configured.
- No `pb_content_title` — wire/verify scripts SKIP (content-only bundle).
- Display tab fields nest into Colors / Spacing / Size / Additional groups.
