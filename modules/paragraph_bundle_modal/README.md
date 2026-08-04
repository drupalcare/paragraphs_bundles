# Paragraph Bundle Modal

`modal_bundle` paragraph type with a trigger button, dialog content, and nested
body/footer paragraphs.

## Dependencies

- `paragraphs_bundles` (parent)
- Core `options`, `text`
- `paragraphs`, `entity_reference_revisions`

## Enable

```bash
drush en paragraph_bundle_modal -y
drush cr
drush pb:regenerate-css
```

## Demo QA

1. Add a Modal paragraph; set button label, modal title, body/footer sections.
2. Set Display colors and optional animation class.
3. Open/close modal with keyboard (Escape, Tab trap) and backdrop click.
4. Confirm dynamic CSS selector:
   `#pb-modal-{id}-win .pb-modal__content > .pb-ui-inner`

## v2 notes

- Display colors use per-paragraph dynamic CSS, not inline `--pb-*`.
- v1 DOM ids preserved: wrapper `pb__modal-wrapper-{id}`, button
  `pb-modal-{id}`, dialog `pb-modal-{id}-win`.
- `PbDynamicCssBuilder` uses outer-id suffix `-win` for modal scope.
