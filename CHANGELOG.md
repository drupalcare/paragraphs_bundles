# Changelog

All notable changes to `paragraphs_bundles` are recorded here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [2.0.4] — 2026-10-10

No change to how any page renders. Stylesheet housekeeping and CI coverage.

### Changed

- **Stylelint now checks all 74 stylesheets, and all 74 pass.** CI was
  linting `css/*.css` only - 6 of 74 files - because the job shell has no
  globstar, so `**` matched one level. The bundle and sub-module stylesheets
  are now listed explicitly. The 113 errors this exposed were fixed: property
  order and layout, `rgb()` notation, quote style in the icon font CSS, and
  duplicate selectors in the hero and flip box bundles merged into one block
  each. In the AI bar stylesheet `all: initial` stays the first declaration
  of each rule, where it must be.
- **3D carousel: the control-button selector is defined once.** The prev,
  next and pause buttons were styled by two blocks with the same selector (a
  reset, then the styling). They are now one block, reset first. Measured on
  a 3D carousel at rest, hover and keyboard focus: all 1421 computed values
  read back identical, and the screenshots are byte-identical.
  `PbCssDuplicateSelectorContractTest` now allows no duplicate selector at
  all.
  ⚠ One cascade difference remains, and nothing in PB triggers it: a theme or
  module that adds the utility class `pb-u-hide-0` to a 3D carousel control
  button will now hide that button.

## [2.0.3] — 2026-10-09

### Fixed

- **Hovering a section now recolours all of its text.** The editor's text
  hover colour was set on the section, but titles, paragraphs and fields
  painted their own colour, so they ignored it: hovering a hero box turned the
  box to the hover colour while every line stayed as it was. Text colour is
  now set once on the section and everything inside inherits it - core
  `.field`, and the hero, node reference, modal, image overlay, card and image
  grid bundles.
- **Links inside a modal's text keep their link colour.** The modal repainted
  every element, links included, in the text colour, so links were
  distinguishable by underline alone.
- **Captions and titles inside a clickable card, image or icon stay in the
  text colour.** A link that wraps a whole block (`.pb-ui-link`) now inherits
  the section's colour instead of the theme's link colour. The icon bundle's
  links carry that class now too.

### Added

- `PbCssColourInheritanceContractTest`: fails if any stylesheet paints text
  colour from a `--pb-*` token onto `*`, a text element or a field wrapper
  instead of the section that owns it. `color: inherit` resets stay allowed.

## [2.0.2] — 2026-10-07

### Added

- **`control-btn` class on PB's control buttons**, so a theme that styles
  every `<button>` can leave navigation and media controls alone by excluding
  `.control-btn`. On 25 buttons: carousel, 3D carousel and slideshow controls,
  the 3D flip box toggle, image grid lightbox controls, alert countdown
  pause and close, the column toggles and the modal close button. Not on
  buttons whose look is the design (accordion, tabs, the modal open button).
  The class is added; no existing class, style or behaviour changes.
  `PbControlButtonClassContractTest` checks every shipped button.

## [2.0.1] — 2026-10-07

Run `drush updatedb` after updating.

### Fixed

- **#3629036: two- and three-column sections created before 1.0.17 can be
  edited and saved again.** 1.0.17 made "Toggle animation effect" required
  without filling it on existing sections, so editing those pages failed with
  "Toggle animation effect field is required". Update
  `paragraph_bundle_layout_update_9003` fills the missing value with "slide"
  (how those sections already rendered, so no page changes) on the current
  version and every revision, in batches, and never touches a value already
  set. Thanks shank115 for the first patch on the issue.
- **3D carousel CSS class.** 2.0.0 emitted `paragraph--type--_d-carousel-bundle`
  instead of the 1.x `paragraph--type--3d-carousel-bundle`. Both are emitted
  now, so themes written for either release keep matching.
- **Upgraded sites get the 2.x names "Lightbox Gallery" and "Lightbox Image"**
  for the image grid sections (update `paragraph_bundle_image_grid_update_9002`),
  and the help text of the Simple section's border, border radius, margin,
  padding and shadow fields and Image Background's padding field (updates
  `paragraphs_bundles_update_9012`, `paragraph_bundle_image_background_update_9003`).
  A label or help text the site changed itself is kept. Config translations
  keep their 1.x text; translators update those by hand.

### Documentation

- `docs/CSS-CLASS-MIGRATION.md` lists 14 more 1.x classes the 2.0.0 edition
  missed, including `js-pb-close-alert` and the column toggle icon classes.

## [2.0.0] — 2026-10-05

### Added — the Paragraphs List, moved here from PB Import and rebuilt

- **`/admin/content/paragraphs/paragraphs-list`** now ships in this module, at
  the same address and under Content (admin menu and the sidebar's `content`
  menu). PB Import no longer ships it. Its edit form at
  `/admin/content/paragraph/{paragraph}/edit` returns to the list after saving.
  The delete confirmation is a behavior, not an inline `onclick`, so it works
  under a CSP that forbids inline scripts. The form id is now
  `paragraphs_bundles_paragraphs_list_form`.
- **New columns and tools:** PID (was "ID"), NID and a linked Content title;
  sort by PID, Type, NID, Content title and Label; search by content title
  ("starts with", or the slower "contains") and by label ("contains"); a row of
  checkboxes above the table hides and shows Type, NID, Content title, Label and
  Parent (JavaScript, remembered in the browser); Previous / Next paging instead
  of page numbers, bookmarkable. Layout-only CSS: no colours, so the admin theme
  decides how it looks.
- **Its own permission, `access paragraphs list`** (restricted: the list shows
  unpublished paragraphs too). It replaces `administer site configuration` for
  the list and the undefined `administer paragraphs` for edit. Edit and delete
  also need entity update access, which Paragraphs answers from the host content
  (a paragraph nothing uses is editable with the permission alone). A row you may
  not update has no usable select box and no Edit link, and Delete Selected
  re-checks every id on the server and says how many it skipped. **The
  post-update `grant_paragraphs_list_permission` gives the permission to every
  role that has `administer site configuration`.**
- **Built for large sites:** a derived table, `paragraphs_bundles_list_index`
  (type, root entity, depth, a copy of the Label and of the root's title),
  answers every query; it is rebuilt from the paragraphs' own parent columns on
  save and delete, never edited by hand. The count is cached; paging is by
  keyset. An index failure is logged and queues a rebuild, and never blocks a
  save. **`drush updb` creates the table (9010), adds the title column to a
  table made without it (9011), and post-updates fill it, 500 paragraphs a
  step.** Drush: `paragraphs-bundles:list-index:rebuild` (chunked, resumable) and
  `paragraphs-bundles:list-index:verify` (`--all` for everything; exits non-zero
  on drift). See `docs/PARAGRAPHS-LIST-INDEX.md`.

### Added — 3D carousel: a coverflow geometry

- **New field `pb_display_3d_car_style`** ("Carousel style", Display →
  Additional): **Rotating ring** (default) or **Coverflow**. Update **9003**
  adds it to existing sites and writes `ring` onto existing carousels. The
  field is optional; carousels with no value render as the ring, as before.
- **The ring's markup is unchanged.** The style stylesheets load as two
  separate libraries, so a ring page never downloads the coverflow rules.
- In a coverflow, the card that wraps from one end to the other fades out,
  moves while invisible and fades back in, instead of sliding across the fan.

### Fixed — per-paragraph generated CSS did not load on a site installed in a subdirectory

- The library registered for each generated stylesheet used the file's URL
  (`/subdir/sites/…/files/paragraphs_bundles/pb-….css`). Core's library parser
  strips the leading slash of such a key without removing the base path, so on
  a site served from a subdirectory the browser asked for
  `/subdir/subdir/sites/…` and got a 404: every paragraph styled by generated
  CSS (the flip box's scene height, colours, spacing) rendered unstyled. It now
  registers the `public://` URI, which the asset renderer turns into the right
  URL. No change on a site at the web root. Rebuild caches after updating
  (`drush cr`) so the library definitions are rebuilt.

### Fixed — Drupal 12: renaming a paragraphs field or type did not refresh the Paragraphs List index

- The list index rebuild is queued from `field_config_update` and
  `paragraphs_type_update` by comparing the entity with its original. Both hooks
  read the magic `->original` property, which Drupal 12 removed, so on Drupal 12
  the comparison saw nothing and the rebuild was never queued. They now call
  `getOriginal()` (available since Drupal 11.2). No change on Drupal 11.

### Fixed — a failed list-index write could still break the editor's save on MySQL

- When an index write failed during a content save, the failure path queued a
  rebuild inside the save's transaction. On a site whose `queue` table did not
  exist yet, creating it is DDL, which MySQL/MariaDB commit implicitly; Drupal
  11.4 then threw `TransactionOutOfOrderException` and the save failed. The
  failure path now only logs and sets a State flag; the rebuild is queued at
  kernel termination (`PbListIndexHook` is a `needs_destruction` service), after
  the transaction. Run `drush cr` after updating (service tag added).

### Fixed — Three columns: a default layout the field does not offer

- `pb_display_three_columns` shipped the default `l4-l4-l4`; its options are
  `l4_l4_l4` and friends. Pages rendered correctly (the template turns `_` into
  `-`), but a section created without the form stored a value the field does
  not offer, and its edit form showed no layout chosen. Fixed in the shipped
  config; `paragraph_bundle_layout_update_9002` corrects the default and every
  stored value (25 sections on the test site). `PbListDefaultsContractTest`
  now fails on any list field whose shipped default is not one of its keys.

### Fixed — 3D carousel: keyboard focus at the ends of a ring

- Reaching the first or last slide with the keyboard hid the arrow that had
  focus, dropping the user to the top of the page. Focus now moves to the
  opposite arrow. This was inherited from 1.x.

### Added — AI (optional)

- **`paragraphs_bundles_ai`** — optional AI sub-module. A bar at the foot of
  a page you can edit: choose a section, say how many items go inside it, say
  what it should say, pick a style, and it writes the section into the page.
  The page is saved as a **new revision**; nothing is published for you.
  Requires `drupal/ai`; the parent module does not.
- `composer.json` suggests `drupal/ai`, naming the sub-module that needs it.

  ⚠ **Corrected 2026-10-09:** this entry first listed three AI agent tools
  (`pb_resolve_target`, `pb_draft_field_text`, `pb_create_sections`) and an
  `ai_agents` agent config. Those were removed before 2.0.0 was released and
  never shipped; the bar above is what 2.0.0 contains.
- **Fixed in `SectionComposer` and `SectionEditor`: a non-scalar value reached a
  string cast.** The values being written carry whatever the model answered, so
  a field value can be an array or an object, and the allowed-values check cast
  it with `(string) $value`. The `is_scalar()` guard written to catch exactly
  that sat ten lines further down, *after* the use, so it only ever ran on
  values that had already survived the cast. The guard is now first in both.
  Found by PHPStan at `level: max`, not by a test.

  ⚠ **Corrected 2026-09-24:** this entry first described that cast as "a fatal
  on an array". Measured on PHP 8.5.10, it is not. An array casts to the literal
  string `"Array"` with only a warning, and just an object without `__toString()`
  throws. **The array arm is the quieter one and the worse one** — `"Array"` is
  saved into a section, and a new revision written, as though it were the copy
  that was asked for. `is_scalar()` is false for both, so one guard covers both.

- **`SectionEditor` has tests** — `SectionEditorTest`, 7 cases. It had none of
  any kind while being the one service that rewrites a paragraph somebody is
  looking at, which is why the defect above survived there after being fixed in
  its sibling. The array case is red-proofed: removing the guard turns the suite
  red with one failure, one error and PHP's own "Array to string conversion"
  warning. It also covers the cross-page check that stops a submitted paragraph
  id reaching a section on somebody else's page.

### Removed — the Colour Swatch formatter

- **`color_swatch_formatter` is gone**, and with it the last inline `style`
  attributes Paragraphs Bundles emitted. It built four of them from PHP render
  arrays: three entirely static, and one carrying an arbitrary user-chosen hex.
  A field formatter has no per-entity generated CSS file to move that hex into —
  unlike a paragraph — so splitting it would have left PB permanently one short
  of zero inline styles.

  It was assigned to **no displays**, had no test and no documentation, and the
  `paragraphs_bundles_rgb` field type still ships **`color_text_formatter_hex`**
  and **`color_text_formatter_rgb`**, neither of which inlines anything. If you
  were using the swatch, switch the display to one of those.

  `paragraphs_bundles.info.yml` advertises *"per-paragraph dynamic CSS files (no
  inline styles)"*. That sentence is now true: verified under an enforced
  `style-src: self` policy with a known-positive probe, PB contributes **zero**
  markup style attributes to a page rendering every bundle with every field
  filled.

- **`PbDynamicCssBuilder::buildInlineCustomPropertiesStyle()` removed** — the
  one API in this project whose job was manufacturing inline style strings. Its
  last caller went in 2026-09-20 when the image-overlay colours moved into the
  generated stylesheet. Removed while 2.x is unreleased, when an interface
  change is still free.

### Added — APEX responsive images (optional)

- **`paragraphs_bundles_apex`** — optional bridge that renders a PB media image
  through APEX's layout-aware responsive image formatter, which sizes pictures
  from the width of the box they land in rather than from viewport breakpoints.
  It adds three media view modes — `pb_apex_wide`, `pb_apex_narrow` and
  `pb_apex_card` — and changes nothing until a site selects one.
  Requires `drupal/apex`; the parent module does not.
- **A missing APEX mode is now reported, instead of silently blanking the
  image.** `ApexResponsiveImageFormatter` returns an empty render array for a
  responsive mode it cannot load — no error, no log entry, the picture simply
  disappears — and those modes are config entities APEX ships a delete form for.
  A `runtime_requirements` hook now names each affected display and the missing
  mode on the status report. Drupal's config dependency system cannot cover
  this: measured 2026-09-23, a hand-added `dependencies.config` entry is dropped
  by `EntityDisplayBase::calculateDependencies()` on the very next save, because
  the formatter declares no dependency on the mode it names.
- **Removed a `hook_update_N` that could never run.** It shipped in the module's
  first release to backfill config, but core records a new module's schema
  version as the highest available update number at install time
  (`ModuleInstaller.php:407-421`), so `drush updatedb` skipped it for every site,
  forever. Measured on a real install: recorded version 10001, only available
  update 10001. The README told people to run it, and no longer does. The
  idempotent backfill `hook_install()` performs is unchanged.
- **Dropped `search_api_excerpt` from three shipped view displays.** A
  site-export leak: that pseudo-field exists only when `search_api` is
  installed, and this bridge neither depends on nor uses it.
- **Corrected a false performance claim in the README.** It said PB's
  `pb_media_responsive_wide` sets `image_loading: eager`, and built its
  "eager/lazy decision moves" section on that. Measured against both the
  shipped config and a running site: **every** PB media display carrying the
  setting is `lazy`, that one included. The real difference is that PB's
  displays expose `image_loading` and the APEX formatter exposes none — it is
  always lazy, with LCP promotion left to `apex_performance`. A drift test now
  pins the config and the sentence together, because nothing in the repository
  could previously have gone red for it.


A ground-up rewrite of the suite for Drupal 11.3+ / 12, shipped as a **drop-in
upgrade from 1.x**: no content or field changes are required, and every v1
bundle keeps its rendered DOM *structure*. Read
**`docs/UPGRADE-V1-TO-V2.md`** before upgrading — the upgrade is one-way, and
it has a prerequisite (your site must already be on the last 1.x release).

Three things do change for site builders, all detailed below: **structural CSS
class names were renamed**, **outer element ids were standardised** (both in
`docs/CSS-CLASS-MIGRATION.md`, which lists all 304 class renames, the 69 frozen
tokens that did not change, and the full id table), and two things v1 bundled
are no longer in the package (see **Removed**).

### Performance — the icon font is now subsetted to the icons you use

`paragraph_bundle_icon` bundles the three Material Symbols variable fonts, and
each one carries all 4,284 icons: 3,411,436 bytes for Outlined, 4,644,048 for
Rounded, 3,012,704 for Sharp. A page draws a handful. Google's own guidance
labels that configuration **not recommended** and names subsetting as the fix.

Icon pages now request only the icons the site actually uses, through Google
Fonts' `icon_names` parameter. Measured on a page with an outlined icon:
**9,118 bytes — 686 of CSS and 8,432 of font — against 3,411,436 before, 374x
smaller.** The bundled font is no longer requested at all.

- **Nothing changes for authors.** All four variable axes — Fill, Weight, Grade
  and Optical Size — still work; the computed `font-variation-settings` on the
  glyph is unchanged. Icon names are still free text.
- **The bundled fonts still ship, and are a real fallback.** The local
  `@font-face` is never removed; the subset is added after it. Measured in
  Chrome 2026-09-23 with cache-busted URLs and the requests read off the
  network: two `@font-face` rules for one family cost **one** request, for the
  last declared, and the browser fetches the earlier rule only when the last
  one fails. So the saving above is unaffected, and a site that cannot reach
  Google paints from the bundled file. Removing the fonts is what broke
  upgrades once before; they stay.
- **Icon names are validated** against the 4,284 names Google publishes, shipped
  as `modules/paragraph_bundle_icon/data/material-symbols-names.txt`. This
  matters because an unrecognised name is dropped silently by the API, and a
  request where *every* name is unrecognised falls back to the complete 344 KB
  font.
- **`font-display: block` replaces `swap`** for icons. With `swap` the browser
  paints the fallback face while loading, and for a ligature icon font that
  paints the ligature TEXT — an icon set to `home` briefly showed the word
  "home".
- **Sites enforcing a content policy** need `https://fonts.googleapis.com` and
  `https://fonts.gstatic.com` allowed for the stylesheet and the font file. This
  module emits no policy of its own.
- The template, `paragraph_bundle_icon.libraries.yml` and every
  `attach_library()` call are unchanged; the swap happens in
  `hook_library_info_alter()`.
- **`$entity->getOriginal()` replaces the magic `$entity->original`**, which is
  deprecated in Drupal 11.2 and **removed in 12.0**
  (`EntityBase::__get()`). The old form was worse than a notice here: written
  as `$entity->original ?? NULL` it fired `__isset()` *and* `__get()`, so it
  raised the deprecation twice — and on Drupal 12 it would not fatal, it would
  quietly return NULL, making the "has the icon actually changed?" comparison
  never match and rebuilding the whole library registry on every icon save.
  A silent performance regression rather than a visible break.
- **The "nothing changed, do not rebuild" branch now has a test.** It never had
  one: every existing assertion checked that something *does* invalidate, so
  the early return could have been deleted with the suite staying green.
- **A site with very many distinct icons no longer gets a 4 MB font.**
  Measured against the live API 2026-09-23 by bisection: `icon_names` up to
  4,092 bytes returns a real subset, but at 4,114 bytes Google returns
  **HTTP 200 and the complete 4,001,724-byte font** — heavier than the
  3,411,436-byte file this module bundles — and only around 4,400 bytes does it
  finally return 400. The middle case is silent: the status code, the CSS and
  the browser all look correct while the page is larger than if subsetting had
  never run. `buildUrl()` now caps `icon_names` at 4,096 bytes and attaches no
  subset above it, so the bundled font renders every icon. The list is *not*
  truncated to fit: the Google stylesheet would still win the family and would
  simply lack the glyphs that were cut, losing those icons with no fallback.

⚠ **Sites without outbound access to fonts.googleapis.com** fall back to the
bundled font automatically: the browser fetches the local `@font-face` once the
Google one fails. Measured in Chrome only; other engines are unverified. See the
sub-module README.

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
Two things that 1.x shipped are no longer in the package. Both are
user-visible; read this section before upgrading.

> **The Material Symbols fonts are NOT among them.** An earlier 2.0.x-dev
> commit removed the three bundled `.woff2` files and required every site to
> supply the font itself. **That was reverted before release**, because
> upgrading from 1.x silently turned every icon into its ligature name in plain
> text — a `home` icon rendering the word "home" — with no update path and
> nothing logged. The fonts ship, and icons keep working on upgrade.

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
- **The 3D flip box's control showed the wrong state, and its icon said the
  wrong thing.** The glyph was two panels either side of a dashed mirror axis —
  what design tools use for *flip horizontal*, i.e. mirror an object — and at
  the 24px it renders at, the 3px dashes aliased into a smudge while the solid
  panel read as an off-centre blob. It is now a card with an arrow turning over
  it: one stroke weight, no sub-pixel detail, and it means "show the other
  side". Separately, the icon only reflected an explicit button press, so
  **hovering the box turned it over while the control still drew the
  front-facing icon**; the icon now rotates from the same value that decides
  which face is presented, so it follows hover and focus too. The button's
  spoken label follows that value as well — "Show the back of X" / "Show the
  front of X" instead of a fixed "Flip X", because `aria-expanded` alone says
  open/closed, which does not describe a card with two equal sides. The icon
  is no longer rotated 90° for `flip-x`: that made sense for a mirror line and
  only pointed the arrow sideways for an arrow.
- **The carousel had no accessible name.** Its slide container is
  `role="group"` with `aria-roledescription="carousel"`, which requires one,
  and it pointed `aria-labelledby` at `index-{id}` — an element only the *3D*
  carousel renders. The reference has been dangling since v1, so screen
  readers announced an unnamed carousel. It now carries a direct
  `aria-label`. Found by the id sweep, not by the audit.
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
  stylesheets set the icon family with no generic fallback after it, so a site
  that overrides or fails to load the family fell through to an unspecified
  font. A `sans-serif` fallback is now named explicitly.
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
  and its `role="tab"` buttons render only for four of the five
  bottom-navigation
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
- **Outer element ids are one derivable form: `pb-{bundle-slug}-{paragraph
  id}`.**
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
  through is deleted. No stored data changes.

  Each paragraph's generated stylesheet carries the id as its scope selector,
  so those files are rewritten by the upgrade: `drush updb` ends with
  `paragraphs_bundles_post_update_regenerate_dynamic_css`. A site coming from a
  `2.0.x-dev` snapshot instead of from 1.x has already recorded that
  post-update as run and should rebuild once with
  `drush pb:regenerate-css --purge`; otherwise its paragraphs keep stylesheets
  scoped to the old ids and render without their editor colours.
- **Eight un-prefixed DOM ids are now inside the `pb-` namespace.** The
  carousel, 3D carousel and slideshow emitted `prev-{id}`, `next-{id}`,
  `btn-{id}`, `co-{id}`, `index-{id}`, `item-{id}-{n}`, `label-item-{id}-{n}`
  and `play-pause-{id}`. An id is global to the document: a host theme or
  another module can own `#next-42` just as legitimately, and whichever loses
  is broken with no error anywhere. They are now
  `pb-carousel__prev-{id}`, `pb-3d-carousel__play-pause-{id}`,
  `pb-slideshow__play-pause-{id}` and so on. The 3D carousel's ring id moved
  with them (`pb-3d-carousel-ring-{id}` → `pb-3d-carousel__ring-{id}`), and its
  JavaScript, which derives the other four ids from that one, was updated in
  the same commit.
- **The last two off-grammar classes were renamed**: `pb__simple__inner` →
  `pb-simple__inner` and `pb__default__inner` → `pb-default__inner`. They were
  the only `pb__` names left that were ordinary classes rather than stored
  field values, and both carried a double underscore straight after the prefix.
- **One owner for the node-reference conditional fields.** The view-mode field
  widget hook also tried to set `#states` on the four NF reference fields, but
  the element it inspected has no `subform` key, so that branch had never run on
  any site — while `pb-conditional-field.js`, which also clears hidden fields
  and
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
  dynamic CSS on `#pb-node-reference-{id} > .pb-ui-inner`; conditional NF fields
  via
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
  responsive bg swap JS; outer id `pb-image-background-{id}`; no title field
  (verify
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
