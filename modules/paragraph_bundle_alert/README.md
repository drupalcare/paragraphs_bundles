# Paragraph Bundle Alert

`alert_bundle` paragraph type with dismissible alerts, optional countdown,
date-range visibility, close-button persistence, and nested body paragraphs.

## Dependencies

- `paragraphs_bundles` (parent)
- Core `datetime`, `options`, `text`
- `paragraphs`, `entity_reference_revisions` (nested alert body)

## Enable

```bash
drush en paragraph_bundle_alert -y
drush cr
drush pb:regenerate-css
```

## v2 notes

- Display colors use per-paragraph dynamic CSS (`pb_dynamic_library`) on
  `#pb__alert-{id} > .paragraph__inner`, not inline `--pb-*` styles.
- List display fields read stored keys via `paragraph.field.value` in Twig.
- Dismissal, date range, and cache max-age logic live in
  `PbaPreprocessAlertHook`.
