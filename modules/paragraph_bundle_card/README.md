# Paragraph Bundle Card

Four paragraph types:

- `card_two_columns_bundle` / `card_three_columns_bundle` — column layouts
- `card_text_section_bundle` / `card_image_section_bundle` — nested sections

## Dependencies

- `paragraphs_bundles`, `paragraph_bundle_image`
- Core `image`, `link`, `media`, `media_library`, `options`, `text`
- `paragraphs`, `entity_reference_revisions`

## Enable

```bash
drush en paragraph_bundle_card -y
drush cr
drush pb:regenerate-css
```

## Demo QA

1. Add a two- or three-column card; nest text/image sections in each column.
2. Set layout + breakpoint on the parent; confirm responsive CSS library loads.
3. Confirm dynamic CSS selectors match v1 outer ids:
   - `#pb__card-two-columns-{id} > .paragraph__inner`
   - `#pb__card-three-columns-{id} > .paragraph__inner`
   - `#pb-card-text-section-{id} > .paragraph__inner`
   - `#pb__image-{id}` split scopes (inner border + `.pb__card-image-title`
     overlay)

## v2 notes

- Display colors use per-paragraph dynamic CSS, not inline `--pb-*`.
- Card image section splits border colours (inner) from overlay bg/text
  (`.pb__card-image-title`) via `PbDynamicCssBuilder::SPLIT_COLOR_SCOPES`.
