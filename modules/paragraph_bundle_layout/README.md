# Paragraph Bundle Layout

Three paragraph types for column layouts:

- `one_column_bundle` — single content column
- `two_columns_bundle` — left/right columns with optional sticky, toggle, and
  breakpoint layout CSS
- `three_columns_bundle` — left/middle/right columns with the same interactive
  features

## Dependencies

- `paragraphs_bundles` (parent)
- Core `options`
- `paragraphs`, `entity_reference_revisions`

## Enable

```bash
drush en paragraph_bundle_layout -y
drush cr
drush pb:regenerate-css
```

If the module was already enabled before v2 shared fields shipped, reinstall
so optional config imports title tag/alignment + link color fields:

```bash
drush pmu paragraph_bundle_layout -y
drush en paragraph_bundle_layout -y
drush cr
```

## Demo QA

1. Add One Column, Two Columns, and Three Columns paragraphs with nested body
   items.
2. Set Display colors; confirm dynamic CSS on each bundle's
   `#pb-{layout-slug}-{id} > .pb-ui-inner`.
3. On two/three column bundles: enable column toggles, sticky columns, and a
   layout breakpoint; resize viewport and test keyboard toggle buttons.
4. Confirm column animation ms and sticky top offset apply via inline grid
   CSS variables (not dynamic CSS).

## v2 notes

- Display colors use per-paragraph dynamic CSS, not inline `--pb-*`.
- Layout list fields read stored keys from `paragraph.field.value`.
- Breakpoint CSS libraries attach lazily from `pb_display_layout_breakpoints`.
- Interactive column fields ship in optional YAML (v1 update 8101–8103 logic
  absorbed into config).
