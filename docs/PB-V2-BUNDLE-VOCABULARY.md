# PB v2 — per-bundle L3/L4 vocabulary (approved)

**Status:** Approved for implementation — apply during Phase B ports and
accordion
rename.  
**Rules:** [`PB-V2-CLASS-NAMING-SPEC.md`](PB-V2-CLASS-NAMING-SPEC.md)

L3 names are **descriptive roles** (what the node does in the UI). L4 names
match
DOM/ARIA where practical. All tokens are prefixed and scoped — safe on any
Drupal
page.

**Legend:** L3 = JS `once()` behavior host. `—` = layer not used. **Rename** =
change from current Twig during port.

---

## Interactive bundles

### Accordion (`pb-accordion`)

| Layer | Class | Role | Notes |
|-------|-------|------|-------|
| L0 | `paragraphs-bundles` `pb-accordion` | Paragraph root | add `pb-u-open-
first` etc. on L0 |
| L1 | `pb-ui-inner` | Display shell | |
| L2 | `pb-content__full` | Content region | |
| L3 | **`pb-accordion__sections`** | Section list host | JS `once()` target |
| L4 | `pb-accordion__item` | One expandable section | state: `pb-is-active` /
`pb-is-inactive` |
| L4 | `pb-accordion__button` | Section trigger | `role` implicit via `<button>`
|
| L4 | `pb-accordion__pane` | Section body | nested paragraphs allowed |
| L4 | `pb-accordion__toggle-all` | Expand/collapse all control | |
| L4 | `pb-grid__expand-button` | Toolbar wrapper | shared; state `pb-is-expand-
all` |

### Tabs (`pb-tabs`)

| Layer | Class | Role | Notes |
|-------|-------|------|-------|
| L0 | `paragraphs-bundles` `pb-tabs` | Paragraph root | add private class on
port |
| L1 | `pb-ui-inner` `pb-tabs__inner` | Display shell | |
| L2 | `pb-content__full` | Content region | |
| L3 | **`pb-tabs__tabset`** | Tab widget host | JS `once()` target |
| L4 | `pb-tabs__tablist` | Tab button row | `role="tablist"` |
| L4 | `pb-tabs__tab` | Single tab control | state: `pb-is-active` |
| L4 | `pb-tabs__panels` | Tab panel container | |
| L4 | `pb-tabs__panel` | One tab panel | `role="tabpanel"` |
| utility | `pb-u-vertical-tabs` / `pb-u-horizontal-tabs` | Layout on tabset |
replaces `v__tabs` / `h__tabs` |

### Carousel (`pb-carousel`)

| Layer | Class | Role | Notes |
|-------|-------|------|-------|
| L0 | `paragraphs-bundles` `pb-carousel` | Paragraph root | `pb-has-arrows` /
`pb-no-arrows` |
| L1 | `pb-ui-inner` `pb-carousel__track-inner` | Display shell | |
| L2 | `pb-content__full` | On `pb-carousel__viewport` | shell exception |
| L3 | **`pb-carousel__viewport`** | Carousel host | JS `once()` target |
| L4 | `pb-carousel__wrapper` | Inner layout wrapper | |
| L4 | `pb-carousel__slides` | Slide track (`role="group"`) | JS slide container
|
| L4 | `pb-carousel__slide` | One slide | state: `pb-is-active` / `pb-is-
inactive` |
| L4 | `pb-carousel__dots` | Dot navigation | |
| L4 | `pb-carousel__options-bar` | Arrow control bar | |
| L4 | `pb-nav__prev` `pb-nav__next` | Prev/next buttons | shared nav block |
| utility | `pb-u-horizontal-carousel` / `pb-u-vertical-carousel` / `pb-u-
hybrid-carousel` | Orientation on L0 | was `*-carousel` |
| state | `pb-is-visible` | Wrapper revealed by JS | was `is-visible` |

### Slideshow (`pb-slideshow`)

| Layer | Class | Role | Notes |
|-------|-------|------|-------|
| L0 | `paragraphs-bundles` `pb-slideshow` | Paragraph root | |
| L1 | `pb-ui-inner` `pb-slideshow__wrap-inner` | Display shell | |
| L2 | `pb-content__full` | Content region | |
| L3 | **`pb-slideshow__stage`** | Slideshow host | JS `once()` target |
| L4 | `pb-slideshow__slides` | Slide stack | |
| L4 | `pb-slideshow__slide` | One slide | state: `pb-is-active` (**not** bare
`active`) |
| L4 | `pb-slideshow__slide-inner` | Slide content wrapper | |
| L4 | `pb-slideshow__title` | Slide caption | |
| L4 | `pb-slideshow__nav` | Bottom controls / dots row | `role="group"` — it
also holds the play/pause control, so it is not a `tablist` |
| L4 | `pb-slideshow__nav-btn` | Nav dot/button | plain button, carries
`aria-controls` + `aria-current` |
| L4 | `pb-slideshow__arrows` | Prev/next row | |
| L4 | `pb-nav__play` `pb-nav__prev` `pb-nav__next` | Shared controls | state:
`pb-is-playing` / `pb-is-paused` |
| L4 | `pb-nav__play-icon` `pb-nav__pause-icon` | Play/pause SVG hosts | toggle
`hidden` in JS |
| utility | `pb-has-bottom-nav` | Root when bottom nav on | was `has-bottom-nav`
|
| utility | `pb-u-zero` / `pb-u-not-zero` | Autoplay interval flags on stage |
was `zero` / `not-zero` |
| utility | `pb-u-hide-0` | Hide play when interval 0 | was `hide-0` |
| utility | `pb-u-reduced-motion` | Slides container when PRM | was `reduced-
motion` |

### 3D carousel (`pb-3d-carousel`)

| Layer | Class | Role | Notes |
|-------|-------|------|-------|
| L0 | `paragraphs-bundles` `pb-3d-carousel` | Paragraph root | |
| L1 | `pb-ui-inner` `pb-3d-carousel__inner` | Display shell | stop using `pb-
carousel__inner` |
| L2 | `pb-content__full` | Merged on L1 today | optional cleanup |
| L3 | **`pb-3d-carousel__ring`** | 3D carousel host | **Rename** from bare
`pb-3d-carousel` on widget |
| L4 | `pb-3d-carousel__slides` | Cell container | stop borrowing `pb-
carousel__slides` |
| L4 | `pb-3d-carousel__slide` | One cell | state: `pb-is-active` |
| L4 | `pb-3d-carousel__options` | Control bar | |
| L4 | `pb-nav__prev` `pb-nav__next` `pb-nav__play` | Shared nav | |

Dynamic CSS ids use `pb-3d-carousel-{id}` (`OUTER_ID_PREFIX_OVERRIDES`). Ring
host id:
`pb-3d-carousel-ring-{id}` (Twig only; not dynamic CSS target).

### Modal (`pb-modal`)

| Layer | Class | Role | Notes |
|-------|-------|------|-------|
| L0 | `paragraphs-bundles` `pb-modal` | Paragraph root | shell exception |
| L3 | **`pb-modal__dialog`** | Dialog host | JS `once()` target (`pb-paragraph-
bundle-modal-dialog`) |
| L4 | `pb-modal__button` | Open trigger wrapper | |
| L4 | `pb-modal__content` | Dialog inner content shell | |
| L4 | `pb-modal__header` | Header row | |
| L4 | `pb-modal__title` | Title | |
| L4 | `pb-modal__body` | Body | nested paragraphs |
| L4 | `pb-modal__footer` | Footer | |
| state | `pb-is-open` | Dialog visible | on `pb-modal__dialog` |
| utility | `pb-u-modal-open` | `html` scroll lock | when any modal open |

### Alert (`pb-alert`)

| Layer | Class | Role | Notes |
|-------|-------|------|-------|
| L0 | `paragraphs-bundles` `pb-alert` | Paragraph root | `pb--has-close` / `pb
--no-close` |
| L2 | `pb-content__full` `pb-alert__content` | Content | no L3 — static +
dismiss JS |
| L4 | `pb-alert__countdown` | Countdown display | |
| L4 | `pb-alert__close` | Dismiss button | |
| state | **`pb-is-closed`** | Dismissed / collapsed | **Rename** from `alert-
is-closed` |

### Image grid (`pb-image-grid`)

| Layer | Class | Role | Notes |
|-------|-------|------|-------|
| L0 | `paragraphs-bundles` `pb-image-grid` | Grid paragraph root | |
| L0 | `paragraphs-bundles` `pb-image-grid-section` | Section paragraph root |
nested grid cells |
| L1 | `pb-ui-inner` | Display shell | |
| L2 | `pb-content__full` | Content region | |
| L3 | **`pb-image-grid__gallery`** | Grid + lightbox host | **Rename** from
`pb-image-grid__wrapper` (deferred) |
| L3 | `pb-image-grid__wrapper` | Grid + lightbox host | current name; scope
under L0 `pb-image-grid` |
| L4 | `pb-image-grid__title` | Item caption | section template |
| L4 | `pb-grid__lightbox` | Lightbox overlay | shared grid pattern |
| L4 | `pb-grid__lightbox-content` | Lightbox body | |
| L4 | `pb-grid__expand` `pb-grid__close` | Lightbox controls | |
| L4 | `pb-image__wrapper` `pb-image-grid` | Image cell | `pb-image-grid` on
cell is not L0 |

**Scoping contract:** grid CSS/JS use `.paragraphs-bundles.pb-image-grid`;
section cells use
`.paragraphs-bundles.pb-image-grid-section`; image cell overlay targets
`.paragraphs-bundles.pb-image-grid-section .pb-image__wrapper.pb-image-grid` so
L0 `pb-image-grid`
on the grid root is never styled as a shadow/display cell. Expand buttons get
`aria-controls` from JS (parent lightbox `id`); section Twig must not hardcode
it.

### Layout columns (`pb-one-column` / `pb-two-columns` / `pb-three-columns`)

| Layer | Class | Role | Notes |
|-------|-------|------|-------|
| L0 | `paragraphs-bundles` **`pb-layout`** `pb-{n}-columns` | Paragraph root |
**`pb-layout`** = private bundle slug for CSS/JS scoping; subtype stays `pb-one-
column` / `pb-two-columns` / `pb-three-columns` |
| L2 | `pb-content__full` | Content region | no L3 |
| L4 | `pb-one-column` / `pb-two-columns` / `pb-three-columns` | Column grid |
block = layout type |
| L4 | `pb-col-left` `pb-col-middle` `pb-col-right` | Column cells | |
| L4 | `pb-layout-col__toolbar` | Collapse toolbar | |
| L4 | `pb-layout-col__toggle` | Column toggle button | |
| L4 | `pb-layout-col__content` | Column inner | |
| state | **`pb-is-sticky`** **`pb-is-collapsed`** | Column state | **Rename**
from `is-sticky` / `is-collapsed` |

### Parallax (`pb-parallax`)

| Layer | Class | Role | Notes |
|-------|-------|------|-------|
| L0 | `paragraphs-bundles` `pb-parallax` | Parent paragraph | passthrough — no
L3 on parent |
| L0 | `paragraphs-bundles` `pb-parallax-section` | Section paragraph |
scroll/effect data attrs on root |
| L4 | `pb-parallax__bg` | Section background | |
| L4 | `pb-parallax__text` | Section text overlay | on section children |
| state | **`pb-is-visible`** | Scroll-reveal state | **Rename** from bare
`visible` |

**Scoping contract:** section CSS/JS use
`.paragraphs-bundles.pb-parallax-section`;
parent shell uses `.paragraphs-bundles.pb-parallax`. Dynamic CSS ids use
`pb-parallax-{id}`
for both `parallax_bundle` and `parallax_section_bundle`
(`OUTER_ID_PREFIX_OVERRIDES`).

---

## Static / CSS-only bundles

| Bundle | L0 private | L3 | Key L4 classes |
|--------|------------|-----|----------------|
| Grid | `pb-grid` | — | `pb-grid__wrapper` |
| Hero | `pb-hero` | — | `pb-hero__text-wrapper`, `hero-bundle-inner` (L1
exception) |
| Image / wide / narrow | `pb-image` etc. | — | `pb-image__wrapper`, `pb-ui-
link` |
| Image background | `pb-image-background` | — | bundle-specific wrapper classes
|
| Image overlay | `pb-image-overlay` | — | `pb-image__wrapper` |
| Icon | `pb-icon` | — | icon wrapper classes |
| Link | `pb-link`, `pb-link-section` | — | `pb-link__inner`, `pb-content__full`
|
| **pb_block** (custom block type) | `pb-ui-block` | — | paragraph field only;
no Display tab |
| **block_bundle** (Drupal Block paragraph) | — (Track A) | — | embeds block
plugin; parent `paragraph--default` |
| **block_content_bundle** (paragraph) | — (Track A) | — | embeds block content
entity; parent `paragraph--default` |
| **contact_form_bundle** | — (Track A) | — | embeds Contact form; parent
`paragraph--default` |
| **views_bundle** | — (Track A) | — | embeds Views reference; parent `paragraph
--default` |
| **webform_bundle** | — (Track A) | — | embeds Webform; parent `paragraph--
default` |
| 3D flip box | `pb-3d-flip-box` | **`pb-3d-flip-box__scene`** | `__inner`,
**`pb-3d-flip-box__front`**, **`pb-3d-flip-box__back`** (stop
`pb-3d-carousel__front/back`) |
| Card layouts | `pb-card-*` | — | `pb-card-three-columns__left` etc. |

**Scoping contract:** L0 is `pb-card` plus subtype (`pb-card-two-columns`,
`pb-card-three-columns`, `pb-card-image-section`, `pb-card-text-section`).
Inner column grids reuse subtype class names; CSS/JS scope with
`.paragraphs-bundles.pb-card.pb-card-two-columns .pb-card-two-columns`. JS
`pb-u-image-top` counts only image sections owned by the current grid (nested
card
layouts excluded).

**Not the same as shadow tokens:** `pb__card_2` and `pb__card_4` are frozen
`pb_display_shadow` display modifiers (global `pb__` prefix). They are unrelated
to
the card bundle `pb-card` L0 class — never merge them in CSS selectors.

| Node reference | per slug | — | `pb-content__full` |
| Default / simple | — | — | nested field output only |

**Track A batch (no custom Twig):** `block_bundle`, `block_content_bundle`,
`contact_form_bundle`, `views_bundle`, `webform_bundle` — all use parent
`paragraph--default.html.twig`; Phase B is satisfied by the parent template
(`pb-ui-inner`, dynamic CSS, `pb-content__full`). No bundle-private L0 class.

---

## Shared cross-bundle blocks

| Block | Classes | Used by |
|-------|---------|---------|
| `pb-ui-*` | `pb-ui-inner`, `pb-ui-row`, `pb-ui-bar`, `pb-ui-btn`, `pb-ui-link`
| most bundles |
| `pb-nav__*` | `pb-nav__prev`, `pb-nav__next`, `pb-nav__play`, `pb-
nav__announcer`, `pb-nav__play-icon`, `pb-nav__pause-icon` | carousel,
slideshow, 3d |
| `pb-icon-toggle` | `pb-icon-toggle__plus`, `pb-icon-toggle__minus` | accordion
|
| `pb-content__full` | content wrapper | most bundles |
| `pb-image__wrapper` | image cell | image, card, grid sections |

---

## Implementation order

1. ~~**Accordion**~~ — `pb-accordion__sections` done.
2. ~~**Tabs**~~ — `pb-tabs__tabset` + L4 renames done.
3. ~~**Slideshow**~~ — `pb-slideshow__stage` + `pb-is-active` done.
4. ~~**Carousel**~~ — `pb-carousel__viewport` + slide states done.
5. ~~**Modal**~~ — `pb-modal__dialog` + L0 `pb-modal` done.
6. **Parallax** — fix bare `visible` → `pb-is-visible`.

---

## Rename maps (synced)

The maps below are the shipped record. The generator and its rule file are
maintainer tooling and are not part of the release package; the complete
old → new mapping for site builders is in
[`CSS-CLASS-MIGRATION.md`](CSS-CLASS-MIGRATION.md).

### Tabs L4 (Phase A intermediate → approved)

| Old | Approved |
|-----|----------|
| `pb-tabs__buttons` | `pb-tabs__tablist` |
| `pb-tabs__tab-button` | `pb-tabs__tab` |
| `pb-tabs__panes` | `pb-tabs__panels` |
| `pb-tabs__tab-pane` | `pb-tabs__panel` |
| (widget host in Twig) | `pb-tabs__tabset` |

### Slideshow L3

| Old | Approved |
|-----|----------|
| `pb-slideshow__inner` | `pb-slideshow__stage` |

### Accordion L3

| Old | Approved |
|-----|----------|
| `pb-accordion__root` (provisional) | `pb-accordion__sections` |

### Carousel L3 + states

| Old | Approved |
|-----|----------|
| `pb-carousel__outer` | `pb-carousel__viewport` |
| `horizontal-carousel` / `vertical-carousel` / `hybrid-carousel` |
`pb-u-*-carousel` on L0 |
| `active-slide` / `non-active-slide` | `pb-is-active` / `pb-is-inactive` |
| `is-visible` | `pb-is-visible` |

### Modal L3 + shell

| Old | Approved |
|-----|----------|
| `pb-modal` on dialog element | `pb-modal__dialog` |
| `pb-modal__wrapper` on L0 root | `pb-modal` (L0 private; id `pb-
modal__wrapper-{id}` unchanged) |
| `pbcss-modal` on `html`/`body` | `pb-u-modal-open` on `html` |
