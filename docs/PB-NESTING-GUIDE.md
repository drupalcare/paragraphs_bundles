# Paragraphs Bundles — Nesting Guide

> What may go inside what, and why. Every rule here is derived from what the
> bundles **actually do** — the CSS and JavaScript each one ships — not from
> preference. Measured 2026-09-11 against Drupal 11.4.6 with Solo.

**The short version.** Put leaf bundles inside container bundles, one level
deep, and keep the three-dimensional and the auto-playing bundles at the top
level. That covers almost every real page and cannot go wrong.

---

## 1 · Why some nestings break

Four measured traits decide it. A bundle carrying one of these is constrained
in where it can sit, or in what it can hold.

| Trait | Bundles that have it | What it does to a nesting |
|---|---|---|
| `perspective` (3D rendering context) | `parallax`, `3d_carousel`, `3d_flip_box` | A 3D context **flattens any 3D descendant**. Two of these nested produce a visibly wrong result, not a subtle one. |
| autoplay / timers | `3d_carousel`, `carousel`, `slideshow` | Keeps animating when hidden. Inside a closed pane it burns work for nobody, and pause-when-invisible has nothing to hook onto. |
| scroll &amp; rect reads | `parallax`, `carousel`, `slideshow` | Reads position against the viewport. A transformed ancestor **redefines what "viewport" means**, so the maths silently goes wrong. |
| heavy `transform` + `overflow: hidden` | `parallax`, `carousel`, layout columns, `image_overlay` | Creates a containing block and clips. Nesting these compounds the clipping. |

---

## 2 · The six rules

### Rule 1 — Leaves go anywhere ✅

Fourteen bundles have **no child slot at all**. They cannot create a nesting
problem, so they are the right default for the inside of any container.

`simple` · `hero` · `image` · `image_wide` · `image_narrow` · `image_overlay` ·
`icon` · `block` · `block_content` · `views` · `webform` · `contact_form` ·
`node_reference` · `pb_target`

### Rule 2 — Perspective never nests in perspective 🚫

Never put **`parallax`**, **`3d_carousel`** or **`3d_flip_box`** inside one
another. Each establishes its own 3D rendering context and the inner one is
flattened. This is the single most common mistake and the most visible.

### Rule 3 — Nothing that autoplays goes somewhere hidden 🚫

No **`carousel`**, **`slideshow`** or **`3d_carousel`** inside a **`modal`**, a
closed **`accordion`** pane, or an inactive **`tab`**. Put them at the top level
where they can be seen.

### Rule 4 — Parallax needs the real viewport 🚫

`parallax` reads scroll position, so it must be **top level** or inside a plain
container (`one_column`, `grid`, columns). Never inside anything transformed — a
carousel, a slideshow, a flip box, or another parallax.

### Rule 5 — Backgrounds do not stack 🚫

An `image_background` is a backdrop for content, not a container for another
backdrop. No `image_background` inside `image_background`, and no `parallax`
inside one.

### Rule 6 — One container deep ⚠

Columns inside columns compound clipping and padding. **One** level of layout
nesting is predictable; a second stops being worth the trouble.

---

## 3 · Which bundles host children

⚠ **A slot accepting a bundle is not permission to put it there.** Eighteen
slots across twelve bundles accept *any* bundle, so field config will not stop a
nesting that renders wrong. This table is what stops it.

| Bundle | Slot | Put inside | Never |
|---|---|---|---|
| `one_column` | `pb_content_one_column` | any leaf; one container | — |
| `two_columns` | left / right | any leaf | another column set |
| `three_columns` | left / middle / right | any leaf | another column set |
| `grid` | `pb_content_grid_body` | any leaf | `parallax` |
| `alert` | `pb_content_alert_body` | `simple`, `icon`, `image` | any widget |
| `image_background` | `pb_content_main` | any leaf | `image_background`, `parallax` |
| `modal` | body / footer | any leaf | `carousel`, `slideshow`, `3d_carousel` |
| `accordion_section` | `pb_content_accordion_body` | any leaf | autoplay widgets, another accordion |
| `tab_section` | `pb_content_tab_body` | any leaf | autoplay widgets, another tab set |
| `slideshow_section` | `pb_content_slideshow_body` | `image`, `simple`, `icon` | another slideshow, `parallax` |
| `carousel` | `pb_content_carousel_body` | `image`, `simple`, `icon` | `parallax`, any 3D, another carousel |
| `3d_carousel` | `pb_content_3d_car_body` | `image`, `simple` | any 3D bundle |
| `3d_flip_box` | front / back | `simple`, `icon`, `image` | any 3D bundle, autoplay widgets |

### Fixed-shape children

These exist only inside one parent and are chosen for you — you never place them
freely.

| Child | Belongs to |
|---|---|
| `accordion_section` | `accordion` |
| `tab_section` | `tabs` |
| `slideshow_section` | `slideshow` |
| `parallax_section` | `parallax` |
| `link_section` | `link` |
| `image_grid_section` | `image_grid` |
| `card_text_section`, `card_image_section` | `card_two_columns`, `card_three_columns` |

---

## 4 · Machine-readable form

The same rules as data, for `paragraphs_bundles_ai` and for any tool that needs
to **refuse** an impossible nesting rather than generate one.

```json
{
  "leaves": ["simple_bundle","hero_bundle","image_bundle","image_wide_bundle","image_narrow_bundle",
             "image_overlay_bundle","icon_bundle","block_bundle","block_content_bundle","views_bundle",
             "webform_bundle","contact_form_bundle","node_reference_bundle","pb_target_bundle"],
  "traits": {
    "perspective": ["parallax_bundle","3d_carousel_bundle","3d_flip_box_bundle"],
    "autoplay":    ["3d_carousel_bundle","carousel_bundle","slideshow_bundle"],
    "scroll":      ["parallax_bundle","carousel_bundle","slideshow_bundle"],
    "transform":   ["parallax_bundle","carousel_bundle","two_columns_bundle","three_columns_bundle",
                    "image_overlay_bundle","accordion_bundle","icon_bundle","image_grid_bundle"]
  },
  "forbidden": [
    {"rule": 2, "inside": ["parallax_bundle","3d_carousel_bundle","3d_flip_box_bundle"],
     "child":  ["parallax_bundle","3d_carousel_bundle","3d_flip_box_bundle"],
     "why": "a 3D rendering context flattens a 3D descendant"},
    {"rule": 3, "inside": ["modal_bundle","accordion_section_bundle","tab_section_bundle"],
     "child":  ["carousel_bundle","slideshow_bundle","3d_carousel_bundle"],
     "why": "autoplay keeps running while hidden"},
    {"rule": 4, "inside": ["carousel_bundle","slideshow_bundle","3d_carousel_bundle",
                           "3d_flip_box_bundle","two_columns_bundle","three_columns_bundle"],
     "child":  ["parallax_bundle"],
     "why": "a transformed ancestor redefines the viewport parallax measures against"},
    {"rule": 5, "inside": ["image_background_bundle"],
     "child":  ["image_background_bundle","parallax_bundle"],
     "why": "backdrops do not stack"},
    {"rule": 6, "inside": ["two_columns_bundle","three_columns_bundle"],
     "child":  ["two_columns_bundle","three_columns_bundle"],
     "why": "clipping and padding compound past one level"}
  ]
}
```

---

Width behaves differently on `pb_content` than on a plain landing page. See
[PB-CONTENT-WIDTH-CONTRACT.md](PB-CONTENT-WIDTH-CONTRACT.md).
