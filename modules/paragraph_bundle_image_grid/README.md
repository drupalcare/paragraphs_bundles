# Paragraph Bundle Image Grid

`image_grid_bundle` + `image_grid_section_bundle` — responsive CSS grid with
nested image cells, optional links/titles, and a lightbox gallery.

## Dependencies

- `paragraphs_bundles` (parent)
- `paragraph_bundle_image` (`pb_media_image`, `pb_image_grid` view mode)
- Core `image`, `options`
- `paragraphs`, `entity_reference_revisions`

## Enable

```bash
drush en paragraph_bundle_image_grid -y
drush cr
drush pb:regenerate-css
```

If the module was already enabled before v2 shared fields shipped, reinstall
so optional config imports title tag/alignment + link color fields:

```bash
drush pmu paragraph_bundle_image_grid -y
drush en paragraph_bundle_image_grid -y
drush cr
```

## Demo QA

1. Add an Image Grid paragraph with several nested section items.
2. Set grid min cell width and gap; confirm layout on the wrapper.
3. Set Display colors on wrapper and section cells; confirm dynamic CSS.
4. Click expand on a cell — lightbox opens with full-size image URL.
5. Set title/tag/alignment on wrapper and section cells.

## v2 notes

- Wrapper colors use dynamic CSS on `#pb-image-grid-{id} > .pb-ui-inner`.
- Section colors split: border on inner, bg/text on `.pb-image-grid__title`.
- Grid width/gap remain inline on `.pb-image-grid__wrapper` (editor px values).
- `PbigPreprocessSectionHook` sets `pb_full_size_url` for lightbox JS.
