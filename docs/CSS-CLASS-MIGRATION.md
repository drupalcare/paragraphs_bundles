# CSS class migration: paragraphs_bundles v1 → v2

**Ship with v2.** This is the authoritative reference for every
**structural** CSS class rename between v1 contrib and v2. Sub-themes
and custom modules that target PB class names must update overrides
using the tables below.

These tables are the shipped record. `drush pb:migrate-theme` applies them
for you (see "Before you start"); `src/Migration/PbClassRenameMap.php` holds
the same rows, and a test fails when the two disagree.

---

## Before you start: let Drush do the renaming

You do not need to apply the tables below by hand. Paragraphs Bundles 2.x
ships a Drush command, `pb:migrate-theme`, that reads your theme's CSS, Twig
and JavaScript files and changes the old v1 names to the v2 names for you.
It never touches `.yml` files, minified files (`*.min.*`), or the
`node_modules`, `vendor`, `dist` and `.git` folders, and it does not follow
symbolic links.

Run these steps in order. You can run Drush from any folder of the site:
the project folder (the one with `composer.json`, which holds `web/`), `web/`
itself, or a multisite folder such as `web/sites/example`. Add `--uri` for a
multisite, as you do for any Drush command. The examples use a theme called
`mytheme`.

The target can be:

- a theme's machine name: `mytheme`;
- a folder path: `web/themes/custom/mytheme` or `web/modules/custom/my_module`;
- a whole folder of themes: `web/themes/custom` migrates every theme in it.

A folder path is looked up from the folder you are in, then from the project
folder, then from the Drupal root, so `web/themes/custom/mytheme` and
`themes/custom/mytheme` both work from anywhere. If nothing matches, the error
lists every path it tried.

The command only works inside the Drupal root. If your theme folder is a
symbolic link to a folder outside the root (common with Composer path
repositories), it is refused: run the command on a copy of the theme inside
the root. Drupal core, Paragraphs Bundles itself and the public files folder
are refused too.

### Step 1. Save a copy of your theme

The command edits files in place. Commit the theme to Git first, or copy it:

```bash
git add web/themes/custom/mytheme && git commit -m "Theme before PB v2 class migration"
```

### Step 2. Do a dry run

A dry run changes nothing. It counts what would change:

```bash
drush pb:migrate-theme mytheme
```

The output looks like this:

```text
css/overrides.css: 6 line(s)
js/card.js: 2 line(s)
templates/card.html.twig: 2 line(s)
  - unknown-v1-token      1  v1-style tokens not in any table (may be theme-owned; check by hand)
  - removed               1  v1 classes with no v2 element
  - value-in-template     1  Display-value classes in Twig/JS (often stored-value comparisons; change only if used as a CSS class)
  - context-dependent     1  Context-dependent classes (pick the right variant)
  - ambiguous-id          1  v1 ids shared by two bundles (pick the right one)
 [success] Dry run: 10 line(s) in 3 file(s) would change; 5 item(s) need manual review; 0 file(s) skipped.
 [success] Nothing was written. Add --report=pb-report.md to list every change, then --apply to write them.
```

"Skipped" counts symbolic links and hard-linked files inside the theme. The
command never follows or edits them; the report lists them.

### Step 3. Read the report

Run the dry run again with `--report` to get every change, line by line, in
a new Markdown file:

```bash
drush pb:migrate-theme mytheme --report=pb-report.md
```

The report holds lines of your theme's source code, so it must be written
outside the web root, where no visitor can download it. On a Composer site
the project folder is outside the web root (`web/` is the web root). Run from
the project folder, `pb-report.md` lands next to `composer.json`. Run from
inside `web/` (for example `web/sites/example`), a plain file name would land
in the web root, so the command writes it to the project folder instead and
says so. On a site with no Composer project folder, give a full path outside
the web root, for example `--report=/home/me/pb-report.md`.

The command refuses a report path inside the Drupal root or the public files
folder, refuses to overwrite an existing file, and checks the folder is
writable before it reads any theme file. The report is written even in a dry
run (nothing in the theme changes) and only you can read it (mode 0600). The
command prints where it wrote it. Run from `/var/www/example`:

```text
 [success] Report written to /var/www/example/pb-report.md
```

Run from `/var/www/example/web/sites/example` with the same option:

```text
 [success] Report written to /var/www/example/pb-report.md (not /var/www/example/web/sites/example/pb-report.md: that is inside the web root, so it went to the project folder)
```

Delete the report when you are done. Each change shows the old line and the
new line:

```text
## css/overrides.css

- L2
  - `.pb__card .pb__card-image {`
  + `.pb-card .pb-card__image {`
```

The end of the report has a "Needs manual review" section. Step 7 explains
it.

### Step 4. Apply the changes

```bash
drush pb:migrate-theme mytheme --apply
```

Run it as the user who owns the theme files (the same user you edit them
with), not with `sudo`. Each changed file is replaced by a new one owned by
whoever runs the command; a file owned by someone else, or a read-only file,
is skipped with a warning and listed in the report instead.

Symbolic links inside the target are never followed. When you migrate a
whole folder such as `web/themes/custom`, a theme in it that is a symbolic
link is skipped and counted in the summary; run the command on that theme's
real folder if it is inside the Drupal root.

The summary line now says `Applied:`. Each file is replaced in one step, so a
full disk leaves the old file in place instead of a half-written one. If the
command is stopped in the middle (power loss, `kill -9`), a hidden temporary
file named `.pbmig` plus random letters can be left in a theme folder. It is
safe to delete. Running
the same command a second time changes nothing (`Applied: 0 line(s) in 0
file(s) changed`), so it is safe to run again.

To change only some file types, add `--ext` with a comma-separated list. Only
`css`, `scss`, `sass`, `less`, `twig` and `js` are accepted; anything else
(for example `yml` or `php`) is refused before any file is read:

```bash
drush pb:migrate-theme mytheme --ext=css,scss --apply
```

### Step 5. Clear the caches

Drupal caches compiled Twig templates and aggregated CSS and JavaScript:

```bash
drush cache:rebuild
```

### Step 6. Check your pages

Open the pages that use Paragraphs Bundles and compare them with the live
site. Check the layouts, colors, sliders, tabs and accordions. If something
looks wrong, compare the file with the copy from step 1 (`git diff
web/themes/custom/mytheme`).

### Step 7. Fix the report-only items by hand

The command does not guess. When a name could mean two things, it leaves the
line alone and lists it in the report. What to do with each kind:

| Report section | What it means | What to do |
|---|---|---|
| `unknown-v1-token` | A `pb__` name that is not in any table below. Your theme may have made it up itself (for example in its own templates). | If your theme defines the class, leave it. Otherwise search the tables below. |
| `removed` | The element no longer exists in v2. | Delete the rule, or move it to the class the table names. |
| `value-in-template` | A value such as `pb__round_large` in Twig or JavaScript. This is usually a saved field value being compared (`== 'pb__round_large'`), which must not change. | Leave comparisons alone. If the string is used as a CSS class, change it using the "Value-driven utility classes" table. |
| `context-dependent` | One v1 name became two v2 names, for example `two-l6_l6` became `pb-two-columns--l6-l6` or `pb-card-two-columns--l6-l6`. | Pick the one that matches the bundle the rule styles. |
| `generic-token` | A short word such as `active` or `dot` next to a PB class. The command only renames it when it is joined to a PB class, as in `.pb-slideshow__number.dot`. | If the word belongs to the PB element, rename it using the "Manual-only renames" table. |
| `ambiguous-id` | `#pb__image-…` and `#pb__parallax-…` were each used by two bundles in v1. | Use the "Outer element ids" table to pick the right new id. |
| `pcre-error` | A line too long or complex for PHP's pattern engine. It was left unchanged. | Check that line by hand against the tables below. |
| `scss-nesting` | A Sass or Less selector built from its parent with `&` or `#{}`, inside or next to a PB class. For example `.pb__card { &-image {} }` built `.pb__card-image` in v1. The parent is renamed to `.pb-card`, so `&-image` would now build `.pb-card-image`, not the v2 `.pb-card__image`. | Rewrite the nested selector by hand, for example `&__image`. |
| `file-reference` | A file name that contains a v1 name, such as `url(../images/pb__card.png)` or `include 'pb__card.html.twig'`. The command does not rename files, so it leaves the reference alone. | Leave it, or rename the file and the reference together. |

After your edits, run step 2 again. The dry run should report `0 line(s)`.

### What a missed rename costs

No stored content or field value changes. A missed rename means a style stops
applying, never lost data. The tables below are the full record of every
rename, if you want to check a single name by hand. Several v1 names are
prefixes of others (`pb__card` and `pb__card-image`), so a plain
find-and-replace corrupts the longer name; the command matches whole names
only.

---

## Summary

| | Count |
|---|------:|
| Structural class renames (v1 → v2) | **199** |
| Manual-only renames (below) | **119** |
| Frozen tokens (unchanged) | **69** |

That is 318 rows and **313 distinct v1 names**. A few names appear in both a
per-bundle table and a thematic one. One of them, `hide-{delay}`, was removed
rather than renamed.

Every v2 name in the first edition of these tables was checked against the
shipped source on 2026-08-04: **all 299 existed then**. Every frozen token was checked the same way, and
the ones that turned out to be renamed classes rather than stored values were
moved into the rename tables where they belong.

Fourteen rows were added for 2.0.1 (2026-10-07): the column toggle icons, the
alert close hook and two 3D carousel classes. They were found by comparing
every page of a v1 site with the same pages after upgrading, completed from
the v1 source, and their v2 names were checked against the shipped source the
same day: 313 names in all, one of them removed. The column toggle icon classes
are written by the SVG files under `paragraph_bundle_layout/svg/`, which
`pb-col-toggle-icons.html.twig` includes, so search those as well as the
templates.

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
| Display list field classes | `pb__pmw_500`, `pb__padding_16`, … | **Same**
(frozen) |
| Breakpoint classes | `pb__576` … `pb__1400` | **Same** (frozen) |
| Admin form / widget CSS | `pb__tab-display`, color picker, … | **Same**
(frozen) |

**Two inner wrappers did change**, and they are easy to miss because they are
the last `pb__` names that were ordinary classes rather than stored values:

| v1 class | v2 class |
|---|---|
| `pb__simple__inner` | `pb-simple__inner` |
| `pb__default__inner` | `pb-default__inner` |

They carry no stored-value, config or asset-library coupling, which is why
they could be renamed when the rest of the `pb__` family could not, and their
old names broke the grammar twice over: a double underscore straight after the
prefix, and a second one before the element. `pb-simple__inner` is a plain BEM
element of the `pb-simple` block, like every other inner wrapper in the module.

Frozen tokens are output verbatim from Display tab field values. See
[Display field classes (unchanged)](#display-field-classes-unchanged) below.

---

## Outer element ids (v1 → v2)

**This is the one section that is not about classes, and the one most likely
to affect a site that has never touched a PB class.** If your CSS targets a
single paragraph by id, as in `#pb__accor-wrap-42 { … }`. Read this table.

### The rule v2 follows

> **Every DOM id Paragraphs Bundles emits is `pb-` + the bundle machine name
> in kebab-case + `-{paragraph id}`.** No abbreviations, no per-bundle
> exceptions. Given a bundle machine name you can write its id without
> looking anything up: `accordion_bundle` → `#pb-accordion-42`.

Secondary ids inside a bundle follow the same stem plus the BEM element they
sit on: `#pb-accordion__sections-42`, `#pb-tabs__tabset-42`,
`#pb-slideshow__stage-42`.

The rule is stated normatively in
[`PB-V2-CLASS-NAMING-SPEC.md`](PB-V2-CLASS-NAMING-SPEC.md) (**L1 Identity**)
and enforced in one place in code —
`PbDynamicCssBuilder::outerElementId()`, which both the preprocess hook (which
writes the attribute) and the dynamic-CSS builder (which writes the matching
`#…` scope) call. Neither can drift from the other.

### Why it changed, when so much else was frozen

v1 had **30 bespoke id patterns** and most were abbreviations of a bundle name
rather than the name: `pb__nf-` for node reference, `pb__accor-wrap-` for
accordion, `pb__slide-wrap-` for slideshow, `pb__image-` for *both* the image
bundle and the card image section. Four bundles already used `pb-`. There was
no rule to state, so nothing could be derived and nothing could be checked.

Freezing them was considered and rejected for three reasons:

1. **The freeze was already broken.** `parallax_bundle` and
   `3d_carousel_bundle` had changed id before 2.0.0, and the migration doc
   still said ids were unchanged. A promise that is already false for two
   bundles is not protecting anyone; it is hiding a breakage.
2. **`parallax_bundle` and `parallax_section_bundle` shared one prefix**, so
   the id did not even identify the bundle. It does now.
3. **A major release is the only place this is allowed.** Ids are a public
   DOM contract. Changing them in 2.0.0 costs a documented find-and-replace;
   changing them later would be a breaking change in a stable series.

Nothing in the database changes. The only thing that can break is **your own
CSS or JS that names a PB id**, and the table below is the complete list of
what to change.

### Your per-paragraph CSS files are rewritten by the upgrade

Each paragraph's editor colors live in a generated stylesheet under
`public://paragraphs_bundles/`, and that file **contains the id as its scope
selector**. A file written by v1 says `#pb__hero-9 > .pb-ui-inner`, so until it
is rewritten it no longer matches the `pb-hero-9` the page now renders. The
paragraph would render with its colors silently missing.

The v1 → v2 upgrade handles this: `drush updb` ends with
`paragraphs_bundles_post_update_regenerate_dynamic_css`, which rewrites every
file. **Nothing is required of you.**

The one case that needs a manual step is **coming from a `2.0.x-dev`
snapshot** rather than from 1.x: that post-update has already been recorded as
run on your site, so it will not run again. Rebuild the files once:

```bash
drush pb:regenerate-css --purge
```

`--purge` also clears stylesheets left behind by paragraphs that no longer
exist. Verified on a live site: before regenerating, eight files still scoped
to `#pb__…`; after it, every file's selector matched the id its paragraph
renders.

### The 30 ids that existed in v1

| Bundle | v1 id | 2.0.0 id |
|---|---|---|
| `3d_carousel_bundle` | `#pb__caro-wrap-{id}` | `#pb-3d-carousel-{id}` |
| `3d_flip_box_bundle` | `#pb__3d-flip-box-{id}` | `#pb-3d-flip-box-{id}` |
| `accordion_bundle` | `#pb__accor-wrap-{id}` | `#pb-accordion-{id}` |
| `alert_bundle` | `#pb__alert-{id}` | `#pb-alert-{id}` |
| `card_image_section_bundle` | `#pb__image-{id}` | `#pb-card-image-section-{id}` |
| `card_text_section_bundle` | `#pb-card-text-section-{id}` | **unchanged** |
| `card_three_columns_bundle` | `#pb__card-three-columns-{id}` | `#pb-card-three-columns-{id}` |
| `card_two_columns_bundle` | `#pb__card-two-columns-{id}` | `#pb-card-two-columns-{id}` |
| `carousel_bundle` | `#pb__carousel-{id}` | `#pb-carousel-{id}` |
| `grid_bundle` | `#pb__grid-{id}` | `#pb-grid-{id}` |
| `hero_bundle` | `#pb__hero-{id}` | `#pb-hero-{id}` |
| `icon_bundle` | `#pb__icon-{id}` | `#pb-icon-{id}` |
| `image_background_bundle` | `#pb__image-bg-{id}` | `#pb-image-background-{id}` |
| `image_bundle` | `#pb__image-{id}` | `#pb-image-{id}` |
| `image_grid_bundle` | `#pb__image-grid-{id}` | `#pb-image-grid-{id}` |
| `image_grid_section_bundle` | `#pb__image-grid-section-{id}` | `#pb-image-grid-section-{id}` |
| `image_narrow_bundle` | `#pb__image-n-{id}` | `#pb-image-narrow-{id}` |
| `image_overlay_bundle` | `#pb__image-overlay-{id}` | `#pb-image-overlay-{id}` |
| `image_wide_bundle` | `#pb__image-w-{id}` | `#pb-image-wide-{id}` |
| `modal_bundle` | `#pb__modal-id-{id}` | `#pb-modal-{id}` |
| `node_reference_bundle` | `#pb__nf-{id}` | `#pb-node-reference-{id}` |
| `one_column_bundle` | `#pb__one-column-{id}` | `#pb-one-column-{id}` |
| `parallax_bundle` | `#pb__parallax-{id}` | `#pb-parallax-{id}` |
| `parallax_section_bundle` | `#pb__parallax-{id}` | `#pb-parallax-section-{id}` |
| `simple_bundle` | `#pb__simple-{id}` | `#pb-simple-{id}` |
| `slideshow_bundle` | `#pb__slide-wrap-{id}` | `#pb-slideshow-{id}` |
| `tabs_bundle` | `#pb__tabs-{id}` | `#pb-tabs-{id}` |
| `three_columns_bundle` | `#pb__three-columns-{id}` | `#pb-three-columns-{id}` |
| `two_columns_bundle` | `#pb__two-columns-{id}` | `#pb-two-columns-{id}` |
| default template (any other bundle) | `#pb__default-{id}` | `#pb-default-{id}` |

**29 changed, 1 unchanged.** The modal dialog keeps its `-win` suffix, so
`#pb__modal-id-42-win` becomes `#pb-modal-42-win`.

**Ten bundles gained an id in v2** and had none in v1, so nothing can break:
`accordion_section`, `block`, `block_content`, `contact_form`, `link`,
`link_section`, `slideshow_section`, `tab_section`, `views`, `webform`.

### Secondary ids that also moved

| Element | v1 id | 2.0.0 id |
|---|---|---|
| Accordion section host | `#pb__accor-{id}` | `#pb-accordion__sections-{id}` |
| Tabs tabset host | `#pb__tab-{id}` | `#pb-tabs__tabset-{id}` |
| Slideshow stage | `#pb__slideshow-{id}` | `#pb-slideshow__stage-{id}` |

These three had to move regardless: each one's old name is now the *outer* id
of its own bundle, and a page cannot carry the same id twice.

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
| `pb__accor-btn` | removed in 1.0.4. It wrapped each section's button in 1.0.0 to 1.0.3; target `pb-accordion__button` instead |
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
| `js-pb-close-alert` | `pb-alert__close` (v2's script uses this class too) |

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
| `hide-{delay}` | removed. The pause button renders only when autoplay is on; the delay is on `data-rotation-delay` |
| `item-{id}-{n}` | `pb-3d-carousel__slide`. The slide id is now `#pb-3d-carousel__slide-{id}-{n}` |
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
| `pb-col-toggle__icon` | `pb-layout-col__toggle-icon` |
| `pb-col-toggle__icon--angle` | `pb-layout-col__toggle-icon--angle` |
| `pb-col-toggle__icon--angle-collapse` | `pb-layout-col__toggle-icon--angle-collapse` |
| `pb-col-toggle__icon--angle-expand` | `pb-layout-col__toggle-icon--angle-expand` |
| `pb-col-toggle__icon--caret` | `pb-layout-col__toggle-icon--caret` |
| `pb-col-toggle__icon--caret-left` | `pb-layout-col__toggle-icon--caret-left` |
| `pb-col-toggle__icon--caret-right` | `pb-layout-col__toggle-icon--caret-right` |
| `pb-col-toggle__icon--chevron` | `pb-layout-col__toggle-icon--chevron` |
| `pb-col-toggle__icon--chevron-left` | `pb-layout-col__toggle-icon--chevron-left` |
| `pb-col-toggle__icon--chevron-right` | `pb-layout-col__toggle-icon--chevron-right` |
| `pb-col-toggle__icon--minus` | `pb-layout-col__toggle-icon--minus` |
| `pb-col-toggle__icon--plus` | `pb-layout-col__toggle-icon--plus` |
| `pb-col-toggle__icon--plus-minus` | `pb-layout-col__toggle-icon--plus-minus` |
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
| `pb__modal-wrapper` | `pb-modal__wrapper-{id}`, now an **id**, not a class |
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

## Manual-only renames (reported by the command)

`drush pb:migrate-theme` reports most of these instead of rewriting them
(see "Step 7" above). Two reasons:

- **State / flags / utilities** tokens collide with `:active`,
  `prefers-reduced-motion`, or already-migrated `pb-is-*` / `pb-u-*`.
- **Value-driven + bundle-constructed classes** derive from **stored
  field values**. v1 rendered the stored value *as* the class; v2 keeps
  the stored value byte-for-byte and renders a modern class via the
  `pb_class` filter. So the **stored value never changes** (never touch
  content or config `allowed_values`). Only your CSS selector does. A find-and-replace
  that also rewrites `.yml` would corrupt those stored values. That is why the command never reads `.yml` files, and only
  rewrites a value-driven class inside a stylesheet.

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

## Display field classes: stored values (never change)

These **`pb__*`** tokens are **list field storage values**. The stored
value is frozen. Never edit it, in content or config `allowed_values`.
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
