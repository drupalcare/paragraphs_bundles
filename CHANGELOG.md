# Changelog

All notable changes to `paragraphs_bundles` are recorded here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [2.0.0] — 2026-07-06

A ground-up rewrite of the suite for Drupal 11.3+ / 12, shipped as a **drop-in
upgrade from 1.x**: no content or field changes are required, and every v1
bundle keeps its rendered DOM *structure*. Read
**`docs/UPGRADE-V1-TO-V2.md`** before upgrading — the upgrade is one-way, and
it has a prerequisite (your site must already be on the last 1.x release).

Three things do change for site builders, all detailed below: **structural CSS
class names were renamed**, **outer element ids were standardised** (both in
`docs/CSS-CLASS-MIGRATION.md`, which lists all 304 class renames, the 69 frozen
tokens that did not change, and the full id table), and three things v1 bundled
are no longer in the package (see **Removed**).

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

### Removed
Three things that 1.x shipped are no longer in the package. All three are
user-visible; read this section before upgrading.

- **The bundled Material Symbols web fonts (3 files, ~11 MB).**
  `paragraph_bundle_icon` no longer ships
  `material-symbols-{outlined,rounded,sharp}.woff2`, and the module's CSS no
  longer declares an `@font-face` for them. They are Apache-2.0 licensed, which
  is not compatible with this project's GPL-2.0-or-later, and drupal.org does
  not package bundled third-party font binaries. **Sites using the Material
  Symbols icon styles must now supply the font themselves** — from the theme,
  or self-hosted; `modules/paragraph_bundle_icon/README.md` documents both,
  including the privacy trade-off of loading it from Google. The class rules
  that turn a ligature into a glyph still ship, so supplying the font under its
  standard family name is all that is needed. If the font is missing the icon
  renders as its ligature name in plain text (a `home` icon shows the word
  "home") — that is the symptom to look for. Sites that use the image-icon
  field rather than Material Symbols are unaffected, and no stored value
  changes either way.
- **28 bundled `LICENSE` / `LICENSE.txt` files**, one per sub-module. The
  project's licence is unchanged and is still declared in `composer.json` and
  every `.info.yml`; drupal.org adds the canonical GPL `LICENSE.txt` to the
  release tarball itself, so carrying 28 copies in the repository was
  duplication, not compliance.
- **The `.pb-u-animate--fading` CSS class and its `@keyframes animatefading`.**
  This was the module's only infinite animation and it had no pause control, so
  it was a WCAG 2.2.2 / 2.3.3 liability. It was also unreachable: it appears in
  no template, no JavaScript, no other stylesheet, and in no field's
  `allowed_values`, and `PbValueClassMap` maps six animation values, none of
  them this one. It was deleted rather than given a `prefers-reduced-motion`
  guard because its keyframes end at `opacity: 0` with no fill-mode, so a
  single finite run would fade the element out and then snap it back. The six
  reachable animation values (`bottom`, `left`, `opacity`, `right`, `top`,
  `zoom`) are unaffected and are now reduced-motion aware.

### Fixed
- **The slideshow's play button is no longer a non-tab child of a
  `role="tablist"`.** Whenever autoplay ran, WCAG 2.2.2 required a pause
  control, and that control lives in the same row as the numbered nav — so any
  slideshow with both dots and autoplay put a plain button inside a tablist,
  which may only own tabs. The slideshow now uses the ARIA APG **basic
  carousel** pattern throughout: the nav is a `role="group"`, the numbered
  buttons are plain buttons carrying `aria-controls` and `aria-current`, and
  every slide is a `role="group"` with `aria-roledescription="slide"`. Two
  consequences worth knowing: every dot is now reachable with Tab rather than
  arrow keys within a roving-tabindex group, and slides no longer change role
  depending on which bottom-navigation style is selected. Guarded by
  `PbSlideshowAriaTest`, which now asserts the tab roles are absent in all six
  configurations rather than merely balanced.
- **Three crash guards on paths a visitor can reach.** A theme that leaves a
  Markup object in a paragraph's `class` list made `array_diff()` cast it to
  string — harmless — but an array in that list is a fatal, and the class list
  is whatever the theme put there. A render array whose `#cache` is not an
  array made `unset($build['#cache']['keys'])` fatal rather than a no-op. And
  the form-display verifier passed raw config into methods that index it by
  name, so a non-string child reached `implode()` and failed while reporting a
  failure. All three now normalise or guard first.
- **Template suggestions could be built from a foreign cache entry.** The
  managed-bundle list was returned from cache after only an `is_array()`
  check, so anything else written to that cache id — most plausibly an entry
  from a different version of this code — would have been handed to the
  suggestion builder as bundle names. The entry is now validated and
  recomputed when it does not hold.
- **`PbcRegionMap::layoutLibraryName()` no longer claims it can return NULL.**
  It never could — the `match` has a default arm — but the interface said
  `?string` and documented a "no library" case, so every caller carried a dead
  null-check.
- **Every high-contrast style the module ships now actually applies.** 28 CSS
  files carried an `@media (prefers-contrast: high)` block — thicker focus
  outlines, heavier borders, stronger checkbox outlines. `high` was renamed to
  `more` in Media Queries Level 5 before any browser shipped the feature, so
  none of those blocks has ever matched, on any engine, for any visitor who
  asked their operating system for increased contrast. All 28 now say `more`.
- **An unprefixed `@keyframes fadeIn` no longer collides with the theme's.**
  The 3D carousel's entry animation used a name generic enough that any theme
  defining its own `fadeIn` would win or lose at random depending on load
  order. It is now `pb-fade-in`.
- **The Material Symbols CSS names a fallback font.** The three icon
  stylesheets set a font family the module deliberately does not bundle, with
  no generic fallback after it — so the branch that actually runs on a site
  that has not supplied the font was the unspecified one.
- **The 3D flip box can be operated with a keyboard, and its hidden face is
  hidden from screen readers too.** The box carried `tabindex="0"` on a plain
  `<div>` with no role, no name and no key handler — a keyboard user could land
  on it and nothing happened (WCAG 2.1.1, 4.1.2). `backface-visibility` is a
  visual property only, so both faces were exposed to assistive technology at
  once (1.3.2) and any link on the face turned away stayed in the tab order
  while invisible, sending focus off-screen (2.4.3, 2.4.7). The bundle now
  ships a flip control in the box's top-right corner — a real `<button>`, so
  Enter and Space work — and marks whichever face is turned away `inert` and
  `aria-hidden`. Hovering still flips the box, and the face you are looking at
  is always the operable one. Without JavaScript the control stays hidden and
  the box behaves exactly as it did before.
- **An unrecognised site timezone no longer white-screens the alert form.** The
  alert widget hook passed `system.date`'s `timezone.default` straight into
  `new \DateTimeZone()`, which throws on any value PHP does not recognise — and
  the hook runs on every alert paragraph form, so a site whose timezone had been
  set by a migration, a config import or a hand-edited YAML lost the ability to
  edit its alerts. Anything blank, non-string or unrecognised now falls back to
  UTC, the same fallback the code already applied to an unset value.
- **A colour field rejects a malformed submission instead of crashing on it.**
  `ColorTextWidget`'s validate callback passed the submitted value to
  `Color::validateHex()`, which is typed `string`; a value arriving as an array
  — reachable from a crafted POST — raised a `TypeError` and took down the form
  submission rather than showing the field's validation message.
- **The opacity field no longer accepts a value it cannot store.** The 1–100
  validator cast the submitted value to `int` before range-checking it, and PHP
  scores an array as `1` — inside the valid range. Non-numeric input is now
  rejected by the same message that rejects an out-of-range number.
- **Themes can override 17 more bundles per view mode.** Twelve sub-modules
  implemented `hook_theme_suggestions_paragraph_alter()` to append
  `paragraph__<bundle>` — a suggestion the Paragraphs module already provides
  for every paragraph. Because Drupal scans suggestions from last to first, that
  duplicate outranked `paragraph__<bundle>__<view_mode>`, so a theme's
  `paragraph--card-two-columns-bundle--teaser.html.twig` was silently ignored on
  17 of the 39 bundles while working normally on the other 22. The twelve hook
  classes are removed; template selection is now Paragraphs' own, consistently
  for every bundle. A Kernel test fails the build if any sub-module reintroduces
  the pattern.
- **A damaged image file no longer white-screens a page with an image grid.**
  The image-grid section preprocess passed `File::getFileUri()` straight into
  `ImageStyle::buildUrl()`, which is typed `string`; a File row with a NULL uri
  raised a `TypeError` and took down the whole page. The URL is also now
  invalidated by the media entity and the image style it is built from, not
  only by the file.
- **An alert set to a date range no longer fatals when its date fields are
  missing.** `pb_display_alert_start_date` and `pb_display_alert_end_date` were
  the only fields the alert preprocess read without a `hasField()` guard, so
  deleting either one crashed every alert configured for `date_range`.
- **Every sub-module now declares the modules its own config needs.** All 26
  bundle modules under-declared their dependencies and relied on the parent to
  pull the rest in — every one of them was missing `field_group`, and between
  them they were also missing `options`, `paragraphs`, `link`, `media`,
  `media_library`, `text`, `file`, `image`, `user` and `views`. Drupal skips
  optional config whose dependencies are unmet **silently**, and `drush en`
  still reports success, so a sub-module installed on a site whose parent
  dependency list had moved on would have installed a paragraph type with no
  form display and no error. The parent's own list was the mirror image: it
  declared `block`, `image`, `media_library`, `system`, `user` and `views`,
  none of which its config or its PHP uses. Both sides are now derived from
  what each module actually ships, and a unit test fails the build if a module
  adds config needing something it does not declare.
- **Sub-module dependencies carry the right project prefix.** Seven `info.yml`
  files declared `paragraph_bundle_image:paragraph_bundle_image`. The prefix
  names the drupal.org *project*, which is `paragraphs_bundles`; the
  sub-modules have no project of their own.
- **Two CSS rules that had never matched anything now do.** The simple bundle's
  inner wrapper carries `pb__simple__inner`, but the width rule targeted
  `.simple__inner`; the slideshow's image rule targeted
  `field--name-pb__image-field`, while the media field is `pb_image_field` so
  Drupal emits `field--name-pb-image-field`. Also removed a `--pb-dot-active`
  fallback from the slideshow dot, a custom property this project emits
  nowhere and so could never resolve.
- **An accordion section with no title no longer takes down the page.** The
  accordion template built its fragment slug by lowercasing the section title
  read straight off the field. On an empty field that expression yields an
  array, not a string, so rendering an untitled section raised
  `mb_strtolower(): Argument #1 ($string) must be of type string, array given`
  and the page failed. Titles are now read through the module's
  `pb_field_value` filter, which always returns a string.
- **Editing a nested body paragraph invalidates the page that shows it.**
  Tabs, accordion and slideshow render bodies nested inside sections, and did so
  in a way that discarded the body paragraph's cache metadata. A cached page had
  no record that it depended on those bodies, so edits could stay invisible
  until something else cleared the page. The bodies' cache tags now bubble up.
- **Help pages for Paragraph Bundle Hero and Paragraph Bundle Image** were blank
  because neither module shipped a `README.md`. Both now document their
  paragraph types, dependencies and the config they install — Image in
  particular owns the shared media type, breakpoints and image styles that six
  other bundle modules depend on.
- **Strict config-schema compliance** — corrected five classes of schema
  violation in the shipped optional config (custom field-type default values,
  null `target_bundles_drag_drop`, boolean defaults stored as integers, a stray
  form-display `label`, and a `block_content` `revision` flag), so the suite
  installs cleanly under Drupal's strict config-schema checker (the drupal.org
  gate). Guarded by Kernel tests importing optional config under strict schema.
- **V1→V2 feature parity (all 27 modules)** — every bundle was ported with its
  v1 Twig DOM preserved, and a Kernel test renders every bundle to catch a
  template that stops producing the shared wrapper. Three fixes came out of the
  port review:
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
- **Dead CSS removed** — `.pb__round`, which was never emitted; every
  non-ASCII character removed from CSS comments (ASCII-only for clean DevTools
  output). The fading animation is covered in **Removed** above: it survived
  this pass under its BEM name and was deleted later.
- **Manage display cleanup (v2 view contract)** — all `pb_display_*` fields
  hidden on paragraph view displays; styling comes from entity + dynamic CSS.
  Folded into update **`9006`**; `image_background_bundle` Twig uses
  `pb_image_field_url` instead of view formatters for background URLs.
- **`pb_content` form display upgrade** — surgical merge preserves site-added
  fields on the node type; single sub-module hook **`9001`** (removed
`9002`/`9003`).
- **The alert removed its own content on a timer nobody could stop** (WCAG 2.2.1
  Timing Adjustable, Level A). The `countdown` dismissal deleted the alert after
  N seconds with no way to turn the limit off, adjust it or extend it. There is
  now a pause/resume control, and it is gated on the countdown **timer**, not on
  the "show countdown label" setting — the timer runs whenever the dismissal
  type is `countdown` with seconds above zero, so an alert with the label
  switched off previously ran the timer and rendered no control at all. The
  countdown also pauses automatically while pointer or keyboard focus is inside
  the alert. Two further Level A findings in the same bundle are fixed with it:
  the close button no longer advertises an `aria-expanded` disclosure state it
  could never honour (dismissal is one-way, and the re-open branch was
  unreachable dead code), and focus is moved to the paragraph wrapper before the
  alert goes `inert`, instead of being dropped to `<body>`.
- **Slideshow slides claimed a tab role with no tab to own them** — slides
  carried `role="tabpanel"` unconditionally, but the `role="tablist"` container
  and its `role="tab"` buttons render only for four of the five bottom-navigation
  values. With `play`, or with the field **unset — the shipped default** — the
  page got tabpanels with no tab and no tablist anywhere, which assistive
  technology cannot make sense of. Those slides are now `role="group"` with
  `aria-roledescription="slide"`, per the ARIA APG carousel pattern. The same
  gate fixed a second defect: `aria-labelledby` was emitted whenever the field
  was merely set, so `play` pointed every slide at a nav-button id that is never
  rendered. Attributes only — no element, class or nesting changes. Verified by
  rendering all six configurations: orphaned tabpanels 2 → 0, dangling
  references 2 → 0, the four working configurations byte-identical.
- **Paragraphs created programmatically got an incomplete stylesheet** — both
  CSS builders read field values behind an `is_string()` gate, but 47 of this
  module's 171 paragraph fields are integer, decimal, boolean or range
  storages, and an integer column holds a real `int` on an entity whose value
  was just set in code (it only becomes a numeric string after a round trip
  through the database). `hook_paragraph_insert()` builds the stylesheet from
  exactly that in-memory entity, so every numeric declaration — `gap`,
  `max-width`, `min-height`, animation durations, sticky offsets, background
  opacity, the `*_active_item` values — was silently dropped for any paragraph
  created by a recipe, a migration or a demo-content script, and stayed dropped
  until somebody re-saved the paragraph through the form. Numbers are now
  coerced; booleans, arrays and objects still emit nothing, the same
  fail-closed rule `pb_css_value` already used.
- **Upgraded sites got different edit forms from new ones** — `9006` wires the
  shared v2 fields onto displays that already exist, so it computed weights
  from what it found, while a fresh install imports them from `config/optional`.
  Measured across all 39 bundles: six `pb_display_*` fields sat on different
  weights on ~30 form displays, and on 4 of 5 sampled bundles that was enough to
  change the **order fields render in** — on `icon_bundle`, `pb_content_body`
  and `pb_content_link` ended up scattered among the display fields. New update
  **`9008`** re-applies the shipped layout (component weights, field group
  weights and child order), leaving site-added fields, fields the site has
  hidden, and site-added group children alone. After it, all 126 displays render
  in the same order on both install paths.
- **Displays could list a field as visible and hidden at once** — the v1 wiring
  path wrote straight into a display's `content` array without clearing the
  matching `hidden` entry, which `EntityDisplayBase::setComponent()` does and
  `preSave()` does not repair. 134 such contradictions across 35 of the 39
  paragraph form displays on an upgraded site, none on a fresh one. The wiring
  helpers now clear it, and `9008` cleans up sites that already ran them.
- **Pre-release hook consolidation** — the parent now ships **`9005`**,
  **`9006`**, **`9007`** and **`9008`** only, plus a post-update for the CSS
  backfill.
  v2's development hooks were fragmented across `9001`–`9015`; the survivors
  were renumbered above v1's floor (see the **`9005`** note below) and the view
  display cleanup folded into `9006`. Full chain in
  `docs/UPGRADE-V1-TO-V2.md`.
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
- **`docs/UPGRADE-V1-TO-V2.md`** — the upgrade guide: prerequisites, backup and
  recovery, the full update-hook chain, and what changes for site builders.
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
- **`PbDynamicUserVarBuilder`** — emits non-colour editor field values into the
  same per-paragraph dynamic CSS file as the colour vars. These are written as
  **real CSS properties** on the element v1 gave a `style=""` attribute
  (`gap`, `max-width`, `min-height`, `background-*`, `perspective`,
  `grid-template-columns`, `font-size`), not as custom properties; the two
  column bundles are the exception and do emit `--pb-col-anim-ms` /
  `--pb-col-sticky-top`. New `PbColorNormalizer` service shared by the colour
  and overlay rgba paths.
- `docs/reference/pb-css-variables.md` — field → CSS reference for the static
  and dynamic layers.
- Unit tests for `PbColorNormalizer` (opacity, hex overlay rgba).

### Changed
- **Outer element ids are one derivable form: `pb-{bundle-slug}-{paragraph id}`.**
  **This is a breaking change for site CSS or JS that names a PB id**, and it
  affects 29 of the 30 bundles that had an id in v1 — the full old → new table
  is in `docs/CSS-CLASS-MIGRATION.md`.

  v1 had thirty bespoke patterns and most were abbreviations rather than the
  bundle name (`pb__nf-`, `pb__accor-wrap-`, `pb__slide-wrap-`, `pb__image-`
  for two different bundles). Nothing could be derived from a bundle machine
  name and nothing could be checked. Two of the thirty had already changed
  before 2.0.0 while the migration doc still promised ids were unchanged, so
  the guarantee that had justified freezing them was already false.

  `parallax_bundle` and `parallax_section_bundle` had shared one id prefix, so
  a parallax id did not say which bundle it was on; they are now distinct. The
  three inner ids whose old names collide with a new outer id moved with them:
  `pb__accor-{id}` → `pb-accordion__sections-{id}`, `pb__tab-{id}` →
  `pb-tabs__tabset-{id}`, `pb__slideshow-{id}` → `pb-slideshow__stage-{id}`.

  The rule is now written down (`docs/PB-V2-CLASS-NAMING-SPEC.md` §3.10) and
  has one implementation, `PbDynamicCssBuilder::outerElementId()`, called both
  by the preprocess hook that writes the attribute and by the CSS builder that
  writes the matching scope — the per-bundle override map they used to disagree
  through is deleted. Per-paragraph CSS regenerates itself; no stored data
  changes; no update hook is needed.
- **The last two off-grammar classes were renamed**: `pb__simple__inner` →
  `pb-simple__inner` and `pb__default__inner` → `pb-default__inner`. They were
  the only `pb__` names left that were ordinary classes rather than stored
  field values, and both carried a double underscore straight after the prefix.
- **One owner for the node-reference conditional fields.** The view-mode field
  widget hook also tried to set `#states` on the four NF reference fields, but
  the element it inspected has no `subform` key, so that branch had never run on
  any site — while `pb-conditional-field.js`, which also clears hidden fields and
  infers the mode from populated ones, did the whole job. The dead branch is
  gone; the hook now only attaches that library.
- **No jQuery on the front end.** Six bundle libraries (tabs, carousel, 3D
  carousel, modal, accordion, icon) and one admin widget library declared
  `core/drupal.ajax`, which depends on `core/jquery` — so any page showing one
  of those paragraphs downloaded jQuery and core's ajax script. None of this
  module's JavaScript uses an ajax API. Both that dependency and the unused
  `core/drupalSettings` declarations are gone.
- **One implementation of `hook_help()`.** The parent module and all 26 bundle
  sub-modules each carried their own near-identical copy of the same
  README-to-help-page renderer. They now share one service, and each module's
  hook class only names its module.
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

### Port detail

> These are implementation notes kept from the v1 → v2 port, module by module.
> They are the *how*, not the *what* — the sections above are the release
> summary. Corrected on 2026-08-04: this block was drafted mid-port and still
> used v1 class names and the pre-consolidation update-hook numbering.

#### Added during the port
- `pb_display_link` / `pb_display_link_hover` shared field storages and
  `simple_bundle` instances. Emit `--pb-lk` / `--pb-lk-h` into the
  per-paragraph dynamic CSS file; foundation CSS in
  `paragraphs-bundles-custom.css` applies them to `<a>` elements inside
  the paragraph. Reusable install helper
  `paragraphs_bundles_pb_link_wire_bundle()` lets each sub-module wire
  the fields when it is ported.
- Per-paragraph dynamic CSS files in `public://paragraphs_bundles/`,
  replacing the v1 inline `style="--pb-bg:rgba(...);"` attribute. Each
  file scopes `--pb-*` declarations to `#pb-{slug}-{id} > .pb-ui-inner`;
  element-id specificity (1,0,0) makes editor choices win without
  `!important`.
- New `--pb-lk` / `--pb-lk-h` link colour variables. Falls back to
  host design system (`--r-lk`) and finally `LinkText` system
  colour. Preserves link affordance independently of paragraph text.
- Drush command `pb:regenerate-css` (alias `pb-regen`) with
  `--purge` to wipe and rebuild every CSS file.
- The update chain that ships is `update_9005()`–`update_9008()` plus one
  post-update. During the port it was fragmented across `9001`–`9015`; those
  development hooks were consolidated and renumbered above v1's floor before
  release, so **`update_9009()` and `update_9010()` do not exist** in the
  shipped module. What they did is now done by `9006`. The authoritative
  chain is in [`docs/UPGRADE-V1-TO-V2.md`](docs/UPGRADE-V1-TO-V2.md):
  - `9005` — shared field storages, title help text, optional flags.
  - `9006` — wire shared v2 fields on every PB bundle, nest the Display tab,
    hide `pb_display_*` on view displays.
  - `9007` — repair v1 config against v2's tightened config schema.
  - `9008` — realign form and view display layout with a fresh install.
  - `post_update` — regenerate every per-paragraph CSS file.
- Reusable install helper
  `paragraphs_bundles_pb_wire_shared_fields_for_bundle()` — single entry
  point for all shared v2 field wiring (no sub-module `.install`
  required).
- `paragraph_bundle_hero` sub-module port (`hero_bundle`): v1 Twig DOM
  preserved; dynamic CSS scoped to
  `#pb-hero-{id} .pb-hero__text-inner` (nested color target).
- `paragraph_bundle_icon` sub-module port (`icon_bundle`): v1 Twig DOM
  preserved; Google Material Symbols libraries + admin assets; dynamic CSS
  on `.pb-ui-inner`; nested Display tab + link/title shared fields.
- `paragraph_bundle_alert` sub-module port (`alert_bundle`): v1 Twig DOM
  preserved (dismissal, countdown, date range, close button); dynamic CSS on
  `.pb-ui-inner`; OOP preprocess + datetime timezone widget + admin JS.
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
  `#pb-modal-{id}-win .pb-modal__content > .pb-ui-inner`; outer-id
  suffix `-win` in `PbDynamicCssBuilder`.
- `paragraph_bundle_accordion` sub-module port (`accordion_bundle` +
  `accordion_section_bundle`): v1 Twig DOM/ARIA preserved; accordion JS;
  dynamic CSS on `#pb-accordion-{id} > .pb-ui-inner`; `--pb-bt-a` from
  `pb_display_accor_active_item` via `BUNDLE_EXTRA_COLOR_FIELDS`.
- `paragraph_bundle_tabs` sub-module port (`tabs_bundle` +
  `tab_section_bundle`): v1 Twig DOM/ARIA preserved; tabs JS; dynamic CSS on
  `#pb-tabs-{id} > .pb-ui-inner`; `--pb-bt-a` from
  `pb_display_tab_active_item`.
- `paragraph_bundle_grid` sub-module port (`grid_bundle`): v1 Twig preserved;
  responsive breakpoint CSS libraries; dynamic CSS on
  `#pb-grid-{id} > .pb-ui-inner`; inline gap on `.pb-grid__wrapper`.
- `paragraph_bundle_carousel` sub-module port (`carousel_bundle`): v1 Twig
  DOM/ARIA preserved; carousel JS; dynamic CSS on
  `#pb-carousel-{id} > .pb-ui-inner`; breakpoint libraries; title block
  weights 1–3 via `wire-bundle-shared-fields.php`.
- `paragraph_bundle_node_reference` sub-module port (`node_reference_bundle`):
  `pb_node_reference` content type and node view modes; v1 Twig DOM preserved;
  dynamic CSS on `#pb-node-reference-{id} > .pb-ui-inner`; conditional NF fields via
  `PbnrFieldWidgetHook` and `pb-conditional-fields` JS; title block weights 1–3.
- `paragraph_bundle_parallax` sub-module port (`parallax_bundle` +
  `parallax_section_bundle`): v1 parallax JS/CSS preserved; wrapper dynamic CSS
  on `#pb-parallax-{id} > .pb-ui-inner`; section colors scoped to
  `.pb-parallax__text`; breakpoint libraries; title block weights 1–3.
- `paragraph_bundle_slideshow` sub-module port (`slideshow_bundle` +
  `slideshow_section_bundle`): v1 slideshow DOM/ARIA/JS preserved; dynamic CSS
  on `#pb-slideshow-{id} > .pb-ui-inner` with `--pb-bt-a` active slide
  color; title block weights 1–3.
- `paragraph_bundle_image_background` sub-module port
  (`image_background_bundle`):
  v1 background/overlay DOM preserved; inline background CSS and overlay rgba;
  responsive bg swap JS; outer id `pb-image-background-{id}`; no title field (verify
  SKIP).
- `paragraph_bundle_image_grid` sub-module port (`image_grid_bundle` +
  `image_grid_section_bundle`): v1 grid/lightbox DOM/JS preserved; dynamic CSS
  on `#pb-image-grid-{id} > .pb-ui-inner`; section split colors (border on
  inner, bg/text on `.pb-image-grid__title`); `PbigPreprocessSectionHook` sets
  lightbox full-size URL; title block weights 1–3 on both bundles.
- `paragraph_bundle_image_overlay` sub-module port (`image_overlay_bundle`):
  v1 overlay DOM/effects CSS preserved; split dynamic CSS (border on inner,
  bg/text on `.pb-overlay__container` and `.pb-overlay__title`); outer id
  `#pb-image-overlay-{id}`; title block weights 1–3.
- `paragraph_bundle_3d_carousel` sub-module port (`3d_carousel_bundle`): v1 3D
  carousel DOM/JS/ARIA preserved; dynamic CSS on `#pb-3d-carousel-{id} >
  .pb-ui-inner`; inline perspective px; breakpoint libraries; no title
  field (wire/verify SKIP).
- `paragraph_bundle_3d_flip_box` sub-module port (`3d_flip_box_bundle`): v1
  flip-box DOM/CSS preserved; dynamic CSS on `#pb-3d-flip-box-{id} >
  .pb-ui-inner`; inline height px + flip axis class on `.pb-3d-flip-box__scene`;
  title block weights 1–3.
- `paragraph_bundle_layout` sub-module port (`one_column_bundle`,
  `two_columns_bundle`, `three_columns_bundle`): v1 column layout DOM/ARIA/JS
  preserved; dynamic CSS on each bundle's `#pb-{layout-slug}-{id} >
  .pb-ui-inner`; breakpoint libraries; column toggle toolbar + sticky
  inline grid vars; title block weights 1–3 on all three bundles.
- All v2 `*.info.yml` files: `package: 'Paragraphs Bundles'` (separate Extend
  list group from core Paragraphs).
- Removed `paragraph_bundle_block.install` — `hook_install()` ran before the
  paragraph type was registered; use optional YAML on enable + parent
  `9006` on upgrade.
- `block_bundle` form/view displays: title tag and title alignment wired
  (Content tab + hidden on view), matching `simple_bundle` / `hero_bundle`.
- Track A sub-modules (`paragraph_bundle_block`,
  `paragraph_bundle_block_content`,
  `paragraph_bundle_contact_form`, `paragraph_bundle_views`,
  `paragraph_bundle_webform`): OOP theme + suggestion + help hooks; optional
  config from v1 plus `pb_display_link` / `pb_display_link_hover`; nested
  Display tab field groups; title tag/alignment on form + hidden on view
  (block pattern); all use parent `paragraph--default.html.twig`.
- Grouping of Additional display fields by widget type (selects, colors,
  numbers, checkboxes, datetime, range, media) for a two-per-row admin layout.

#### Changed during the port
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
  Additional (bundle-specific fields). `update_9006()` applies this to
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
  `.pb-hero__text-inner`). `OUTER_ID_PREFIX_OVERRIDES` keeps v1
  outer ids (e.g. `#pb-hero-{id}`) in sync with preprocess + CSS.

#### Fixed during the port
- `paragraph_bundle_image` Twig: read display list keys from
  `paragraph.field.value` (view display hides `pb_display_*` fields).
- `image_narrow_bundle` / `image_wide_bundle`: restore v1 outer ids
  (`pb-image-narrow-{id}`, `pb-image-wide-{id}`) in Twig + `PbDynamicCssBuilder`
  so dynamic CSS scopes match the DOM.

#### Preserved
- All field machine names (`pb_content_*`, `pb_display_*`).
- All CSS custom properties (`--pb-bg`, `--pb-bg-h`, `--pb-br`,
  `--pb-br-h`, `--pb-tx`, `--pb-tx-h`, `--pb-px*`).
- **Not** class names. This line previously claimed v1 class names were
  preserved; that was true only of the earliest port drafts. v2 renames 299
  structural classes — see
  [`docs/CSS-CLASS-MIGRATION.md`](docs/CSS-CLASS-MIGRATION.md) for the full
  map and the 69 tokens that genuinely did not change.
- Library names `paragraphs_bundles/color-picker` and
  `paragraphs_bundles/opacity-range` (referenced by widget plugins).
- v1's update hooks `9001`–`9004` are **removed**, not ported: v2 declares
  `hook_update_last_removed() = 9004`, so a site below that schema must
  finish upgrading on 1.x first. v2's own chain starts at `9005`.
