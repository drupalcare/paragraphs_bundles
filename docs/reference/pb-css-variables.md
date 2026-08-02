# Paragraph Bundles — CSS custom properties reference

**Module:** `paragraphs_bundles` (v2)  
**See also:** `docs/PB-V2-INLINE-TO-DYNAMIC-PLAN.md` (two-layer model)

---

## Two layers

| Layer | Location | User values? |
|-------|----------|--------------|
| **Static library** | `modules/paragraph_bundle_*/css/*.css` | Never — layout +
`var(--pb-*)` consumption only |
| **Dynamic library** | `public://paragraphs_bundles/pb-{slug}-{id}.css` | Only
place for editor field values |

List-field modifiers (`pb__padding_4`, `pb__width_100`) stay as **CSS classes**,
not custom properties.

---

## Colour variables (all bundles with Display colours)

Emitted by `PbDynamicCssBuilder` on the bundle inner scope (or split scopes for
hero, modal, card-image, etc.).

| Variable | Field | Notes |
|----------|-------|-------|
| `--pb-bg` | `pb_display_bg` | **Editor only** — not theme-bridged. Target
varies by bundle (see builder scopes). |
| `--pb-bg-h` | `pb_display_bg_hover` | **Editor only** — not theme-bridged. |
| `--pb-br` | `pb_display_border_color` | |
| `--pb-br-h` | `pb_display_border_hover` | |
| `--pb-tx` | `pb_display_text` | |
| `--pb-tx-h` | `pb_display_text_hover` | |
| `--pb-lk` | `pb_display_link` | Id-scoped link rules |
| `--pb-lk-h` | `pb_display_link_hover` | |
| `--pb-bt-a` | bundle-specific active item | Accordion, tabs, slideshow —
**editor only** |

**image_background:** `pb_display_bg` maps to `--pb-overlay-bg` on
`.img-color-overlay`, not `--pb-bg` on inner.

---

## User layout / content variables

Emitted by `PbDynamicUserVarBuilder` when the editor filled the source field.

### image_background_bundle

| Variable | Field(s) | Scope |
|----------|----------|-------|
| `--pb-bg-image` | `pb_display_background_image` | Outer `#pb__image-bg-{id}`
(skipped when small image set) |
| `--pb-bg-attachment` | `pb_display_background_attachment` | Outer |
| `--pb-bg-position` | `pb_display_image_alignment` | Outer |
| `--pb-bg-repeat` | `pb_display_image_repetition` | Outer |
| `--pb-bg-size` | `pb_display_image_sizing` | Outer |
| `--pb-min-height` | `pb_display_image_min_height` | Outer |
| `--pb-overlay-bg` | `pb_display_bg` + `pb_display_bg_opacity` | `.img-color-
overlay` |

### grid_bundle

| Variable | Field | Scope |
|----------|-------|-------|
| `--pb-gap` | `pb_display_grid_gap` | `.pb__grid-wrapper` |

### carousel_bundle

| Variable | Field | Scope |
|----------|-------|-------|
| `--pb-gap` | `pb_display_slide_gap` | `.pb__carousel-items` |
| `--pb-slide-max-width` | `pb_display_custom_slide_width` | `.pb__carousel-
item` |

### image_grid_bundle

| Variable | Field | Scope |
|----------|-------|-------|
| `--pb-grid-columns` | `pb_display_grid_width` | `.pb__image-grid-wrapper` |
| `--pb-gap` | `pb_display_image_grid_gap` | `.pb__image-grid-wrapper` |

### two_columns_bundle / three_columns_bundle

| Variable | Field | Scope |
|----------|-------|-------|
| `--pb-col-anim-ms` | `pb_display_2c_anim_ms` / `pb_display_3c_anim_ms` |
`.pb__two-columns` / `.pb__three-columns` |
| `--pb-col-sticky-top` | `pb_display_2c_stk_top` / `pb_display_3c_stk_top` |
Same |

### 3d_carousel_bundle

| Variable | Field | Scope |
|----------|-------|-------|
| `--pb-perspective` | `pb_display_3d_car_perspective` | `>
.pb-3d-carousel__inner` |

### 3d_flip_box_bundle

| Variable | Field | Scope |
|----------|-------|-------|
| *(none via builder)* | `pb_display_3d_height` | Inline `height` on
`.pb-3d-flip-box__scene` in Twig |

### parallax_section_bundle

| Variable | Field | Scope |
|----------|-------|-------|
| `--section-height` | `pb_display_parallax_height` + unit field | `>
.paragraph__inner` |
| `--pb-bg-image` | `pb_content_image` | `.pb__parallax-bg` |
| `--animation-speed` | `pb_display_para_bg_anim_speed` | `.pb__parallax-bg`
(non-legacy scroll) |
| `--animation-easing` | `pb_display_para_anim_easing` | `.pb__parallax-bg`
(non-legacy) |
| `--background-position` | `pb_display_parallax_bg_position` | `.pb__parallax-
bg` (non-legacy) |

### icon_bundle

| Variable | Field(s) | Scope |
|----------|----------|-------|
| `--pb-icon-variation` | GMS fill/weight/grade/opsz | `.icon-wrapper` |
| `--pb-icon-color` | `pb_content_gms_icon_color` | `.icon-wrapper span` |
| `--pb-icon-size` | `pb_content_gms_icon_font_size` | `.icon-wrapper span` |

---

## pb_content node (not a paragraph)

Dynamic file: `pb-pb-content-{nid}.css`  
Library: `paragraphs_bundles/dynamic--pb-content-{nid}`

| Property | Field(s) | DOM target (v1 inline) |
|----------|----------|------------------------|
| `background-color` | `pb_display_content_bg` + `pb_display_content_bg_opacity`
| `#node-{nid}` (`<article>`) |

Hex values emit as `#rrggbb`; rgb triplets emit as `rgba(r, g, b, a)`.

---

## Regeneration

After deploy or bulk field changes:

```bash
drush pb:regenerate-css --purge
drush cr
```

Empty fields produce **no** declaration; static CSS fallbacks apply.
