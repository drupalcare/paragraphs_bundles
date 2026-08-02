# PB v2 — Theme bridge (`--pb-*` ↔ host theme)

**Status:** Implemented (v2). Bridge CSS + `PbThemeBridgeService` + library
alter.
**Scope:** v2 only. Works with **Solo**, **APEX (`apex_theme`)**, and any
**sub-theme**
of either, plus a **neutral** fallback for other themes.

---

## Problem

Paragraph Bundles uses **`--pb-*`** custom properties (editor-driven colours).
Solo and APEX use **`--r-*`** region variables (15 per region, set on region
wrappers). Today foundation CSS mixes them inline:

```css
background-color: var(--pb-bg, var(--r-bg, transparent));
```

Issues:

1. **Two naming eras** — Solo uses `--r-bg`, `--r-tx`, …; APEX uses
   `--r-background`, `--r-text`, … Each theme documents its own region
   variables.
2. **Implicit fallbacks** — every rule repeats the chain; easy to drift.
3. **Conflict fear** — themes and PB must never fight over the same variable
   names.

---

## Principle (your model — correct)

| Layer | Owns | Set by |
|-------|------|--------|
| Theme | `--r-*` only | Solo / APEX region colour system |
| Paragraph editor | `--pb-*` only | Display tab + dynamic CSS file |
| **Theme bridge** | aliases `--pb-*` → `--r-*` | One static CSS file per theme
family |

**Rules:**

1. PB **never** writes `--r-*`.
2. Themes **never** write `--pb-*`.
3. When the editor **sets** a colour → dynamic CSS sets `--pb-*` on
   `#pb__{slug}-{id} …` at the bundle's target element (id specificity wins).
4. When the editor **leaves** a colour empty → bridge supplies defaults for
   **text, border, and link** tokens only. **`--pb-bg` / `--pb-bg-h` and all
   hover tokens (`--pb-tx-h`, `--pb-br-h`, `--pb-lk-h`) are never bridged**
   — hover appears only when the editor sets hover display fields; each
   bundle applies background on its own DOM target via dynamic + bundle CSS.

No conflict: editor overrides only `--pb-*`; theme only supplies `--r-*`.

---

## Variable mapping (PB subset ↔ 15 region vars)

PB paragraph surface uses **8** colour slots (plus bundle extras like
`--pb-bt-a`):

| `--pb-*` | Purpose | Bridged? | Solo (`--r-*`) | APEX (`--r-*`) |
|----------|---------|----------|----------------|----------------|
| `--pb-bg` | Background | **No — editor only** | — | — |
| `--pb-bg-h` | Background hover | **No — editor only** | — | — |
| `--pb-tx` | Text | Yes | `--r-tx` | `--r-text` |
| `--pb-tx-h` | Text hover | **No — editor only** | — | — |
| `--pb-br` | Border | Yes | `--r-br` | `--r-border` |
| `--pb-br-h` | Border hover | **No — editor only** | — | — |
| `--pb-lk` | Link | Yes | `--r-lk` | `--r-link` |
| `--pb-lk-h` | Link hover | **No — editor only** | — | — |
| `--pb-bt-a` | Active tab/accordion/slide | **No — editor only** | — | — |

**Background and hover:** never mapped from region `--r-*`. Dynamic CSS +
bundle static CSS apply colours on the bundle-specific node only when the
matching display fields are filled. Foundation CSS uses
`var(--pb-*-h, var(--pb-*))` fallbacks so unset hover tokens do not change
appearance.

Bundle extras (`--pb-bt-a` for active tab/accordion/slide): **editor only**
— not theme-bridged (same rule as `--pb-bg`).

---

## Bridge CSS files (one per theme family)

Static files under `css/bridge/`:

| File | When loaded |
|------|-------------|
| `pb-theme-bridge-solo.css` | Active theme hierarchy includes `solo` |
| `pb-theme-bridge-apex.css` | Hierarchy includes `apex_theme` |
| `pb-theme-bridge-neutral.css` | Any other front theme (system colour keywords
only) |

**Example (APEX) — scoped to namespace only:**

```css
.paragraphs-bundles {
  --pb-tx: var(--r-text);
  --pb-br: var(--r-border);
  --pb-lk: var(--r-link);
}
```

**Example (Solo)** — same structure, legacy `--r-bg`, `--r-tx`, etc.

Region inheritance works because `--r-background` (or `--r-bg`) is already
set on `.page-wrapper`, `.region-inner`, APEX `.apex-region-slot`, etc.
The paragraph reads the **nearest** ancestor value through `var()`.

---

## Theme detection (sub-themes included)

Walk the **base theme chain** from the active front theme (same algorithm as
`ThemeDetectionService::buildThemeHierarchy()` in APEX):

1. If `apex_theme` appears anywhere in chain → **APEX bridge**
2. Else if `solo` appears in chain → **Solo bridge**
3. Else → **neutral bridge**

Sub-themes of Solo/APEX are covered automatically (no hardcoded sub-theme list).

**Dependency rule:** PB does **not** require the `apex` module. Detection is
a simple theme-handler walk inside `paragraphs_bundles` (new
`PbThemeBridgeService`).

**Cache:** library choice cached per active theme (`cache tags: theme:{name}`),
invalidates on theme switch.

---

## Where the bridge attaches

| Attach point | Weight / order |
|--------------|----------------|
| `paragraphs_bundles/paragraphs-bundles-base` | early foundation |
| **`paragraphs_bundles/theme-bridge-{solo\|apex\|neutral}`** | after base,
before custom |
| `paragraphs_bundles/paragraphs-bundles-custom` | applies `var(--pb-*)` to
elements |
| Dynamic `paragraphs_bundles/dynamic--{slug}-{id}` | per paragraph, **last**
(weight 1000) |

Bridge loads **once per page** when any PB paragraph is present (same lazy
pattern as foundation — attach from Twig
`attach_library('paragraphs_bundles/paragraphs-bundles')`
or `hook_library_info_build` + preprocess flag).

Dynamic CSS **only emits** `--pb-*` declarations for fields the editor filled
(already true in `PbDynamicCssBuilder`). Empty field = bridge default applies.

---

## Cascade example

```
Region (.apex-header)     --r-text: #eee
  └── .paragraphs-bundles --pb-tx: var(--r-text)   ← bridge (not --pb-bg)
        └── #pb__hero-42 .pb__hero-txt-wrapper-inner
              [no editor bg]  → no background (region bg shows through)
              [editor bg red] → dynamic sets background on hero inner → red
```

---

## Foundation CSS simplification (follow-up)

After bridge ships, `paragraphs-bundles-custom.css` can simplify to:

```css
.paragraphs-bundles > .pb-inner {
  background-color: var(--pb-bg);
  color: var(--pb-tx);
  border-color: var(--pb-br);
}
```

Fallback chain moves **only** into bridge files (solo / apex / neutral), not
every rule. Neutral bridge keeps `Canvas`, `CanvasText`, `LinkText` system
keywords.

---

## Implementation checklist

1. **`PbThemeBridgeService`** — `resolveBridgeId(): 'solo' | 'apex' | 'neutral'`
   — **done**
2. **Three bridge CSS files** — base token mappings only — **done**
3. **Static libraries** — `theme-bridge-{id}` in
   `paragraphs_bundles.libraries.yml` — **done**
4. **`PbLibraryInfoAlterHook`** — adds bridge as dependency of
   `paragraphs-bundles` — **done**
5. **Tests** — Unit: `PbThemeBridgeServiceTest` theme hierarchy — **done**
6. **Foundation CSS** — `paragraphs-bundles-custom.css` uses `var(--pb-*)` only
   — **done**
7. **Demo QA** — paragraph in multiple regions; empty Display colours inherit
   region; set one colour → only that property overrides — **pending (demo)**

---

## Out of scope

- PB does not read theme settings PHP directly (no `theme_get_setting` for
  colours) — CSS inheritance only.
- Admin form / widget CSS unchanged (frozen).
- Does not replace APEX `apex_color` — consumes its `--r-*` output.

---

## Related

- `css/paragraphs-bundles-custom.css` — current inline `--r-*` fallbacks
- `src/Service/Css/PbDynamicCssBuilder.php` — editor-only `--pb-*` emission
- `docs/reference/pb-css-variables.md` — field → CSS reference
- `docs/PB-V2-CLASS-NAMING-SPEC.md` — BEM / scoping standard
