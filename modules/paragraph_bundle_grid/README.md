# Paragraph Bundle Grid

`grid_bundle` paragraph type: responsive CSS grid with 1–12 columns,
configurable
gap, breakpoint-specific column rules, and nested body paragraphs.

## Dependencies

- `paragraphs_bundles` (parent)
- Core `options`
- `paragraphs`, `entity_reference_revisions`

## Enable

```bash
drush en paragraph_bundle_grid -y
drush cr
drush pb:regenerate-css
```

If the module was already enabled before v2 shared fields shipped, reinstall
so optional config imports title tag/alignment + link color fields:

```bash
drush pmu paragraph_bundle_grid -y
drush en paragraph_bundle_grid -y
drush cr
```

## Demo QA

1. Add a Grid paragraph with several nested body items.
2. Set column count, gap (px), and breakpoint library.
3. Resize viewport: confirm column count changes at the chosen breakpoint
   (`pb__576`, `pb__768`, `pb__992`, `pb__1200`, `pb__1400`).
4. Set Display colors; confirm dynamic CSS on `#pb-grid-{id} > .pb-ui-inner`.
5. Set grid gap (px); confirm `#pb-grid-{id} .pb-grid__wrapper { gap: … }` in
   dynamic CSS.

## v2 notes

- Display colors use per-paragraph dynamic CSS, not inline `--pb-*`.
- Grid gap is emitted on `.pb-grid__wrapper` via dynamic CSS when set.
- Breakpoint CSS libraries attach lazily from `pb_display_grid_breakpoints`.
