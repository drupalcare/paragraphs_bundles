# PB v2 BEM vocabulary (generated)

Generated from the maintainer's class-naming ruleset, which is development
tooling and is not part of the release package. The tables below are the
shipped record.

**Authoritative standard:** `docs/PB-V2-CLASS-NAMING-SPEC.md`

**Renames (L3 structural):** 166
**Frozen (L2 config):** 87

## Spec categories

| Category | Pattern |
|----------|---------|
| Shared UI | `pb-ui-*` |
| State | `pb-is-*` |
| Flags | `pb-has-*`, `pb-no-*` |
| Utilities | `pb-u-*` |
| Components | `pb-{bundle-slug}__*` |
| Phase B | Intermediate → approved (ported bundles) |
| Layout column | `pb-layout-col__*` |

## Frozen L2 config (not renamed)

Most of these double as stored field values, so renaming one would require a
content migration; the rest are asset-library suffixes (`pb__576`…) or admin
form-group names held in form-display config. They are frozen for those reasons
and keep their v1 spelling. Classes that v1 *built* from a stored value (e.g.
`pb__3d-caro-100`, `pb__image-overlay-fade`) are **renames, not frozen tokens**
—
they are listed in the per-bundle tables below.

<details><summary>Frozen class list (69)</summary>

| Class |
|-------|
| `pb__1200` |
| `pb__nmw_100` |
| `pb__nmw_800` |
| `pb__nmw_1024` |
| `pb__nmw_1280` |
| `pb__nmw_1440` |
| `pb__nmw_1600` |
| `pb__nmw_1920` |
| `pb__nmw_2560` |
| `pb__1400` |
| `pb__576` |
| `pb__768` |
| `pb__992` |
| `pb__animate_bottom` |
| `pb__animate_left` |
| `pb__animate_opacity` |
| `pb__animate_right` |
| `pb__animate_top` |
| `pb__animate_zoom` |
| `pb__border` |
| `pb__border_3px` |
| `pb__border_6px` |
| `pb__border_bottom` |
| `pb__border_left` |
| `pb__border_right` |
| `pb__border_top` |
| `pb__bottom_bar` |
| `pb__card_2` |
| `pb__card_4` |
| `pb__circle` |
| `pb__ds_bottom_left` |
| `pb__ds_bottom_middle` |
| `pb__ds_bottom_right` |
| `pb__ds_left` |
| `pb__ds_middle` |
| `pb__ds_right` |
| `pb__ds_top_left` |
| `pb__ds_top_middle` |
| `pb__ds_top_right` |
| `pb__left_bar` |
| `pb__margin` |
| `pb__margin_bottom` |
| `pb__margin_left` |
| `pb__margin_right` |
| `pb__margin_top` |
| `pb__padding` |
| `pb__padding_16` |
| `pb__padding_24` |
| `pb__padding_32` |
| `pb__padding_48` |
| `pb__padding_64` |
| `pb__padding_large` |
| `pb__padding_small` |
| `pb__pmw_100` |
| `pb__pmw_300` |
| `pb__pmw_400` |
| `pb__pmw_500` |
| `pb__pmw_600` |
| `pb__pmw_700` |
| `pb__pmw_800` |
| `pb__pmw_900` |
| `pb__right_bar` |
| `pb__round_large` |
| `pb__round_medium` |
| `pb__round_small` |
| `pb__round_xlarge` |
| `pb__round_xxlarge` |
| `pb__section` |
| `pb__top_bar` |

</details>

## Shared Ui

| Legacy | Target |
|--------|--------|
| `paragraph__inner` | `pb-ui-inner` |
| `pb-block` | `pb-ui-block` |
| `pb__bar` | `pb-ui-bar` |
| `pb__button` | `pb-ui-btn` |
| `pb__button-title` | `pb-ui-btn__title` |
| `pb__link` | `pb-ui-link` |
| `pb__row` | `pb-ui-row` |

## State

| Legacy | Target |
|--------|--------|
| `pb__active` | `pb-is-active` |
| `pb__active-no` | `pb-is-inactive` |

## Flags

| Legacy | Target |
|--------|--------|
| `pb__no-bottom-nav` | `pb-no-bottom-nav` |

## Utilities

| Legacy | Target |
|--------|--------|
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

## Accordion

| Legacy | Target |
|--------|--------|
| `pb__accor` | `pb-accordion` |
| `pb__accor-button` | `pb-accordion__button` |
| `pb__accor-pane` | `pb-accordion__pane` |
| `pb__accor-wrap-btn-item` | `pb-accordion__item` |
| `pb__accor-wrap__inner` | `pb-accordion-wrap__inner` |

## Alert

| Legacy | Target |
|--------|--------|
| `pb__alert-countdown` | `pb-alert__countdown` |
| `pb__alert-countdown-unit` | `pb-alert__countdown-unit` |
| `pb__alert-top` | `pb-alert--top` |
| `pb__alert__inner` | `pb-ui-inner` |

## Card

| Legacy | Target |
|--------|--------|
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

## Carousel

| Legacy | Target |
|--------|--------|
| `pb__caro-item` | `pb-carousel__slide` |
| `pb__caro-options` | `pb-carousel__options-bar` |
| `pb__caro-options-inner` | `pb-3d-carousel__options-inner` |
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

## 3D Carousel

| Legacy | Target |
|--------|--------|
| `pb__3d-back` | `pb-3d-flip-box__back` |
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
| `pb__3d-front` | `pb-3d-flip-box__front` |

## 3D Flip Box

| Legacy | Target |
|--------|--------|
| `pb__flip-box` | `pb-3d-flip-box` |
| `pb__flip-box-inner` | `pb-3d-flip-box__inner` |

## Grid

| Legacy | Target |
|--------|--------|
| `pb__grid-lightbox` | `pb-grid__lightbox` |
| `pb__grid-lightbox-content` | `pb-grid__lightbox-content` |
| `pb__grid-wrapper` | `pb-grid__wrapper` |
| `pb__grid__inner` | `pb-grid__inner` |

## Hero

| Legacy | Target |
|--------|--------|
| `pb__hero-txt-wrapper` | `pb-hero__text-wrapper` |
| `pb__hero-txt-wrapper-inner` | `pb-hero__text-inner` |

## Icon

| Legacy | Target |
|--------|--------|
| `pb__icon-header` | `pb-icon__header` |
| `pb__icon__inner` | `pb-icon__inner` |

## Image

| Legacy | Target |
|--------|--------|
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

## Image Overlay

| Legacy | Target |
|--------|--------|
| `pb__overlay-container` | `pb-overlay__container` |
| `pb__overlay-title` | `pb-overlay__title` |

## Layout

| Legacy | Target |
|--------|--------|
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

## Modal

| Legacy | Target |
|--------|--------|
| `pb-modal-body` | `pb-modal__body` |
| `pb-modal-button` | `pb-modal__button` |
| `pb-modal-content` | `pb-modal__content` |
| `pb-modal-footer` | `pb-modal__footer` |
| `pb-modal-header` | `pb-modal__header` |
| `pb-modal-title` | `pb-modal__title` |
| `pb__modal-next` | `pb-modal__next` |
| `pb__modal-prev` | `pb-modal__prev` |
| `pb__modal-wrapper` | `pb-modal__wrapper-{id}` (id) |
| `pb__modal__inner` | `pb-modal__inner` |

## Parallax

| Legacy | Target |
|--------|--------|
| `pb__parallax-bg` | `pb-parallax__bg` |
| `pb__parallax-bundle__inner` | `pb-parallax__inner` |
| `pb__parallax-section__inner` | `pb-parallax-section__inner` |
| `pb__parallax-text` | `pb-parallax__text` |

## Slideshow

| Legacy | Target |
|--------|--------|
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

## Tabs

| Legacy | Target |
|--------|--------|
| `pb__tab-button` | `pb-tabs__tab` |
| `pb__tab-pane` | `pb-tabs__panel` |
| `pb__tabs-bundle` | `pb-tabs` |
| `pb__tabs-button` | `pb-tabs__tablist` |
| `pb__tabs-pane` | `pb-tabs__panels` |

## Phase B

| Legacy | Target |
|--------|--------|
| `pb-accordion__root` | `pb-accordion__sections` |
| `pb-carousel__outer` | `pb-carousel__viewport` |
| `pb-slideshow__inner` | `pb-slideshow__stage` |
| `pb-tabs__buttons` | `pb-tabs__tablist` |
| `pb-tabs__panes` | `pb-tabs__panels` |
| `pb-tabs__tab-button` | `pb-tabs__tab` |
| `pb-tabs__tab-pane` | `pb-tabs__panel` |

## Other

| Legacy | Target |
|--------|--------|
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
