# Changelog

All notable changes to `paragraphs_bundles` are recorded here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [2.0.0] — 2026-07-06

A ground-up rewrite of the suite for Drupal 11.3+ / 12, shipped as a **drop-in
upgrade from 1.x**: no content or field changes are required (`drush updb`
migrates the dynamic CSS), and nothing v1 exposed was removed. This entry is the
readable summary; the complete, verifiable detail lives in three companion docs:

- **`docs/CSS-CLASS-MIGRATION.md`** — every v1 → v2 CSS class rename
  (structural,
  value-driven, constructed), with a bulk-apply script and a manual-only section
  for classes that double as stored field values.
- **`docs/planning/PB-V2-FIELD-VALUE-BEM-INVENTORY.md`** — per-field record of
  how
  every display value reaches the DOM (class / attribute / markup / behaviour).
- **`docs/planning/PB-V2-PARITY-AUDIT.md`** — the 27-module v1→v2 feature-parity
  certification (nothing user-visible dropped).

### Security
Editor-supplied display values reach the page through two unescaped sinks: the
generated per-paragraph stylesheet, and `style` attributes written by Twig
templates (Twig's HTML autoescape does **not** neutralise `(`, `)`, `;` or `:`
inside a `style` attribute). Both are now hardened, at the field layer and again
at each emit site.

- **Colour fields validate on write.** `ColorItem` now declares a
  `getConstraints()` regex, so the entity validation API rejects a value that is
  neither a hex literal (`#abc` / `#aabbcc`) nor an `r, g, b` triplet. This is
  the first line of defence and covers form, REST and JSON:API writes; the
  widget's `#element_validate` alone did not.
- **Colour emission fails closed.** `PbDynamicCssBuilder` re-validates every
  colour independently of the field layer, because config import, migration and
  direct database writes bypass entity validation. A value that does not pass
  emits **no declaration at all** — and the matching `var()` property rule
  (`background-color: var(--pb-bg);`) is dropped with it, so nothing resolves
  against an undefined custom property. Rejecting rather than scrubbing is
  deliberate: a malformed colour has no valid rendering, and a scrubbed remnant
  would emit a broken declaration while implying the value was honoured.
- **Non-colour user variables are stripped, not rejected**, since values such as
  `url("…")` legitimately contain quotes and backslashes. Structural characters
  (`{ } ; < >`, comment delimiters, newlines) are removed by a single shared
  `sanitizeCssValue()` on the colour normalizer — previously duplicated, now one
  implementation used by every caller.
- **The inline `<style>` fallback cannot become an injection vector.** When a
  paragraph's CSS file is missing the source is inlined in the page head via
  `Markup::create()`, the module's only unescaped sink. `PbPreprocessHook` now
  refuses to inline any generated CSS containing `<` and logs an error instead —
  generated CSS can never legitimately contain one, so its presence means an
  upstream guarantee regressed.
- **Parallax `style`-attribute injection closed.** The parallax section template
  interpolated five field values straight into a `style` attribute, including a
  `url()` built from rendered field markup. The background URL now comes from
  the image-field URL resolver rather than `render|striptags`, the `url()` is
  quoted, and all five values pass through a new `pb_css_value` Twig filter
  backed by the same shared sanitizer. The filter is reusable anywhere a
  template writes a value into a `style` attribute.

Covered by injection unit tests in `PbDynamicCssBuilderTest` and
`PbDynamicUserVarBuilderTest`: each payload is asserted to emit nothing (colour
path) or to be stripped of every structural character (user-variable path), and
legitimate colour input is asserted to still emit.

### Fixed
- **Strict config-schema compliance** — corrected five classes of schema
  violation in the shipped optional config (custom field-type default values,
  null `target_bundles_drag_drop`, boolean defaults stored as integers, a stray
  form-display `label`, and a `block_content` `revision` flag), so the suite
  installs cleanly under Drupal's strict config-schema checker (the drupal.org
  gate). Guarded by Kernel tests importing optional config under strict schema.
- **V1→V2 parity audit (all 27 modules)** — verified every v1 user-visible
  feature is preserved; nothing dropped. Full record in
  `docs/planning/PB-V2-PARITY-AUDIT.md`. Three fixes came out of it:
  - **A paragraph never renders unstyled.** The per-paragraph dynamic CSS lives
    in a `public://` file; if that file is missing (fresh deploy, wiped files
    directory, or content migrated without a re-save) the CSS is now inlined in
    the page head for that request. A batched `post_update` regenerates every
    file on `drush updb`, and `drush pb:regenerate-css` rebuilds on demand.
  - **Node Reference renders again** — removed a stray `hidden` attribute that
    made `node_reference` paragraphs render blank.
  - **Base classes survive theme template overrides** — `paragraph` +
    `paragraphs-bundles` are re-pinned in preprocess (scoped to PB bundles), so
    the module + dynamic CSS keep applying even when a theme overrides the
    paragraph template.
- **Cross-module CSS selector drift** — image-overlay effect classes styled from
  inside 3D-carousel slides were still v1-named after the BEM pass; realigned to
  `pb-image-overlay--*` so nested overlays keep their hover effects.
- **Dead CSS removed** — `.pb__round` (never emitted) and `.pb__animate-fading`
  (plus its orphaned `@keyframes`); every non-ASCII character removed from CSS
  comments (ASCII-only for clean DevTools output).
- **Manage display cleanup (v2 view contract)** — all `pb_display_*` fields
  hidden on paragraph view displays; styling comes from entity + dynamic CSS.
  Folded into update **`9006`**; `image_background_bundle` Twig uses
  `pb_image_field_url` instead of view formatters for background URLs.
- **`pb_content` form display upgrade** — surgical merge preserves site-added
  fields on the node type; single sub-module hook **`9001`** (removed
`9002`/`9003`).
- **Pre-release hook consolidation** — parent: **`9001`**, **`9005`**,
  **`9006`**
  only (removed fragmented `9002`–`9004`, `9007`–`9015`; view display cleanup
  inside `9006`). See `docs/PB-V2-TESTING-WORKFLOW.md`.
- **Colors group field order on v1 upgrades** — nest now sets
  `pb_display_bg_opacity` weight 16 (before bg 17). field_group sorts by widget
  weight; v1 sites left opacity at 18+ so bg/hover appeared before opacity.
- **Image Background Display tab** — normalize legacy v1 group names
  (`group_tabs_wrapper`, `group_content`, `group_display`) so link colors nest
  under Display → Colors.
- **Consolidated update hooks** — see `docs/UPGRADE-V1-TO-V2.md`.
- **Link color fields in Display → Colors** — see **`9006`** and
  `drush pb:verify-form-displays`.
- **`pb_field_value` Twig filter** — safe scalar reads from paragraph
  fields in templates. Replaces `attribute(paragraph, 'field').value`
  which can return an array under PHP 8.3+ / Twig 3 and breaks `trim()`,
  string concat, and dynamic HTML tags. Applied across 31 bundle
  templates including `pb-content-title.html.twig` and
  `paragraph--3d-carousel-bundle.html.twig`.

### Added
- **`pb_class` Twig filter** + generated `src/Twig/PbValueClassMap.php` — maps a
  stored display value (e.g. `pb__round_large`) to its modern BEM class
  (`pb-u-round--large`) at render time. Falls back to the raw value when
  unmapped, so nothing can break; the stored value is never touched.
- **`PbImageFieldUrlResolver`** + Twig filter `pb_image_field_url` — absolute
  file URLs from image/media fields without view display formatters.
- **`PbViewDisplayVerifier`** — checks no `pb_display_*` in view display
  `content`; used by `drush pb:verify-form-displays` and optional YAML script.
- **`docs/PB-V2-TESTING-WORKFLOW.md`** — maintainer QA: trash DB, Path A fresh
  install vs Path B v1 drop-over, V1/V2 hook comparison table.
- **`docs/PB-V2-INSTALL-PARITY.md`** — mandatory v2 QA policy: fresh install
  and v1 drop-over must expose the same fields in the same order; verification
  commands and release gate checklist.
- **`PbThemeBridgeService`** — resolves `theme-bridge-solo`,
  `theme-bridge-apex`,
  or `theme-bridge-neutral` from the active theme base chain. Bridge CSS maps
  host `--r-*` region variables to paragraph `--pb-*` defaults on
  `.paragraphs-bundles`. `PbLibraryInfoAlterHook` attaches the matching
  library whenever `paragraphs-bundles` loads.
- Unit tests for `PbThemeBridgeService` (theme hierarchy → bridge id).
- Unit tests for `PbDynamicCssBuilder` and `PbDynamicUserVarBuilder`
  (selectors, empty vs filled fields). Composer autoload + entity stubs for
  standalone PHPUnit.
- **`PbDynamicUserVarBuilder`** — emits non-colour editor field values
  (`--pb-gap`, `--pb-bg-image`, `--pb-overlay-bg`, layout column vars, etc.)
  into the same per-paragraph dynamic CSS file as colour vars. New
  `PbColorNormalizer` service shared by colour and overlay rgba paths.
- `docs/reference/pb-css-variables.md` — field → `--pb-*` reference for
  static vs dynamic layers.
- Unit tests for `PbColorNormalizer` (opacity, hex overlay rgba).
- CSS class inventory (Stage 1): `docs/generated/pb-class-inventory.json`.

### Changed
- **CSS class names → BEM.** Every structural class is now
  `pb-{block}__{element}--{modifier}` and every value-driven utility is
  `pb-u-{family}--{modifier}` — e.g. `pb__accor` → `pb-accordion`,
  `pb__carousel-item` → `pb-carousel__slide`, `pb__round_large` →
  `pb-u-round--large`, `pb__image-overlay-fade` → `pb-image-overlay--fade`.
  **Stored field values are byte-for-byte unchanged**: v1 rendered the stored
  value *as* the class; v2 renders a BEM class from it via the `pb_class`
filter,
  so there is **no content or config migration**. JS-set state classes were
  converted in lockstep too (`has-collapsed-col` → `pb-has-collapsed-col`,
  `grid-count-N` → `pb-image-grid--count-N`, `btn-hidden` → `pb-is-hidden`,
  `fade-in` → `pb-is-fade-in`). Element IDs and breakpoint marker tokens
  (`pb__576`…) intentionally keep their spelling (they double as library names /
  data attributes). Full old→new map: `docs/CSS-CLASS-MIGRATION.md`.
- **Per-paragraph dynamic CSS files are content-addressed** —
  `pb-{slug}-{id}.{hash}.css`. Editing a paragraph's display settings now shows
  immediately **without a cache clear**: changed content yields a new filename →
  new URL → only that paragraph's aggregate rebuilds. The library name stays
  stable, so templates and the render cache are unaffected.
- **PB CSS is deliberately un-layered.** Removed the `@layer pb.*` ladder —
  because
  un-layered author rules beat every layered rule, editor-set values reliably
win
  under any host theme, whatever that theme does with `@layer`.
- **Parallax bundle:** restore v1 inline structural styles on
  `paragraph--parallax-section-bundle.html.twig` (background image,
  section height, JS animation vars); remove erroneous `hidden` from both
  parallax twigs; fix media reference resolution in
  `PbDynamicUserVarBuilder::imageFieldUrl()` for `pb_content_image`.
- **Theme bridge:** `--pb-tx-h`, `--pb-br-h`, and `--pb-lk-h` removed from
  bridge files — hover colours are editor-only (same rule as `--pb-bg-h`).
  Foundation CSS resets hover tokens to `initial` and uses
  `var(--pb-*-h, var(--pb-*))` fallbacks; button hover targets the
  `button` / `.pb-ui-btn` element explicitly.
- **Theme bridge:** `--pb-bt-a` removed from bridge (editor-only active
  tab/accordion/slide colour, same rule as `--pb-bg`).
- **`PbPreprocessHook` / `PbcPreprocessHook`:** set `pb_dynamic_library`
  only when the per-entity CSS file exists; Twig attaches conditionally.
- **Card text section:** fix `back-ground-color` typo → `background-color`.
- **`buildForNode()`:** hex page backgrounds now respect opacity via
  `overlayRgba()` (same as paragraphs).
- **Theme bridge:** `--pb-bg` and `--pb-bg-h` removed from bridge files —
  background colour is editor-only, never inherited from Solo/APEX region
  variables. Foundation CSS no longer sets `background-color` on
  `.paragraph__inner`; each bundle applies background on its own DOM target
  via dynamic CSS + bundle static CSS.
- **Bundle CSS:** Removed inline `var(--pb-*, var(--r-*, …))` chains from
  hero, layout, alert, slideshow, 3d carousel, and image grid front-end CSS;
  theme bridge owns host-theme mapping.
- **Theme bridge:** `paragraphs-bundles-custom.css` no longer repeats
  `var(--pb-*, var(--r-*, …))` fallback chains; bridge files own host-theme
  mapping and system-colour defaults for neutral themes.
- **Inline → dynamic CSS (Phase A/B):** Removed user-value `style=""`
  from image_background, grid, carousel, image_grid, layout columns,
  3d carousel/flip, parallax section, and icon bundles. Dynamic CSS
  emits the same properties on the same DOM nodes v1 inline used (not
  vars on a parent for static to consume). Layout breakpoint CSS no
  longer re-declares `--pb-col-*` defaults that overrode editor values.
- `image_background_bundle` attaches `pb_dynamic_library` and fixes
  overlay rgba (`rgba(#hex)` bug) via normalizer.
- **`pb_content` node page background** — dynamic CSS on `#node-{id}`
  via `PbcEntityHook` / `PbcPreprocessHook`; Twig inline removed.
- `drush pb:regenerate-css` now rebuilds **pb_content nodes** as well as
  paragraphs.

### Added
- `pb_display_link` / `pb_display_link_hover` shared field storages and
  `simple_bundle` instances. Emit `--pb-lk` / `--pb-lk-h` into the
  per-paragraph dynamic CSS file; foundation CSS in
  `paragraphs-bundles-custom.css` applies them to `<a>` elements inside
  the paragraph. Reusable install helper
  `paragraphs_bundles_pb_link_wire_bundle()` lets each sub-module wire
  the fields when it is ported.
- Per-paragraph dynamic CSS files in `public://paragraphs_bundles/`,
  replacing the v1 inline `style="--pb-bg:rgba(...);"` attribute. Each
  file scopes `--pb-*` declarations to `#pb__{slug}-{id}__inner`;
  element-id specificity (1,0,0) makes editor choices win without
  `!important`.
- New `--pb-lk` / `--pb-lk-h` link colour variables. Falls back to
  host design system (`--r-lk`) and finally `LinkText` system
  colour. Preserves link affordance independently of paragraph text.
- Drush command `pb:regenerate-css` (alias `pb-regen`) with
  `--purge` to wipe and rebuild every CSS file.
- `update_9005()` backfills CSS files for existing paragraphs on
  upgrade.
- `update_9006()`–`update_9008()` wire shared fields on `simple_bundle`
  (title tag/alignment, link colors, display tab color order).
- `update_9009()` wires shared v2 fields on **every** PB bundle
  (auto-discovers bundles via `pb_display_bg`; creates link field
  instances programmatically when v2 YAML is not shipped yet).
- `update_9010()` nests Display tab fields into collapsible sections
  (Colors, Border & radius, Spacing, Size & effects) on every PB bundle.
- Reusable install helper
  `paragraphs_bundles_pb_wire_shared_fields_for_bundle()` — single entry
  point for all shared v2 field wiring (no sub-module `.install`
  required).
- `paragraph_bundle_hero` sub-module port (`hero_bundle`): v1 Twig DOM
  preserved; dynamic CSS scoped to
  `#pb__hero-{id} .pb__hero-txt-wrapper-inner` (nested color target).
- `paragraph_bundle_icon` sub-module port (`icon_bundle`): v1 Twig DOM
  preserved; Google Material Symbols libraries + admin assets; dynamic CSS
  on `.paragraph__inner`; nested Display tab + link/title shared fields.
- `paragraph_bundle_alert` sub-module port (`alert_bundle`): v1 Twig DOM
  preserved (dismissal, countdown, date range, close button); dynamic CSS on
  `.paragraph__inner`; OOP preprocess + datetime timezone widget + admin JS.
- `paragraph_bundle_link` sub-module port (`link_bundle` +
  `link_section_bundle`):
  Track A default template for `link_bundle`; nested link sections; nested
Display
  tab + link/title shared fields; minimal `link_section_bundle` Twig preserved.
- `paragraph_bundle_card` sub-module port (four bundles): v1 Twig DOM preserved;
  responsive breakpoint libraries; split dynamic CSS for card image overlay
title;
  `PbDynamicCssBuilder` outer-id overrides + `SPLIT_COLOR_SCOPES` for
  `card_image_section_bundle`.
- `paragraph_bundle_modal` sub-module port (`modal_bundle`): v1 Twig DOM/ARIA
  preserved; modal JS (focus trap, Escape); dynamic CSS on
  `#pb__modal-id-{id}-win .pb-modal-content > .paragraph__inner`; outer-id
  suffix `-win` in `PbDynamicCssBuilder`.
- `paragraph_bundle_accordion` sub-module port (`accordion_bundle` +
  `accordion_section_bundle`): v1 Twig DOM/ARIA preserved; accordion JS;
  dynamic CSS on `#pb__accor-wrap-{id} > .paragraph__inner`; `--pb-bt-a` from
  `pb_display_accor_active_item` via `BUNDLE_EXTRA_COLOR_FIELDS`.
- `paragraph_bundle_tabs` sub-module port (`tabs_bundle` +
  `tab_section_bundle`): v1 Twig DOM/ARIA preserved; tabs JS; dynamic CSS on
  `#pb__tabs-{id} > .paragraph__inner`; `--pb-bt-a` from
  `pb_display_tab_active_item`.
- `paragraph_bundle_grid` sub-module port (`grid_bundle`): v1 Twig preserved;
  responsive breakpoint CSS libraries; dynamic CSS on
  `#pb__grid-{id} > .paragraph__inner`; inline gap on `.pb__grid-wrapper`.
- `paragraph_bundle_carousel` sub-module port (`carousel_bundle`): v1 Twig
  DOM/ARIA preserved; carousel JS; dynamic CSS on
  `#pb__carousel-{id} > .paragraph__inner`; breakpoint libraries; title block
  weights 1–3 via `wire-bundle-shared-fields.php`.
- `paragraph_bundle_node_reference` sub-module port (`node_reference_bundle`):
  `pb_node_reference` content type and node view modes; v1 Twig DOM preserved;
  dynamic CSS on `#pb__nf-{id} > .paragraph__inner`; conditional NF fields via
  `PbnrFieldWidgetHook` and `pb-conditional-fields` JS; title block weights 1–3.
- `paragraph_bundle_parallax` sub-module port (`parallax_bundle` +
  `parallax_section_bundle`): v1 parallax JS/CSS preserved; wrapper dynamic CSS
  on `#pb__parallax-{id} > .paragraph__inner`; section colors scoped to
  `.pb__parallax-text`; breakpoint libraries; title block weights 1–3.
- `paragraph_bundle_slideshow` sub-module port (`slideshow_bundle` +
  `slideshow_section_bundle`): v1 slideshow DOM/ARIA/JS preserved; dynamic CSS
  on `#pb__slide-wrap-{id} > .paragraph__inner` with `--pb-bt-a` active slide
  color; title block weights 1–3.
- `paragraph_bundle_image_background` sub-module port
  (`image_background_bundle`):
  v1 background/overlay DOM preserved; inline background CSS and overlay rgba;
  responsive bg swap JS; outer id `pb__image-bg-{id}`; no title field (verify
  SKIP).
- `paragraph_bundle_image_grid` sub-module port (`image_grid_bundle` +
  `image_grid_section_bundle`): v1 grid/lightbox DOM/JS preserved; dynamic CSS
  on `#pb__image-grid-{id} > .paragraph__inner`; section split colors (border on
  inner, bg/text on `.pb__image-grid-title`); `PbigPreprocessSectionHook` sets
  lightbox full-size URL; title block weights 1–3 on both bundles.
- `paragraph_bundle_image_overlay` sub-module port (`image_overlay_bundle`):
  v1 overlay DOM/effects CSS preserved; split dynamic CSS (border on inner,
  bg/text on `.pb__overlay-container` and `.pb__overlay-title`); outer id
  `#pb__image-overlay-{id}`; title block weights 1–3.
- `paragraph_bundle_3d_carousel` sub-module port (`3d_carousel_bundle`): v1 3D
  carousel DOM/JS/ARIA preserved; dynamic CSS on `#pb__caro-wrap-{id} >
  .paragraph__inner`; inline perspective px; breakpoint libraries; no title
  field (wire/verify SKIP).
- `paragraph_bundle_3d_flip_box` sub-module port (`3d_flip_box_bundle`): v1
  flip-box DOM/CSS preserved; dynamic CSS on `#pb__3d-flip-box-{id} >
  .paragraph__inner`; inline height px + flip axis class on `.pb__flip-box`;
  title block weights 1–3.
- `paragraph_bundle_layout` sub-module port (`one_column_bundle`,
  `two_columns_bundle`, `three_columns_bundle`): v1 column layout DOM/ARIA/JS
  preserved; dynamic CSS on each bundle's `#pb__{layout-slug}-{id} >
  .paragraph__inner`; breakpoint libraries; column toggle toolbar + sticky
  inline grid vars; title block weights 1–3 on all three bundles.
- All v2 `*.info.yml` files: `package: 'Paragraphs Bundles'` (separate Extend
  list group from core Paragraphs).
- Removed `paragraph_bundle_block.install` — `hook_install()` ran before the
  paragraph type was registered; use optional YAML on enable + parent
`9009`/`9010`
  on upgrade.
- `block_bundle` form/view displays: title tag and title alignment wired
  (Content tab + hidden on view), matching `simple_bundle` / `hero_bundle`.
- Track A sub-modules (`paragraph_bundle_block`,
  `paragraph_bundle_block_content`,
  `paragraph_bundle_contact_form`, `paragraph_bundle_views`,
  `paragraph_bundle_webform`): OOP theme + suggestion + help hooks; optional
  config from v1 plus `pb_display_link` / `pb_display_link_hover`; nested
  Display tab field groups; title tag/alignment on form + hidden on view
  (block pattern); all use parent `paragraph--default.html.twig`.
- `scripts/nest-display-field-groups.php` — CLI helper mirroring `update_9010()`
  for optional form display YAML during bundle ports.
- `scripts/verify-bundle-form-display.php` — gate before shipping a bundle;
  checks four shared fields, title block order, link fields in Colors group.
- `update_9011()` and `--sort-extra` on `nest-display-field-groups.php` —
  group Additional display fields by widget type (selects, colors, numbers,
  checkboxes, datetime, range, media) for two-per-row admin layout.
- `docs/BUNDLE-PORT-WORKFLOW.md` — ordered port checklist (audit → scaffold →
  link fields → view formatters → **nest Display tab** → Twig → QA).

### Changed
- Display tab **Additional** section admin layout: number, checkbox,
  datetime, and color accent fields use the same two-per-row flex rules as
  margin/padding selects; image/media fields stay full width.
- Per-paragraph CSS library URLs use Drupal's standard single
  six-character aggregate cache-buster (no custom `?v=` suffix).
  `library_info` is invalidated on paragraph save so browsers fetch
  fresh CSS after a save overwrites the file at the same path.
- Dynamic CSS emits id-scoped `:where(a)` link colour rules when
  `pb_display_link` / `pb_display_link_hover` are set (same specificity
  as background/text so theme link styles do not win).
- Per-paragraph dynamic CSS loads in `CSS_AGGREGATE_THEME` at weight
  **1000** via `PbCssAlterHook` so editor colours render after theme CSS
  (APEX `apex_color` pattern).
- Admin CSS/JS attach once per request via `PbFieldWidgetHook`
  (`field_widget_single_element_form_alter` on paragraph PB widgets);
  removed per-field duplicate `#attached` and broken form-level hooks.
- Display tab on paragraph forms uses nested `details` field groups:
  Colors (open), Border & radius, Spacing, Size & effects, and
  Additional (bundle-specific fields). `update_9010()` applies this to
  every PB bundle; fresh installs get it from optional form display YAML.
- Drupal version requirement: `^11.3 || ^12`.
- PHP version requirement: `8.3+`.
- All hooks are OOP (`#[Hook]` attribute classes) except update hooks.
- All field plugins use the Drupal 11 attribute API
  (`#[FieldType]`, `#[FieldWidget]`, `#[FieldFormatter]`).
- Dynamic libraries advertise per-paragraph CSS through
  `hook_library_info_build()`; library names are
  `paragraphs_bundles/dynamic--{slug}-{id}` (e.g.
  `paragraphs_bundles/dynamic--simple-123`).
- Twig extension class renamed
  `ParagraphsBundlesTwigExtension` → `PbTwigExtension`. The
  `decode_entities` filter is preserved verbatim.
- Foundation stylesheet renamed
  `paragraphs-bundles-basic.css` → `paragraphs-bundles-base.css`.
  Removed the legacy `:root { --pb-bg: initial; ... }` block: those
  declarations were redundant in v1 (custom-property `initial` =
  guaranteed-invalid = same as undeclared) and harmful in v2 (they
  would shadow the per-paragraph dynamic file's values via
  inheritance, breaking editor-set colours including hover).
- `PbDynamicCssBuilder` supports per-bundle inner scope overrides when
  v1 Twig applies colours to a nested element (first: `hero_bundle` →
  `.pb__hero-txt-wrapper-inner`). `OUTER_ID_PREFIX_OVERRIDES` keeps v1
  outer ids (e.g. `#pb__hero-{id}`) in sync with preprocess + CSS.

### Fixed
- `paragraph_bundle_image` Twig: read display list keys from
  `paragraph.field.value` (view display hides `pb_display_*` fields).
- `image_narrow_bundle` / `image_wide_bundle`: restore v1 outer ids
  (`pb__image-n-{id}`, `pb__image-w-{id}`) in Twig + `PbDynamicCssBuilder`
  so dynamic CSS scopes match the DOM.

### Preserved
- All field machine names (`pb_content_*`, `pb_display_*`).
- All CSS custom properties (`--pb-bg`, `--pb-bg-h`, `--pb-br`,
  `--pb-br-h`, `--pb-tx`, `--pb-tx-h`, `--pb-px*`).
- All BEM class names from v1 templates (any future rename will be
  documented in `docs/CSS-CLASS-MIGRATION.md` and applied via the
  migration scripts).
- Library names `paragraphs_bundles/color-picker` and
  `paragraphs_bundles/opacity-range` (referenced by widget plugins).
- Update hooks 9001–9004 ported verbatim from v1.
