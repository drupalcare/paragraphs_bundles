# What's new in Paragraphs Bundles 2.0

**For:** site builders and themers deciding whether to upgrade, and anyone who
wants to know why v2 looks the way it does.

**In one line:** every bundle keeps its content, its fields and its rendered
structure — what changed is the CSS class names, how editor colours reach the
page, and the Drupal versions supported.

- **Requires** Drupal `^11.3 || ^12` and PHP 8.3+.
- **Your content is not migrated.** No field was renamed, no stored value was
  rewritten. See [Why nothing was migrated](#why-nothing-was-migrated).
- **The upgrade is one-way** and has a prerequisite. Read
  [`UPGRADE-V1-TO-V2.md`](UPGRADE-V1-TO-V2.md) before you start.

---

## Why there is a v2 at all

v1 worked, but four things could not be fixed inside it.

**1. It could not run on Drupal 12.** v1 used APIs removed in Drupal 12 and
predates the attribute-based plugin and hook APIs. Supporting both meant a
rewrite of the plumbing, not a patch.

**2. Editor-supplied colours reached the page unescaped.** v1 wrote colour
values straight into a `style="--pb-bg:rgba(…)"` attribute. Twig's HTML
autoescaping does **not** neutralise `(`, `)`, `;` or `:` inside a style
attribute, so a crafted value could break out of the declaration. v2 validates
at the field layer and again at every point where CSS is written.

**3. Class names were unpredictable.** v1 shipped names like `pb__accor-wrap`,
`pb__nf`, `pb__caro-item` and `paragraph__inner`. You could not derive a class
from the bundle name — you had to look it up. For a module whose whole purpose
is letting a themer restyle paragraphs, that is the wrong end of the trade.

**4. The same code was copied 27 times.** Every bundle sub-module carried its
own near-identical copy of shared logic. A single security fix in one of them
had to be applied by script across all 27.

---

## What changed for you

### CSS class names are now BEM

This is the change most likely to affect your theme.

| | v1 | v2 |
|---|---|---|
| Paragraph inner wrapper | `paragraph__inner` | `pb-ui-inner` |
| Accordion | `pb__accor-wrap` | `pb-accordion` |
| Carousel slide | `pb__caro-item` | `pb-carousel__slide` |
| Rounded corners | `pb__round_large` | `pb-u-round--large` |

The grammar is `pb-{block}__{element}--{modifier}` for structure and
`pb-u-{family}--{modifier}` for utilities, so you can derive a class from the
bundle machine name instead of looking it up.

**299 classes were renamed. 69 tokens deliberately were not.** The full map is
[`CSS-CLASS-MIGRATION.md`](CSS-CLASS-MIGRATION.md); every entry in it was
checked against the shipped source on 2026-08-04.

> **If your theme overrides PB classes, this is the one thing you must do
> before upgrading:** work through the migration map and update your selectors.
> Nothing warns you at runtime — a stale selector simply stops matching.

### Editor colours are now real stylesheets, not inline styles

v1 put `style="--pb-bg:…"` on the wrapper. v2 writes one small CSS file per
paragraph under `public://paragraphs_bundles/` and attaches it as a library.

Why it is better: it works under a Content-Security-Policy that forbids inline
styles, it is cacheable, and element-id specificity means an editor's colour
wins without `!important`.

The custom property names (`--pb-bg`, `--pb-tx`, `--pb-lk`, …) are unchanged,
so any CSS you wrote against those still works.

### Accessibility fixes

Real defects, not audit theatre: the slideshow hijacked the Space key and
autoplayed with no pause control (WCAG 2.1.1 and 2.2.2), the alert bundle
auto-dismissed with no way to stop it, eight animations ignored
`prefers-reduced-motion`, and `aria-hidden` was emitted as escaped text in five
layout templates. All fixed and covered by tests.

### Two things are no longer in the package

- **28 vendored `LICENSE` files.**
- **`.pb-u-animate--fading`** — the one infinitely-looping animation, referenced
  nowhere.

---

## Why nothing was migrated

v2 renames classes but **not data**. That is deliberate, and it is why the
upgrade is as safe as it is.

Every display option you have ever chosen is stored as a string in a field —
`pb__round_large`, `pb__pmw_500`, `pb__992`. Those strings sit in the field
tables of every site running the module. Renaming them would mean rewriting
content on every install, and a failed content migration is unrecoverable in a
way a wrong CSS selector never is.

So v2 keeps the stored value exactly as v1 wrote it and renders a modern class
from it at display time. The database keeps `pb__round_large`; the page gets
`pb-u-round--large`. You get the new class system with no content migration at
all.

The same reasoning covers the other frozen tokens:

| Frozen | Why |
|---|---|
| Field machine names (`pb_content_*`, `pb_display_*`) | Renaming a field is a content migration |
| Stored display values (`pb__round_large`, …) | Same — they are data, not markup |
| CSS custom properties (`--pb-bg`, …) | Your CSS depends on them |
| Breakpoint tokens (`pb__576` … `pb__1400`) | They double as asset library names other modules can depend on |
| Admin form group names (`pb__tab-display`, …) | They live in your form-display config and style the edit form only |

**Element ids are not on that list.** They were frozen for most of v2's
development and then deliberately un-frozen; the next section says why they
were the exception.

---

## Breaking changes, in full

Three, and only three.

**1. Structural CSS class names changed.** 299 of them. See above.

**2. Outer element ids are now one derivable form**, `#pb-{bundle-slug}-{id}`.
29 of the 30 bundles that had a v1 id therefore changed.

v1 had thirty different id patterns, and most were abbreviations rather than
the bundle's name — `#pb__nf-42` for a node reference, `#pb__accor-wrap-42`
for an accordion, `#pb__slide-wrap-42` for a slideshow. Two different bundles
(parallax and parallax section) even shared a prefix, so the id did not
identify what it was on. There was no rule you could apply; you had to look
each one up.

| Bundle | v1 | v2 |
|---|---|---|
| Accordion | `#pb__accor-wrap-{id}` | `#pb-accordion-{id}` |
| Node reference | `#pb__nf-{id}` | `#pb-node-reference-{id}` |
| Slideshow | `#pb__slide-wrap-{id}` | `#pb-slideshow-{id}` |
| Image (narrow) | `#pb__image-n-{id}` | `#pb-image-narrow-{id}` |
| Modal | `#pb__modal-id-{id}` | `#pb-modal-{id}` |
| … 25 more | | [full table](CSS-CLASS-MIGRATION.md#outer-element-ids-v1--v2) |

**Why this one was not frozen with the rest.** Two of the thirty ids had
already changed before 2.0.0 while the documentation still promised they had
not — so the guarantee was worth less than it appeared. And an id is markup,
not data: correcting it costs a find-and-replace in your own CSS, while
correcting it after a stable 2.0.0 would be a breaking change in a stable
series. A major release is the only honest place to do it.

Your per-paragraph CSS files carry the id as their scope selector, so they have
to be rewritten too — and the upgrade does that for you: `drush updb` finishes
by regenerating every one of them. The exception is a site coming from a
`2.0.x-dev` snapshot rather than from 1.x, where that step has already been
recorded as run; there, rebuild them once with
`drush pb:regenerate-css --purge`.

What needs action from you is CSS or JS **you** wrote that names a PB id.
`card_text_section` is the one bundle whose id is unchanged.

**3. You must be on the latest 1.x before upgrading.** v2 declares
`hook_update_last_removed() = 9004`. A site below that schema is refused with
"requires an intermediate version" — deliberately, because v1's `9001`–`9004`
were removed in v2 and a site below 9004 cannot reach a consistent state.

---

## What the upgrade actually runs

| Hook | What it does |
|---|---|
| `9005` | Shared field storages (title tag/alignment, link colours), title help text |
| `9006` | Wires the shared fields on every bundle, nests the Display tab, hides `pb_display_*` on view displays |
| `9007` | Repairs v1 config that does not match v2's tightened config schema |
| `9008` | Realigns form and view display layout with what a fresh install ships |
| `post_update` | Regenerates every per-paragraph CSS file |

Both paths — a fresh install and a real v1 upgrade — are tested end to end
against a database that started life on v1. Afterwards, an upgraded site and a
freshly installed one differ on **four** config objects, three of which are
site settings rather than module config.

---

## Where to go next

| Document | For |
|---|---|
| [`UPGRADE-V1-TO-V2.md`](UPGRADE-V1-TO-V2.md) | The upgrade procedure — read before upgrading |
| [`CSS-CLASS-MIGRATION.md`](CSS-CLASS-MIGRATION.md) | Every v1 → v2 class, and every frozen token |
| [`PB-V2-CLASS-NAMING-SPEC.md`](PB-V2-CLASS-NAMING-SPEC.md) | The naming grammar, for writing new CSS |
| [`../CHANGELOG.md`](../CHANGELOG.md) | The complete change list |
