# Paragraph Bundle Carousel

`carousel_bundle` paragraph type — horizontal, vertical, or hybrid carousel with
nested slide items, arrows/dots navigation, autoplay timing, and breakpoint CSS.

## Dependencies

- `paragraphs_bundles` (parent)
- Core `options`
- `paragraphs`, `entity_reference_revisions`

## Enable

```bash
drush en paragraph_bundle_carousel -y
drush cr
drush pb:regenerate-css
```

## Demo QA

1. Add a Carousel paragraph with 3+ nested slide items.
2. Set navigation (arrows, dots, both), autoplay interval, and breakpoint.
3. Test keyboard focus, prev/next buttons, and dot navigation.
4. Set Display colors; confirm dynamic CSS on
   `#pb__carousel-{id} > .paragraph__inner`.
5. Content tab order: Title → Title tag → Title alignment → Carousel items.

## v2 notes

- Display colors use per-paragraph dynamic CSS, not inline `--pb-*`.
- Slide gap and custom slide width remain inline on carousel items (v1
  behavior).
- Breakpoint CSS libraries attach lazily from `pb_display_carousel_breakpoints`.
