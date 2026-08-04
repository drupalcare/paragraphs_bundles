# CSS class migration — paragraphs_bundles v1 → v2

**Ship with v2.** This is the authoritative reference for every
**structural** CSS class rename between v1 contrib and v2. Sub-themes
and custom modules that target PB class names must update overrides
using the tables below.

The rename generator and its rule file are maintainer tooling and are not part
of the release package; these tables are the shipped record.

---

## Before you start

1. **Search your theme first.** Most sites reference only a handful of PB
   classes. From your theme or custom module directory:

   ```bash
   grep -rnE 'pb__|paragraph__inner' --include='*.css' --include='*.twig' \
     --include='*.js' .
   ```

   Every hit is either in the rename table below, or in the **frozen** list —
   frozen tokens need no change.

2. **Renames are one-way but non-destructive.** They affect only your own
   overrides. No stored content or field value changes, so a missed rename
   costs you a style that stops applying, never data.

3. **Work from the tables, not from a global find-and-replace.** Several v1
   names are prefixes of others (`pb__card` vs `pb__card-image`), so an
   unanchored replace will corrupt the longer name. If you do script it, sort
   the pairs longest-first and match on a word boundary.

---

## Summary

| | Count |
|---|------:|
| Structural class renames (v1 → v2) | **185** |
| Manual-only renames (below) | **119** |
| Frozen tokens (unchanged) | **69** |

That is 304 rows and **299 distinct renames** — a few names appear in both a
per-bundle table and a thematic one.

Every v2 name in the tables below was checked against the shipped source on
2026-08-04: **all 299 exist**. Every frozen token was checked the same way, and
the ones that turned out to be renamed classes rather than stored values were
moved into the rename tables where they belong.

### What changed

- **Structural** Twig/CSS/JS hooks: legacy `pb__*` abbreviations and
  `paragraph__inner` → `pb-*` BEM (`pb-ui-inner`, `pb-accordion`, …).
- **Phase B ports:** intermediate Phase A names and bare legacy tokens
  → approved vocabulary (`pb-tabs__tablist`, `pb-slideshow__stage`,
  `pb-is-active`, …). See **Phase B** section below.
- **Dynamic CSS:** inline `style="--pb-*"` on the inner wrapper →
  per-paragraph CSS file + `attach_library` (same `--pb-*` variable names).

### What did **not** change

| Item | v1 | v2 |
|------|----|----|
| Field machine names | `pb_content_*`, `pb_display_*` | **Same** |
| CSS custom properties | `--pb-bg`, `--pb-tx`, … | **Same** |
| Outer element ids | `#pb__{slug}-{id}` | **Same** |
| Display list field classes | `pb__pmw_500`, `pb__padding_16`, … | **Same**
(frozen) |
| Breakpoint classes | `pb__576` … `pb__1400` | **Same** (frozen) |
| Admin form / widget CSS | `pb__tab-display`, color picker, … | **Same**
(frozen) |
| Two inner wrappers | `pb__simple__inner`, `pb__default__inner` | **Same** — see note below |

`pb__simple__inner` (simple bundle) and `pb__default__inner` (default template)
are the only two `pb__` names that are ordinary **classes** on the rendered
page. They carry no stored-value, config or asset-library coupling — unlike
every other row here — and were left unrenamed during the BEM pass. If you
override either, expect them to move to `pb-` in a future major.

Frozen tokens are output verbatim from Display tab field values. See
[Display field classes (unchanged)](#display-field-classes-unchanged) below.

---

## Structural renames (v1 → v2)

Apply these in sub-theme CSS, Twig, and JS when overriding PB markup.

### Shared UI

| v1 class | v2 class |
|----------|----------|
| `paragraph__inner` | `pb-ui-inner` |
| `pb-block` | `pb-ui-block` |
| `pb__bar` | `pb-ui-bar` |
| `pb__button` | `pb-ui-btn` |
| `pb__button-title` | `pb-ui-btn__title` |
| `pb__link` | `pb-ui-link` |
| `pb__row` | `pb-ui-row` |

### State

| v1 class | v2 class |
|----------|----------|
| `pb__active` | `pb-is-active` |
| `pb__active-no` | `pb-is-inactive` |

### Flags

| v1 class | v2 class |
|----------|----------|
| `pb__no-bottom-nav` | `pb-no-bottom-nav` |

### Utilities

| v1 class | v2 class |
|----------|----------|
| `h__tabs` | `pb-u-horizontal-tabs` |
| `pb--open-first` | `pb-u-open-first` |
| `pb-image-top` | `pb-u-image-top` |
| `pb__dis-radius` | `pb-u-dis-radius` |
| `pb__dis-top-left-right` | `pb-u-dis-top-left-right` |
| `pb__display-container` | `pb-u-display-container` |
| `pb__image-full` | `pb-u-image-full` |
| `pb__img-auto` | `pb-u-img-auto` |
| `pb__large-screen` | `pb-u-large-screen` |
| `pb__prev-next-arrow-Off` | `pb-u-prev-next-arrow-off` |
| `pb__prev-next-arrow-On` | `pb-u-prev-next-arrow-on` |
| `pb__small-screen` | `pb-u-small-screen` |
| `v__tabs` | `pb-u-vertical-tabs` |

### Accordion

| v1 class | v2 class |
|----------|----------|
| `pb__accor` | `pb-accordion` |
| `pb__accor-button` | `pb-accordion__button` |
| `pb__accor-pane` | `pb-accordion__pane` |
| `pb__accor-wrap-btn-item` | `pb-accordion__item` |
| `pb__accor-wrap__inner` | `pb-accordion-wrap__inner` |

### Alert

| v1 class | v2 class |
|----------|----------|
| `pb__alert-countdown` | `pb-alert__countdown` |
| `pb__alert-countdown-unit` | `pb-alert__countdown-unit` |
| `pb__alert-top` | `pb-alert--top` |
| `pb__alert__inner` | `pb-ui-inner` |

### Card

| v1 class | v2 class |
|----------|----------|
| `pb-card-three-left` | `pb-card-three-columns__left` |
| `pb-card-three-middle` | `pb-card-three-columns__middle` |
| `pb-card-three-right` | `pb-card-three-columns__right` |
| `pb-card-two-left` | `pb-card-two-columns__left` |
| `pb-card-two-right` | `pb-card-two-columns__right` |
| `pb__card` | `pb-card` |
| `pb__card-image` | `pb-card__image` |
| `pb__card-image-section__inner` | `pb-card-image-section__inner` |
| `pb__card-image-title` | `pb-card__image-title` |
| `pb__card-three-columns` | `pb-card-three-columns` |
| `pb__card-three-columns__inner` | `pb-card-three-columns__inner` |
| `pb__card-two-columns` | `pb-card-two-columns` |
| `pb__card-two-columns__inner` | `pb-card-two-columns__inner` |

### Carousel

| v1 class | v2 class |
|----------|----------|
| `pb__caro-item` | `pb-carousel__slide` |
| `pb__caro-options` | `pb-carousel__options-bar` |
| `pb__caro-options-inner` | `pb-3d-carousel__options-inner` (3D carousel) |
| `pb__caro-wrap__inner` | `pb-carousel__track-inner` |
| `pb__carousel-announcer` | `pb-carousel__announcer` |
| `pb__carousel-dot` | `pb-carousel__dot` |
| `pb__carousel-dots` | `pb-carousel__dots` |
| `pb__carousel-item` | `pb-carousel__slide` |
| `pb__carousel-items` | `pb-carousel__slides` |
| `pb__carousel-options` | `pb-carousel__options-bar` |
| `pb__carousel-outer` | `pb-carousel__viewport` |
| `pb__carousel-wrapper` | `pb-carousel__wrapper` |
| `pb__carousel__inner` | `pb-carousel__track-inner` |

### 3D carousel

| v1 class | v2 class |
|----------|----------|
| `pb__3d-back` | `pb-3d-flip-box__back` (3D flip box) |
| `pb__3d-carousel` | `pb-3d-carousel` |
| `pb__3d-caro-100` | `pb-3d-carousel--h100` |
| `pb__3d-caro-150` | `pb-3d-carousel--h150` |
| `pb__3d-caro-200` | `pb-3d-carousel--h200` |
| `pb__3d-caro-250` | `pb-3d-carousel--h250` |
| `pb__3d-caro-300` | `pb-3d-carousel--h300` |
| `pb__3d-caro-350` | `pb-3d-carousel--h350` |
| `pb__3d-caro-400` | `pb-3d-carousel--h400` |
| `pb__3d-caro-450` | `pb-3d-carousel--h450` |
| `pb__3d-caro-500` | `pb-3d-carousel--h500` |
| `pb__3d-caro-550` | `pb-3d-carousel--h550` |
| `pb__3d-caro-s-100` | `pb-3d-carousel--sh100` |
| `pb__3d-caro-s-150` | `pb-3d-carousel--sh150` |
| `pb__3d-caro-s-200` | `pb-3d-carousel--sh200` |
| `pb__3d-caro-s-250` | `pb-3d-carousel--sh250` |
| `pb__3d-caro-s-300` | `pb-3d-carousel--sh300` |
| `pb__3d-caro-s-350` | `pb-3d-carousel--sh350` |
| `pb__3d-caro-s-400` | `pb-3d-carousel--sh400` |
| `pb__3d-caro-s-450` | `pb-3d-carousel--sh450` |
| `pb__3d-caro-s-500` | `pb-3d-carousel--sh500` |
| `pb__3d-flip-box__inner` | `pb-ui-inner` |
| `pb__3d-front` | `pb-3d-flip-box__front` (3D flip box) |

### 3D flip box

| v1 class | v2 class |
|----------|----------|
| `pb__flip-box` | `pb-3d-flip-box` |
| `pb__flip-box-inner` | `pb-3d-flip-box__inner` |

### Grid

| v1 class | v2 class |
|----------|----------|
| `pb__grid-lightbox` | `pb-grid__lightbox` |
| `pb__grid-lightbox-content` | `pb-grid__lightbox-content` |
| `pb__grid-wrapper` | `pb-grid__wrapper` |
| `pb__grid__inner` | `pb-grid__inner` |

### Hero

| v1 class | v2 class |
|----------|----------|
| `pb__hero-txt-wrapper` | `pb-hero__text-wrapper` |
| `pb__hero-txt-wrapper-inner` | `pb-hero__text-inner` |

### Icon

| v1 class | v2 class |
|----------|----------|
| `pb__icon-header` | `pb-icon__header` |
| `pb__icon__inner` | `pb-icon__inner` |

### Image

| v1 class | v2 class |
|----------|----------|
| `pb__image-bg__inner` | `pb-image-background__inner` |
| `pb__image-grid` | `pb-image-grid` |
| `pb__image-grid-section__inner` | `pb-image-grid-section__inner` |
| `pb__image-grid-title` | `pb-image-grid__title` |
| `pb__image-grid-wrapper` | `pb-image-grid__wrapper` |
| `pb__image-grid__inner` | `pb-image-grid__inner` |
| `pb__image-n__inner` | `pb-image-narrow__inner` |
| `pb__image-overlay__inner` | `pb-image-overlay__inner` |
| `pb__image-w__inner` | `pb-image-wide__inner` |
| `pb__image__inner` | `pb-image__inner` |
| `pb__img-wrapper` | `pb-image__wrapper` |

### Image overlay

| v1 class | v2 class |
|----------|----------|
| `pb__overlay-container` | `pb-overlay__container` |
| `pb__overlay-title` | `pb-overlay__title` |

### Layout columns

| v1 class | v2 class |
|----------|----------|
| `pb-col-content` | `pb-layout-col__content` |
| `pb-col-icon-angle` | `pb-layout-col__icon--angle` |
| `pb-col-icon-caret` | `pb-layout-col__icon--caret` |
| `pb-col-icon-plus_minus` | `pb-layout-col__icon--plus-minus` |
| `pb-col-icon-pos-edge` | `pb-layout-col__icon-pos--edge` |
| `pb-col-icon-pos-edge_top` | `pb-layout-col__icon-pos--edge-top` |
| `pb-col-icon-pos-inside_center` | `pb-layout-col__icon-pos--inside-center` |
| `pb-col-toggle` | `pb-layout-col__toggle` |
| `pb-col-toggle__icon--angle` | `pb-layout-col__toggle-icon--angle` |
| `pb-col-toggle__icon--caret` | `pb-layout-col__toggle-icon--caret` |
| `pb-col-toggle__state` | `pb-layout-col__toggle-state` |
| `pb-col-toggle__state--collapsed` | `pb-layout-col__toggle-state--collapsed` |
| `pb-col-toggle__state--expanded` | `pb-layout-col__toggle-state--expanded` |
| `pb-col-toggle__svg` | `pb-layout-col__toggle-svg` |
| `pb-col-toolbar` | `pb-layout-col__toolbar` |
| `pb__one-column` | `pb-one-column` |
| `pb__one-column__inner` | `pb-one-column__inner` |
| `pb__three-columns` | `pb-three-columns` |
| `pb__three-columns__inner` | `pb-three-columns__inner` |
| `pb__two-columns` | `pb-two-columns` |
| `pb__two-columns__inner` | `pb-two-columns__inner` |

### Modal

| v1 class | v2 class |
|----------|----------|
| `pb-modal-body` | `pb-modal__body` |
| `pb-modal-button` | `pb-modal__button` |
| `pb-modal-content` | `pb-modal__content` |
| `pb-modal-footer` | `pb-modal__footer` |
| `pb-modal-header` | `pb-modal__header` |
| `pb-modal-title` | `pb-modal__title` |
| `pb__modal-next` | `pb-modal__next` |
| `pb__modal-prev` | `pb-modal__prev` |
| `pb__modal-wrapper` | `pb-modal__wrapper-{id}` — now an **id**, not a class |
| `pb__modal__inner` | `pb-modal__inner` |

### Parallax

| v1 class | v2 class |
|----------|----------|
| `pb__parallax-bg` | `pb-parallax__bg` |
| `pb__parallax-bundle__inner` | `pb-parallax__inner` |
| `pb__parallax-section__inner` | `pb-parallax-section__inner` |
| `pb__parallax-text` | `pb-parallax__text` |

### Slideshow

| v1 class | v2 class |
|----------|----------|
| `pb__slide-arrows` | `pb-slideshow__arrows` |
| `pb__slide-bottom-btn` | `pb-slideshow__nav-btn` |
| `pb__slide-item` | `pb-slideshow__slide` |
| `pb__slide-item-inner` | `pb-slideshow__slide-inner` |
| `pb__slide-nav` | `pb-slideshow__nav` |
| `pb__slide-number` | `pb-slideshow__number` |
| `pb__slide-title` | `pb-slideshow__title` |
| `pb__slide-wrap__inner` | `pb-slideshow__wrap-inner` |
| `pb__slides` | `pb-slideshow__slides` |
| `pb__slideshow-inner` | `pb-slideshow__stage` |

### Tabs

| v1 class | v2 class |
|----------|----------|
| `pb__tab-button` | `pb-tabs__tab` |
| `pb__tab-pane` | `pb-tabs__panel` |
| `pb__tabs-bundle` | `pb-tabs` |
| `pb__tabs-button` | `pb-tabs__tablist` |
| `pb__tabs-pane` | `pb-tabs__panels` |

### Phase B (intermediate → approved)

| v1 class | v2 class |
|----------|----------|
| `pb-accordion__root` | `pb-accordion__sections` |
| `pb-carousel__outer` | `pb-carousel__viewport` |
| `pb-slideshow__inner` | `pb-slideshow__stage` |
| `pb-tabs__buttons` | `pb-tabs__tablist` |
| `pb-tabs__panes` | `pb-tabs__panels` |
| `pb-tabs__tab-button` | `pb-tabs__tab` |
| `pb-tabs__tab-pane` | `pb-tabs__panel` |

### Other

| v1 class | v2 class |
|----------|----------|
| `pb__announcer` | `pb-nav__announcer` |
| `pb__close-alert` | `pb-alert__close` |
| `pb__close-grid` | `pb-grid__close` |
| `pb__content-alert` | `pb-alert__content` |
| `pb__content-full` | `pb-content__full` |
| `pb__content-title` | `pb-content__title` |
| `pb__content-title--align-center` | `pb-content__title--align-center` |
| `pb__content-title--align-left` | `pb-content__title--align-left` |
| `pb__content-title--align-right` | `pb-content__title--align-right` |
| `pb__ex-button` | `pb-grid__expand-button` |
| `pb__expand-grid` | `pb-grid__expand` |
| `pb__minus` | `pb-icon-toggle__minus` |
| `pb__minus-svg` | `pb-icon-toggle__minus-svg` |
| `pb__multi-column` | `pb-layout__multi-column` |
| `pb__next` | `pb-nav__next` |
| `pb__nf__inner` | `pb-node-reference__inner` |
| `pb__pause` | `pb-nav__pause` |
| `pb__play` | `pb-nav__play` |
| `pb__plus` | `pb-icon-toggle__plus` |
| `pb__plus-minus` | `pb-icon-toggle` |
| `pb__plus-svg` | `pb-icon-toggle__plus-svg` |
| `pb__prev` | `pb-nav__prev` |
| `pb__ps-value` | `pb-slideshow__progress-value` |
| `pb__toggle-all` | `pb-accordion__toggle-all` |

---

## Manual-only renames (not in bulk-apply script)

Update sub-theme / custom CSS overrides **by hand** for these — do NOT
run them through `migrate-class-names.sh`. Two reasons:

- **State / flags / utilities** tokens collide with `:active`,
  `prefers-reduced-motion`, or already-migrated `pb-is-*` / `pb-u-*`.
- **Value-driven + bundle-constructed classes** derive from **stored
  field values**. v1 rendered the stored value *as* the class; v2 keeps
  the stored value byte-for-byte and renders a modern class via the
  `pb_class` filter. So the **stored value never changes** (never touch
  content or config `allowed_values`) — only your CSS selector does. The
  bulk script also rewrites `.yml`, which would corrupt those stored
  values, so these are manual-only.

### State (manual)

| v1 / legacy class | v2 class |
|-------------------|----------|
| `active` | `pb-is-active` |
| `active-slide` | `pb-is-active` |
| `alert-is-closed` | `pb-is-closed` |
| `is-collapsed` | `pb-is-collapsed` |
| `is-paused` | `pb-is-paused` |
| `is-playing` | `pb-is-playing` |
| `is-sticky` | `pb-is-sticky` |
| `is-visible` | `pb-is-visible` |
| `non-active-slide` | `pb-is-inactive` |
| `visible` | `pb-is-visible` |

### Flags (manual)

| v1 / legacy class | v2 class |
|-------------------|----------|
| `has-bottom-nav` | `pb-has-bottom-nav` |

### Utilities (manual)

| v1 / legacy class | v2 class |
|-------------------|----------|
| `hide-0` | `pb-u-hide-0` |
| `horizontal-carousel` | `pb-u-horizontal-carousel` |
| `hybrid-carousel` | `pb-u-hybrid-carousel` |
| `not-zero` | `pb-u-not-zero` |
| `reduced-motion` | `pb-u-reduced-motion` |
| `vertical-carousel` | `pb-u-vertical-carousel` |
| `zero` | `pb-u-zero` |

### Value-driven utility classes (pb-u-*) (manual)

| v1 / legacy class | v2 class |
|-------------------|----------|
| `pb__animate_bottom` | `pb-u-animate--bottom` |
| `pb__animate_left` | `pb-u-animate--left` |
| `pb__animate_opacity` | `pb-u-animate--opacity` |
| `pb__animate_right` | `pb-u-animate--right` |
| `pb__animate_top` | `pb-u-animate--top` |
| `pb__animate_zoom` | `pb-u-animate--zoom` |
| `pb__border` | `pb-u-border` |
| `pb__border_3px` | `pb-u-border--3px` |
| `pb__border_6px` | `pb-u-border--6px` |
| `pb__border_bottom` | `pb-u-border--bottom` |
| `pb__border_left` | `pb-u-border--left` |
| `pb__border_right` | `pb-u-border--right` |
| `pb__border_top` | `pb-u-border--top` |
| `pb__bottom_bar` | `pb-u-border--bottom-bar` |
| `pb__card_2` | `pb-u-shadow--2` |
| `pb__card_4` | `pb-u-shadow--4` |
| `pb__circle` | `pb-u-round--circle` |
| `pb__ds_bottom_left` | `pb-u-ds--bottom-left` |
| `pb__ds_bottom_middle` | `pb-u-ds--bottom-middle` |
| `pb__ds_bottom_right` | `pb-u-ds--bottom-right` |
| `pb__ds_left` | `pb-u-ds--left` |
| `pb__ds_middle` | `pb-u-ds--middle` |
| `pb__ds_right` | `pb-u-ds--right` |
| `pb__ds_top_left` | `pb-u-ds--top-left` |
| `pb__ds_top_middle` | `pb-u-ds--top-middle` |
| `pb__ds_top_right` | `pb-u-ds--top-right` |
| `pb__left_bar` | `pb-u-border--left-bar` |
| `pb__margin` | `pb-u-margin` |
| `pb__margin_bottom` | `pb-u-margin--bottom` |
| `pb__margin_left` | `pb-u-margin--left` |
| `pb__margin_right` | `pb-u-margin--right` |
| `pb__margin_top` | `pb-u-margin--top` |
| `pb__padding` | `pb-u-padding` |
| `pb__padding_16` | `pb-u-padding--16` |
| `pb__padding_24` | `pb-u-padding--24` |
| `pb__padding_32` | `pb-u-padding--32` |
| `pb__padding_48` | `pb-u-padding--48` |
| `pb__padding_64` | `pb-u-padding--64` |
| `pb__padding_large` | `pb-u-padding--large` |
| `pb__padding_small` | `pb-u-padding--small` |
| `pb__pmw_100` | `pb-u-mw--100` |
| `pb__pmw_300` | `pb-u-mw--300` |
| `pb__pmw_400` | `pb-u-mw--400` |
| `pb__pmw_500` | `pb-u-mw--500` |
| `pb__pmw_600` | `pb-u-mw--600` |
| `pb__pmw_700` | `pb-u-mw--700` |
| `pb__pmw_800` | `pb-u-mw--800` |
| `pb__pmw_900` | `pb-u-mw--900` |
| `pb__right_bar` | `pb-u-border--right-bar` |
| `pb__round_large` | `pb-u-round--large` |
| `pb__round_medium` | `pb-u-round--medium` |
| `pb__round_small` | `pb-u-round--small` |
| `pb__round_xlarge` | `pb-u-round--xlarge` |
| `pb__round_xxlarge` | `pb-u-round--xxlarge` |
| `pb__section` | `pb-u-margin--section` |
| `pb__top_bar` | `pb-u-border--top-bar` |

### Bundle constructed classes (v2 BEM) (manual)

| v1 / legacy class | v2 class |
|-------------------|----------|
| `blur-effect` | `pb-parallax--blur` |
| `br-1200` | `pb-parallax--br-1200` |
| `br-1400` | `pb-parallax--br-1400` |
| `br-576` | `pb-parallax--br-576` |
| `br-768` | `pb-parallax--br-768` |
| `br-992` | `pb-parallax--br-992` |
| `br-all` | `pb-parallax--br-all` |
| `combined-effect` | `pb-parallax--combined` |
| `dot` | `pb-slideshow__nav--dot` |
| `dot_play` | `pb-slideshow__nav--dot-play` |
| `fade-effect` | `pb-parallax--fade` |
| `fade-scale-effect` | `pb-parallax--fade-scale` |
| `flip_x` | `pb-3d-flip-box--flip-x` |
| `flip_y` | `pb-3d-flip-box--flip-y` |
| `glow-effect` | `pb-parallax--glow` |
| `hover-effect` | `pb-parallax--hover` |
| `nav` | `pb-slideshow__nav--nav` |
| `nav_play` | `pb-slideshow__nav--nav-play` |
| `opacity-effect` | `pb-parallax--opacity` |
| `pb__image-overlay-fade` | `pb-image-overlay--fade` |
| `pb__image-overlay-slide_bottom` | `pb-image-overlay--slide-bottom` |
| `pb__image-overlay-slide_left` | `pb-image-overlay--slide-left` |
| `pb__image-overlay-slide_right` | `pb-image-overlay--slide-right` |
| `pb__image-overlay-slide_top` | `pb-image-overlay--slide-top` |
| `pb__image-overlay-title` | `pb-image-overlay--title` |
| `pb__image-overlay-zoom` | `pb-image-overlay--zoom` |
| `play` | `pb-slideshow__nav--dot-play` / `pb-slideshow__nav--nav-play` |
| `rotate-effect` | `pb-parallax--rotate` |
| `scale-effect` | `pb-parallax--scale` |
| `shadow-effect` | `pb-parallax--shadow` |
| `smooth-effect` | `pb-parallax--smooth` |
| `three-d-effect` | `pb-parallax--three-d` |
| `translate-effect` | `pb-parallax--translate` |

### Layout / card ratios (context-dependent) (manual)

| v1 / legacy class | v2 class |
|-------------------|----------|
| `three-l2_l2_l8` | `pb-three-columns--l2-l2-l8 | pb-card-three-columns--
l2-l2-l8` |
| `three-l2_l8_l2` | `pb-three-columns--l2-l8-l2 | pb-card-three-columns--
l2-l8-l2` |
| `three-l3_l3_l6` | `pb-three-columns--l3-l3-l6 | pb-card-three-columns--
l3-l3-l6` |
| `three-l3_l6_l3` | `pb-three-columns--l3-l6-l3 | pb-card-three-columns--
l3-l6-l3` |
| `three-l4_l4_l4` | `pb-three-columns--l4-l4-l4 | pb-card-three-columns--
l4-l4-l4` |
| `three-l6_l3_l3` | `pb-three-columns--l6-l3-l3 | pb-card-three-columns--
l6-l3-l3` |
| `three-l8_l2_l2` | `pb-three-columns--l8-l2-l2 | pb-card-three-columns--
l8-l2-l2` |
| `two-l3_l9` | `pb-two-columns--l3-l9 | pb-card-two-columns--l3-l9` |
| `two-l4_l8` | `pb-two-columns--l4-l8 | pb-card-two-columns--l4-l8` |
| `two-l6_l6` | `pb-two-columns--l6-l6 | pb-card-two-columns--l6-l6` |
| `two-l8_l4` | `pb-two-columns--l8-l4 | pb-card-two-columns--l8-l4` |
| `two-l9_l3` | `pb-two-columns--l9-l3 | pb-card-two-columns--l9-l3` |

---

## Display field classes — stored values (never change)

These **`pb__*`** tokens are **list field storage values**. The stored
value is frozen — never edit it, in content or config `allowed_values`.
In v2 the template renders a modern BEM class from the value via the
`pb_class` filter, so your CSS overrides target the new class: see
*Value-driven utility classes* above for the value → rendered-class map.

| Class | Typical use |
|-------|-------------|
| `pb__animate_bottom` | Animation / overlay |
| `pb__animate_left` | Animation / overlay |
| `pb__animate_opacity` | Animation / overlay |
| `pb__animate_right` | Animation / overlay |
| `pb__animate_top` | Animation / overlay |
| `pb__animate_zoom` | Animation / overlay |
| `pb__border` | Border |
| `pb__border_3px` | Border |
| `pb__border_6px` | Border |
| `pb__border_bottom` | Border |
| `pb__border_left` | Border |
| `pb__border_right` | Border |
| `pb__border_top` | Border |
| `pb__round_large` | Border radius |
| `pb__round_medium` | Border radius |
| `pb__round_small` | Border radius |
| `pb__round_xlarge` | Border radius |
| `pb__round_xxlarge` | Border radius |
| `pb__nmw_100` | Node content width (renders body class `pb-nmw-100`) |
| `pb__nmw_800` | Node content width (renders body class `pb-nmw-800`) |
| `pb__nmw_1024` | Node content width (renders body class `pb-nmw-1024`) |
| `pb__nmw_1280` | Node content width (renders body class `pb-nmw-1280`) |
| `pb__nmw_1440` | Node content width (renders body class `pb-nmw-1440`) |
| `pb__nmw_1600` | Node content width (renders body class `pb-nmw-1600`) |
| `pb__nmw_1920` | Node content width (renders body class `pb-nmw-1920`) |
| `pb__nmw_2560` | Node content width (renders body class `pb-nmw-2560`) |
| `pb__1200` | Breakpoints (library suffix) |
| `pb__1400` | Breakpoints (library suffix) |
| `pb__576` | Breakpoints (library suffix) |
| `pb__768` | Breakpoints (library suffix) |
| `pb__992` | Breakpoints (library suffix) |
| `pb__card_2` | Card grid |
| `pb__card_4` | Card grid |
| `pb__margin` | Margin |
| `pb__margin_bottom` | Margin |
| `pb__margin_left` | Margin |
| `pb__margin_right` | Margin |
| `pb__margin_top` | Margin |
| `pb__circle` | Other |
| `pb__section` | Other |
| `pb__padding` | Padding |
| `pb__padding_16` | Padding |
| `pb__padding_24` | Padding |
| `pb__padding_32` | Padding |
| `pb__padding_48` | Padding |
| `pb__padding_64` | Padding |
| `pb__padding_large` | Padding |
| `pb__padding_small` | Padding |
| `pb__bottom_bar` | Shadow / bar |
| `pb__ds_bottom_left` | Shadow / bar |
| `pb__ds_bottom_middle` | Shadow / bar |
| `pb__ds_bottom_right` | Shadow / bar |
| `pb__ds_left` | Shadow / bar |
| `pb__ds_middle` | Shadow / bar |
| `pb__ds_right` | Shadow / bar |
| `pb__ds_top_left` | Shadow / bar |
| `pb__ds_top_middle` | Shadow / bar |
| `pb__ds_top_right` | Shadow / bar |
| `pb__left_bar` | Shadow / bar |
| `pb__right_bar` | Shadow / bar |
| `pb__top_bar` | Shadow / bar |
| `pb__pmw_100` | Width |
| `pb__pmw_300` | Width |
| `pb__pmw_400` | Width |
| `pb__pmw_500` | Width |
| `pb__pmw_600` | Width |
| `pb__pmw_700` | Width |
| `pb__pmw_800` | Width |
| `pb__pmw_900` | Width |

---

## Related docs

| Doc | Audience |
|-----|----------|
| `docs/PB-V2-CLASS-NAMING-SPEC.md` | Naming standard |
| `docs/PB-V2-BEM-VOCABULARY.md` | Generated rename tables + frozen L2 list |
| `docs/PB-V2-BUNDLE-VOCABULARY.md` | Per-bundle L3/L4 names |
| `docs/PB-V2-THEME-BRIDGE.md` | Host-theme variable mapping |
