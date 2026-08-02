# Paragraph Bundle Tabs

`tabs_bundle` paragraph type with nested `tab_section_bundle` items, horizontal
or
vertical tab layout, optional icon tab labels, and breakpoint-driven stacking.

## Dependencies

- `paragraphs_bundles` (parent)
- Core `options`
- `paragraphs`, `entity_reference_revisions`

## Enable

```bash
drush en paragraph_bundle_tabs -y
drush cr
drush pb:regenerate-css
```

## Demo QA

1. Add a Tabs paragraph with 2+ tab sections (text label or icon markup).
2. Toggle vertical tabs and breakpoint class; set animation class on panes.
3. Set Display colors including Active tab item color (`--pb-bt-a`).
4. Confirm keyboard navigation (arrow keys) and ARIA roles on
   tablist/tab/tabpanel.
5. Confirm dynamic CSS selector: `#pb__tabs-{id} > .paragraph__inner`

## Tab section icon field

When **Use icon** is checked on a tab section, enter icon markup in Tab name
(Font Awesome `fas fa-house`, Material `material-icons:home`, etc.). The theme
must load the icon font library.

## v2 notes

- Display colors use per-paragraph dynamic CSS, not inline `--pb-*`.
- v1 outer id preserved: `pb__tabs-{id}`; tab grid id `pb__tab-{id}`.
- `PbDynamicCssBuilder` emits `--pb-bt-a` from `pb_display_tab_active_item`.
