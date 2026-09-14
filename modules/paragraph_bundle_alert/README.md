# Paragraph Bundle Alert

`alert_bundle` paragraph type with dismissible alerts, optional countdown,
date-range visibility, close-button persistence, and nested body paragraphs.

## Alert state

**Alert state** (Display → Colors) adds a state icon and a coloured left bar,
following the U.S. Web Design System's four alert types:

| State   | Meaning                        | Announced as |
|---------|--------------------------------|--------------|
| Success | Resolved, complete, all clear  | polite       |
| Info    | Advisory, informational        | polite       |
| Warning | Caution, watch                 | assertive    |
| Error   | Emergency, action required     | assertive    |

Leave it as *- None -* for an unstyled alert; that is the default and nothing
about existing alerts changes until a state is chosen.

**Choosing a state fills in the background colour for you.** It only does this
when the colour field is still empty, or still holds another state's colour -
a colour you typed is never overwritten. Change it afterwards to anything you
like; the state keeps its icon and bar either way, so the meaning survives a
custom background.

The background is stored in the ordinary `pb_display_bg` field and rendered
through the same per-paragraph dynamic CSS as every other bundle, so a
state-coloured alert and a hand-coloured one produce identical output.

### Accessibility

- The state is also given as text for screen readers ("Warning: ...") because
  colour and an icon alone would carry the meaning.
- Success and Info announce politely (`role="status"`); Warning and Error
  interrupt (`role="alert"`), which is what an emergency warrants and what a
  confirmation does not.
- Icon and bar colours are measured against their own background: the lowest
  is 4.84:1, above the 3:1 WCAG 1.4.11 asks of a graphical object.
- Windows High Contrast and print keep the icon and bar, which is all that
  remains when tinted backgrounds are discarded.

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
  `#pb-alert-{id} > .pb-ui-inner`, not inline `--pb-*` styles.
- List display fields read stored keys via `paragraph.field.value` in Twig.
- Dismissal, date range, cache max-age and alert state live in
  `PbaPreprocessAlertHook`.
- Alert state is a presentation preset only: it seeds `pb_display_bg` and adds
  a class. It never writes to, or styles, a paragraph nested inside the alert.
- Existing sites get the field from `paragraph_bundle_alert_update_9001()`;
  fresh installs get it from `config/optional`.
