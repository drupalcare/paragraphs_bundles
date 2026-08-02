# PB v2 — CSS class naming spec

> **Per-bundle L3/L4:**
[`PB-V2-BUNDLE-VOCABULARY.md`](PB-V2-BUNDLE-VOCABULARY.md)  
> **Generated rename tables:**
[`PB-V2-BEM-VOCABULARY.md`](PB-V2-BEM-VOCABULARY.md)  
> **v1 → v2 map for site builders:**
[`CSS-CLASS-MIGRATION.md`](CSS-CLASS-MIGRATION.md)

**Status:** Authoritative standard for front-end class tokens in
`paragraphs_bundles` v2.

Every new bundle, template, CSS file, and JS selector MUST follow this spec.
The rename map is **generated from rules**, not maintained by hand; the
generator and its rule file are maintainer tooling and are not part of the
release package.

---

## 1. Design goals

1. **Global uniqueness** — no token can collide with themes, core, or contrib.
2. **Predictability** — given a bundle machine name, any developer can derive
   class names without reading 150 legacy rows.
3. **Separation of concerns** — editor config tokens ≠ structural markup.
4. **One bulk migration** — v1 → v2 rename map is output of the rule engine.
5. **Collision-safe by construction** — every class uses the `pb-` / `pb__`
   namespace; generic UI words (`active`, `open`, `visible`, …) are never bare.

---

## 2. Four layers (every class fits exactly one)

| Layer | Token pattern | Set by | Example |
|-------|---------------|--------|---------|
| **L0 Global** | `paragraphs-bundles` | Twig (every paragraph root) | Scope
root shared by all bundles |
| **L1 Identity** | `#pb__{slug}-{id}` | Preprocess | Dynamic CSS anchor (id,
not class) |
| **L2 Config** | `pb__{token}` | List field `allowed_values` or Twig prefix +
field | `pb__pmw_500`, `pb__992` |
| **L3 Private** | `pb-{bundle-slug}` | Twig (**paragraph root only**) | `pb-
accordion`, `pb-tabs` — bundle type marker |
| **L3 Component** | `pb-{bundle}__{element}` | Templates, CSS, JS | `pb-
accordion__pane`, `pb-accordion__sections` |

**L3 behavior host** uses **descriptive role names** per bundle — see
[`PB-V2-BUNDLE-VOCABULARY.md`](PB-V2-BUNDLE-VOCABULARY.md).

**Never mix layers.** Config tokens (`pb__*`) are not BEM blocks. The
**private**
class (`pb-{slug}`) must appear **once** on the paragraph root — not on inner
widgets. Inner structure uses `pb-{slug}__*` elements.

### What is frozen (L2 — config)

Config tokens stay `pb__*` until a future **field decoupling** project
(`PbDisplayClassMapper`). See constraints doc for breakpoint/library coupling.

### What Stage 3 migrates (L3 — component)

Hardcoded structural classes in front-end Twig, CSS, and JS → `pb-*` grammar
below.

---

## 3. L3 grammar — `pb-` namespace

**`pb-`** = paragraphs bundles structural prefix. Required for all L3 classes.
(L2 config keeps **`pb__`** double-underscore — different layer, no collision.)

### 3.1 Bundle private class (paragraph root)

```
pb-{bundle-slug}
```

On the **paragraph root only** — the private bundle-type marker paired with
global `paragraphs-bundles`. CSS and JS must scope through
`.paragraphs-bundles.pb-{slug}`, never `closest('.paragraphs-bundles')` alone.

Inner widgets use **elements** (`pb-accordion__sections`, `pb-accordion__item`),
not
a second copy of the private class. L3/L4 per bundle:
[`PB-V2-BUNDLE-VOCABULARY.md`](PB-V2-BUNDLE-VOCABULARY.md).

**Bundle slug algorithm** (from paragraph bundle machine name):

1. Remove suffix `_bundle` if present.
2. Replace `_` with `-`.
3. Lowercase (already snake_case).

| Machine name | Bundle slug | Private class |
|--------------|-------------|---------------|
| `accordion_bundle` | `accordion` | `pb-accordion` |
| `slideshow_bundle` | `slideshow` | `pb-slideshow` |
| `carousel_bundle` | `carousel` | `pb-carousel` |
| `3d_carousel_bundle` | `3d-carousel` | `pb-3d-carousel` |
| `two_columns_bundle` | `two-columns` | `pb-two-columns` |
| `card_image_section_bundle` | `card-image-section` | `pb-card-image-section` |

**Rule:** use the **full bundle slug**, not v1 abbreviations (`accor`, `caro`,
`slide`). Abbreviations exist only in the legacy → target map until Stage 3
apply.

### 3.2 Element

```
{bundle-block}__{element}
```

- `{element}` = kebab-case DOM role (`pane`, `nav-btn`, `text-inner`).
- One `__` separator (BEM element).

Examples: `pb-accordion__pane`, `pb-carousel__dot`, `pb-slideshow__nav`.

### 3.3 Element modifier

```
{bundle-block}__{element}--{variant}
```

Examples: `pb-modal__dialog--open`, `pb-alert--top` (block modifier when
the whole block variant is meant — prefer element modifier when possible).

### 3.4 Cross-bundle state (JS toggles)

```
pb-is-{state}
```

Used when **multiple bundles** share the same JS-driven state class.

| Legacy | Target | Meaning |
|--------|--------|---------|
| `pb__active` | `pb-is-active` | Selected tab, slide, accordion item, dot |
| `pb__active-no` | `pb-is-inactive` | Explicit inactive sibling |
| `active`, `visible`, `is-collapsed` (legacy bare) | `pb-is-*` | **Never** bare
— see DOM principles §2.3 |

**Not** bare `active`, **not** `pb-active` (missing namespace segment), **not**
Bootstrap-style `is-active` without `pb-` prefix.

### 3.5 Cross-bundle flags (capability / layout)

```
pb-has-{feature}
pb-no-{feature}
```

Examples: `pb-has-arrows`, `pb-has-sticky-col`, `pb-no-arrows`,
`pb-no-bottom-nav`.

### 3.6 Shared UI chrome (3+ bundles)

```
pb-ui-{part}
```

Shared buttons, links, rows, bars, inner colour shell — not owned by one
bundle block.

| Legacy | Target |
|--------|--------|
| `paragraph__inner` | `pb-ui-inner` |
| `pb__button` | `pb-ui-btn` |
| `pb__link` | `pb-ui-link` |
| `pb__row` | `pb-ui-row` |
| `pb__bar` | `pb-ui-bar` |

### 3.7 Conditional utilities (Twig-built, not field storage)

```
pb-u-{descriptor}
```

Small helper classes toggled by Twig logic (not list fields):

| Legacy | Target |
|--------|--------|
| `pb__dis-radius` | `pb-u-dis-radius` |
| `pb__dis-top-left-right` | `pb-u-dis-top-left-right` |
| `pb__image-full` | `pb-u-image-full` |
| `pb__large-screen` / `pb__small-screen` | `pb-u-large-screen` / `pb-u-small-
screen` |

### 3.8 Layout column toolkit

Layout bundle column toggle UI uses block `pb-layout-col`:

```
pb-layout-col__{element}
pb-layout-col__{element}--{modifier}
```

Legacy `pb-col-*` → `pb-layout-col__*` (see rules JSON).

### 3.9 Nav controls (carousel + slideshow)

Shared navigation block when not bundle-specific:

```
pb-nav__{control}
```

Examples: `pb-nav__prev`, `pb-nav__next`, `pb-nav__play`, `pb-nav__pause`.

---

## 4. Selector scoping (CSS)

Every front-end rule MUST start with `.paragraphs-bundles` (L0) **and** include
the bundle L3 block class on the **same paragraph root** when the rule is
bundle-specific.

### 4.1 Twig (paragraph root)

Keep Drupal core classes for API/theming; add the PB bundle block on the root:

```twig
{% set classes = [
  'paragraph',
  'paragraphs-bundles',
  'pb-accordion',
  'paragraph--type--' ~ paragraph.bundle|clean_class,
] %}
```

`paragraph--type--*` stays in markup for Drupal. **Do not use it in CSS.**

### 4.2 CSS patterns

| Use case | Pattern | Example |
|----------|---------|---------|
| Bundle root shell | `.paragraphs-bundles.pb-{slug}` | `.paragraphs-bundles.pb-
accordion { overflow: hidden; }` |
| Bundle components | `.paragraphs-bundles.pb-{slug} .pb-{slug}__*` |
`.paragraphs-bundles.pb-accordion .pb-accordion__pane` |
| Shared L3 inside one bundle | `.paragraphs-bundles.pb-{slug} .pb-icon-toggle`
| Accordion toggle only on accordion paragraphs |

```css
/* Correct — scoped to accordion paragraphs only */
.paragraphs-bundles.pb-accordion .pb-accordion__pane { … }

/* Wrong — leaks to every bundle on the page that uses pb-accordion__pane */
.paragraphs-bundles .pb-accordion__pane { … }

/* Wrong — styling through Drupal core */
.paragraph--type--accordion-bundle { … }

/* Wrong — unscoped */
.pb-accordion__pane { … }
```

### 4.3 Why not only `.paragraphs-bundles .pb-*`?

L0 alone matches **any** paragraph with class `paragraphs-bundles`. A carousel
on the same page also has L0 — bundle-specific rules without `pb-{slug}` on the
root can hit the wrong paragraph.

`:has(.pb-accordion)` is fragile (DOM depth) and still weaker than
`.paragraphs-bundles.pb-accordion` on the paragraph entity root.

### 4.4 Nested paragraphs

Descendant selectors still apply inside nested paragraph content (same as v1
`.paragraph--type--*`). That is acceptable for shared tokens (`pb-icon-toggle`)
when the chain includes `.paragraphs-bundles.pb-{slug}` so **sibling** bundles
on the page are not affected.

### 4.5 Phase B checklist (every bundle)

**Full step-by-step:** `PB-V2-PHASE-B-SCOPING-PLAYBOOK.md` (accordion =
reference).

1. Add `pb-{slug}` to root `classes` in Twig (**once** — not on inner widgets).
2. Give the behavior host a **descriptive role** from
   [`PB-V2-BUNDLE-VOCABULARY.md`](PB-V2-BUNDLE-VOCABULARY.md)
   (e.g. `pb-accordion__sections`, `pb-tabs__tabset`).
3. Replace `.paragraph--type--{bundle}` in CSS with
   `.paragraphs-bundles.pb-{slug} …`.
4. JS: class constants 1:1 with DOM; `once('pb-paragraph-bundle-{slug}-…')`;
   owned `:scope >` queries.
5. Replace bare state classes with `pb-is-*`; keep `pb--*` / `pb-u-*` for config
   on root.
6. Run `apply-pb-class-renames.php --dry-run` (must be 0).

### 4.6 JavaScript scoping

| Class | Role | JS usage |
|-------|------|----------|
| `paragraphs-bundles` | **Global** — every bundle root | Never use alone in
`closest()` for bundle logic |
| `pb-{slug}` | **Private** — one bundle type on paragraph root | Pair with
global: `.paragraphs-bundles.pb-accordion` |
| `pb-{slug}__*` | Component elements | `once()` targets, `querySelector` within
widget |
| `pb-is-*` | Runtime state (JS toggled) | `pb-is-active`, `pb-is-expand-all` —
not bare legacy names |

**`once()` IDs (PB v2):** kebab-case with `pb-` prefix and bundle context, e.g.
`pb-paragraph-bundle-accordion-sections`. APEX modules use `apex-`; PB
sub-modules
use `pb-` per namespace.

```javascript
const PARAGRAPHS_BUNDLES = 'paragraphs-bundles';
const PB_ACCORDION = 'pb-accordion';
const PB_ACCORDION__SECTIONS = 'pb-accordion__sections';
const PB_ACCORDION__ITEM = 'pb-accordion__item';
const PB_ACCORDION__BUTTON = 'pb-accordion__button';
const PB_IS_EXPAND_ALL = 'pb-is-expand-all';
const PB_IS_COLLAPSE_ALL = 'pb-is-collapse-all';

const PARAGRAPHS_BUNDLES_PB_ACCORDION =
  `.${PARAGRAPHS_BUNDLES}.${PB_ACCORDION}`;
const PARAGRAPHS_BUNDLES_PB_ACCORDION_PB_ACCORDION__SECTIONS =
  `${PARAGRAPHS_BUNDLES_PB_ACCORDION} .${PB_ACCORDION__SECTIONS}`;

const PB_ACCORDION__SECTIONS_OWNED_BUTTON =
  `:scope > .${PB_ACCORDION__ITEM} > .${PB_ACCORDION__BUTTON}`;

const paragraphsBundlesPbAccordion =
  sectionsHost.closest(PARAGRAPHS_BUNDLES_PB_ACCORDION);
if (paragraphsBundlesPbAccordion?.classList.contains('pb-u-open-first')) { … }

once('pb-paragraph-bundle-accordion-sections',
  PARAGRAPHS_BUNDLES_PB_ACCORDION_PB_ACCORDION__SECTIONS, context);
once('pb-paragraph-bundle-accordion-toggle',
  PB_ACCORDION__SECTIONS_OWNED_BUTTON, sectionsHost);

// Wrong — matches any nested paragraph with L0 global class.
const root = widget.closest('.paragraphs-bundles');

// Wrong — private class duplicated on inner widget; ambiguous with root.
once('…', '.pb-accordion', context);

// Wrong — nested accordion buttons inside pane content.
accordionContainer.querySelectorAll('.pb-accordion__button');

// Correct — only items/buttons owned by this widget root.
accordionContainer.querySelectorAll(':scope > .pb-accordion__item > .pb-
  accordion__button');
```

**Owned-element rule:** After attaching to `pb-{slug}__root`, select only
elements
owned by that widget. If the bundle can contain nested paragraphs (accordion
panes often do), avoid broad descendant selectors such as
`widget.querySelectorAll('.pb-{slug}__button')`. Prefer `:scope >
.pb-{slug}__item`
or verify `element.parentElement === widget`.

**State classes:** Replace bare legacy states (`expand-all`, `collapse-all`)
with
`pb-is-*` tokens toggled by JS. Config/display flags from fields use `pb--*` or
`pb-u-*` on the paragraph root (see L2/L3 utility patterns).

Put the private class **once** on the paragraph root. Inner structure uses
`pb-{slug}__root` (or similar element), not a second `pb-{slug}`.

### 4.7 JavaScript hygiene (Phase B+)

Selector scoping (§4.6) is **not** full JS modernization. Every front-end bundle
behavior must also meet the module's JavaScript rules:

- `attach(context, settings)` and `detach` on `unload`
- `once()` / `once.remove()` for all init paths
- Listener cleanup (`AbortController` `{ signal }` recommended)
- `fetch` abort discipline, `{ cause: err }` on re-throws
- `pb:` CustomEvents (not `window.pb.*`)
- ASCII-only; no jQuery; ES2022+ syntax

**PB namespace:** `pb-paragraph-bundle-{slug}-*` once IDs (not `apex-`).

**Playbook:** `PB-V2-JS-HYGIENE-PLAYBOOK.md` — apply per bundle with Phase B.

---

## 5. Legacy v1 → L3 mapping (reference)

v1 used inconsistent prefixes. The rule file lists every legacy class; this
table shows **patterns**:

| v1 pattern | L3 target pattern |
|------------|-------------------|
| `pb__accor*` | `pb-accordion__*` |
| `pb__caro*`, `pb__carousel*` | `pb-carousel__*` |
| `pb__slide*`, `pb__slideshow*` | `pb-slideshow__*` |
| `pb__tabs*` | `pb-tabs__*` |
| `pb__grid*` | `pb-grid__*` |
| `pb-col-*` | `pb-layout-col__*` |
| `pb-modal-*` (hyphen) | `pb-modal__*` |
| `pb__pmw_*`, `pb__992`, … | **frozen L2** |

Full table: generated `docs/PB-V2-BEM-VOCABULARY.md`.

---

## 6. Adding a new bundle (checklist)

1. Derive **bundle slug** from machine name (§3.1).
2. Root template: always `paragraphs-bundles` + `paragraph--type--*`.
3. Structural classes: only `pb-{slug}__*` — never bare semantic words.
4. Display tab list fields: store `pb__*` tokens in field storage (L2) until
   mapper exists; do not invent new `pb__` tokens for pure markup.
5. Run `generate-pb-class-renames.py --check` after inventory update.
6. Stylelint: selectors contain `.paragraphs-bundles`.

---

## 7. Future improvement (post Stage 3)

| Project | Benefit |
|---------|---------|
| `PbDisplayClassMapper` service | Decouple L2 field values from CSS class
strings |
| Breakpoint library refactor | Library id `*-bp-992`, drop class/library
coupling |
| Container queries | Reduce breakpoint sub-library proliferation |
| Single L3 prefix only on front-end | Deprecate `pb__` on rendered HTML |

---

## 8. Related docs

| Doc | Role |
|-----|------|
| `PB-V2-BEM-VOCABULARY.md` | Generated rename tables, plus the frozen L2 list |
| `PB-V2-BUNDLE-VOCABULARY.md` | Per-bundle L3/L4 names |
| `CSS-CLASS-MIGRATION.md` | Complete v1 → v2 map for site builders |
