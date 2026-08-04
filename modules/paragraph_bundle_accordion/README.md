# Paragraph Bundle Accordion

`accordion_bundle` paragraph type with nested `accordion_section_bundle` items,
expand/collapse-all controls, and optional panel animation classes.

## Dependencies

- `paragraphs_bundles` (parent)
- Core `options`
- `paragraphs`, `entity_reference_revisions`

## Enable

```bash
drush en paragraph_bundle_accordion -y
drush cr
drush pb:regenerate-css
```

## Demo QA

1. Add an Accordion paragraph with multiple sections (title + nested body).
2. Toggle expand-all, open-first, and panel indicator options.
3. Set Display colors including Active item color (`--pb-bt-a`).
4. Confirm keyboard navigation and ARIA on panel buttons.
5. Confirm dynamic CSS selector: `#pb-accordion-{id} > .pb-ui-inner`

## v2 notes

- Display colors use per-paragraph dynamic CSS, not inline `--pb-*`.
- Outer id `pb-accordion-{id}` (v1: `pb__accor-wrap-{id}`); the section host
  inside it is `pb-accordion__sections-{id}`.
- `PbDynamicCssBuilder` emits `--pb-bt-a` from `pb_display_accor_active_item`.
