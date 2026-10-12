# PB v2: Accessibility playbook (WCAG / ARIA)

> **Authority:** [WCAG 2.2](https://www.w3.org/TR/WCAG22/) and the
> [ARIA Authoring Practices Guide](https://www.w3.org/WAI/ARIA/apg/)  
> **Per-bundle checklists:** `modules/paragraph_bundle_*/ACCESSIBILITY.md`

Every paragraph bundle sub-module ships an **`ACCESSIBILITY.md`** at its root.
That file is the
bundle-specific WCAG / ARIA contract. This document is the shared reference for
patterns,
Drupal APIs, and verification.

---

## Global rules (all bundles)

| Rule | Requirement |
|------|-------------|
| L0 scope | Interactive CSS/JS scopes to `.paragraphs-bundles.pb-{slug}` —
never bare `.paragraph` |
| Focus | Visible focus styles; logical tab order; return focus after
dismissible widgets |
| Names | Every control has an accessible name (`aria-label`, `aria-labelledby`,
or visible text) |
| Live regions | Meaningful open/close or error states use
`Drupal.announce(message, 'polite'\|'assertive')` |
| Motion | Respect `prefers-reduced-motion`; do not require animation to operate
|
| Color | WCAG 2.2 AA contrast; do not convey information by color alone |
| Detach | `once.remove` + `AbortController` on `detach(..., 'unload')`, no
leaked traps or listeners |
| HTML in Twig | Translatable strings via `\|t`; decorative glyphs `aria-
hidden="true"` |

---

## Pattern reference

### Dialog / modal (`pb-modal`, image grid lightbox)

**Reference implementation:** `paragraph_bundle_modal` (✅ implemented)

| Concern | Requirement |
|---------|-------------|
| Markup | `role="dialog"` on dialog host; `tabindex="-1"` for focus fallback |
| Name | `aria-labelledby` when title exists; else `aria-label` |
| Description | `aria-describedby` when body content exists |
| Open | `aria-modal="true"`; remove `aria-hidden` |
| Closed | `aria-hidden="true"`; remove `aria-modal` |
| Trigger | `aria-expanded`, `aria-controls` on open button |
| Close | Named close button (`aria-label`); not icon-only without a name |
| Focus | Move to first focusable or dialog; trap Tab; Escape closes |
| Background | `inert` siblings (sibling-inert walk + body children not
containing dialog) |
| Announce | `Drupal.announce()` on open (with title) and close |
| Scroll | `html.pb-u-modal-open { overflow: hidden }` |

### Tabs (`pb-tabs`)

| Concern | Requirement |
|---------|-------------|
| Roles | `tablist` → `tab` + `tabpanel` |
| State | `aria-selected`, `aria-controls`, panel `aria-labelledby` |
| Keyboard | Arrow keys between tabs; Tab into panel content |

### Accordion / layout disclosure (`pb-accordion`, `pb-layout`)

| Concern | Requirement |
|---------|-------------|
| Trigger | `<button type="button">`, not `<div onclick>` |
| State | `aria-expanded`, `aria-controls` |
| Panel | `id` on panel matches `aria-controls` |

### Carousel / slideshow (`pb-carousel`, `pb-slideshow`, `pb-3d-carousel`)

| Concern | Requirement |
|---------|-------------|
| Region | Labeled carousel/slideshow region |
| Controls | Prev/next, play/pause when autoplay exists |
| Inactive slides | `inert` or removed from tab order |
| Motion | Pause or disable autoplay under `prefers-reduced-motion` |

### Alert (`pb-alert`)

| Concern | Requirement |
|---------|-------------|
| Live | `role="alert"` or polite live region for dynamic alerts |
| Dismiss | Keyboard-dismissible with named control |

### Static / content bundles (hero, image, link, block, …)

| Concern | Requirement |
|---------|-------------|
| Images | Meaningful `alt`; decorative `alt=""` |
| Headings | Logical `h1`–`h6` order |
| Links | Descriptive link text |
| Forms | Delegate to Webform / Contact module patterns |

---

## JavaScript checklist (interactive bundles)

1. `once()` on the **component host** (dialog, tabset, carousel stage, not
   always L0 root).
2. `AbortController` per instance; abort session listeners on close.
3. Focus trap filters **visible** focusables only (`hidden`, `aria-hidden`,
   `display:none`).
4. If zero focusables, focus the widget container (`tabindex="-1"`).
5. If Tab moves outside the widget while open, pull focus back inside.
6. `Drupal.announce()` for state users cannot see.
7. No `innerHTML` for UI chrome; no `window.pb.*` globals.

---

## Manual QA template

Use for every interactive bundle before marking **implemented** in the registry:

1. Keyboard-only: open, operate, close.
2. Screen reader: name, role, state on open and after each action.
3. Tab trap: cannot reach background while modal/dialog open.
4. Escape closes (when pattern requires it).
5. Focus returns to trigger after close.
6. Ajax: re-render paragraph: behavior still works once; no duplicate traps.
7. Zoom 200%: controls remain usable.
8. Forced-colors / high-contrast: focus ring visible.

---

## Status

Each bundle's own `ACCESSIBILITY.md` carries its status in the header block —
`pending`, `implemented` or `n/a`, alongside the private class it owns. Those
files are the record; read the bundle's file for its current state.

| Status | Meaning |
|--------|---------|
| `implemented` | Meets this playbook for the bundle's pattern; QA done |
| `pending` | Not yet verified or incomplete |
| `n/a` | No user-facing interaction |

---

## Changelog

| Date | Change |
|------|--------|
| 2026-06-04 | Initial playbook; modal reference implementation; per-bundle
ACCESSIBILITY.md generator |
