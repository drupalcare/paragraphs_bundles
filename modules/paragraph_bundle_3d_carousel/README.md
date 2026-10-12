# Paragraph Bundle 3D Carousel

`3d_carousel_bundle` paragraph type: CSS 3D carousel with nested slide
paragraphs, prev/next controls, optional autoplay, and responsive breakpoint
CSS libraries.

## Dependencies

- `paragraphs_bundles` (parent)
- Core `options`
- `paragraphs`, `entity_reference_revisions`

## Enable

```bash
drush en paragraph_bundle_3d_carousel -y
drush cr
drush pb:regenerate-css
```

## Demo QA

1. Add a 3D Carousel paragraph with several nested image (or node reference)
   items.
2. Set large/small height classes and breakpoint library.
3. Set perspective (px) and rotation delay; confirm autoplay + pause button.
4. Set Display colors; confirm dynamic CSS on `#pb-3d-carousel-{id} >
   .pb-ui-inner`.

## v2 notes

- No `pb_content_title`: wire/verify scripts SKIP (expected).
- Dynamic CSS on inner (`pb-ui-inner` / `pb-3d-carousel__inner`); perspective on
  inner (editor px value).
- Outer id `pb-3d-carousel-{id}`; ring id `pb-3d-carousel-ring-{id}`.
- Breakpoint CSS libraries attach lazily from `pb_display_3d_caro_breakpoints`.
