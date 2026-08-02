# Paragraph Bundle Icon

`icon_bundle` paragraph type with Google Material Symbols or an uploaded image
icon, optional title/body/link, and the standard Paragraphs Bundles Display tab.

## Dependencies

- `paragraphs_bundles` (parent)
- Core `image`, `link`, `options`, `text`

## Enable

```bash
drush en paragraph_bundle_icon -y
drush cr
drush pb:regenerate-css
```

## v2 notes

- Display colors use per-paragraph dynamic CSS (`pb_dynamic_library`), not
  inline
  `--pb-*` styles on the inner wrapper.
- List display fields read stored keys via `paragraph.field.value` in Twig.
- GMS font-load reveal: JS scopes to `.paragraphs-bundles.pb-icon` per
  paragraph.
