# Paragraph Bundles — CSS custom properties reference

**Module:** `paragraphs_bundles` (v2)  
**Two-layer model:** summarised in the next section.

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

**image_background:** `pb_display_bg` does **not** become `--pb-bg` on inner.
It is emitted by `PbDynamicUserVarBuilder` as a plain `background-color:
rgba(...)` declaration on `.img-color-overlay` — a real property, not a custom
property, so there is no variable to override or theme-bridge here.

---

## User layout / content declarations

Emitted by `PbDynamicUserVarBuilder` when the editor filled the source field.

**These are real CSS properties, not custom properties.** Only the two column
bundles emit a `--pb-*` variable here; every other row below writes the
property directly onto the target element, which is the same property v1 wrote
into a `style=""` attribute on the same element. There is nothing to `var()`
against and nothing for a theme bridge to map — to change one of these, target
the element in your own CSS with equal-or-higher specificity.

Scopes are relative to the outer `#pb-{slug}-{id}` id. The single source of
truth is `USER_STYLE_SCOPES` in `src/Service/Css/PbDynamicUserVarBuilder.php`;
this table mirrors it.

### image_background_bundle

| Property | Field(s) | Scope |
|----------|----------|-------|
| `background-image` | `pb_display_background_image` | Outer (skipped when a
small image is set) |
| `background-attachment` | `pb_display_background_attachment` | Outer |
| `background-position` | `pb_display_image_alignment` | Outer |
| `background-repeat` | `pb_display_image_repetition` | Outer |
| `background-size` | `pb_display_image_sizing` | Outer |
| `min-height` | `pb_display_image_min_height` | Outer |
| `background-color` | `pb_display_bg` + `pb_display_bg_opacity` |
`.img-color-overlay` |

### grid_bundle

| Property | Field | Scope |
|----------|-------|-------|
| `gap` | `pb_display_grid_gap` | `.pb-grid__wrapper` |

### carousel_bundle

| Property | Field | Scope |
|----------|-------|-------|
| `gap` | `pb_display_slide_gap` | `.pb-carousel__slides` |
| `max-width` | `pb_display_custom_slide_width` | `.pb-carousel__slide` |

### image_grid_bundle

| Property | Field | Scope |
|----------|-------|-------|
| `grid-template-columns` | `pb_display_grid_width` |
`.pb-image-grid__wrapper` |
| `gap` | `pb_display_image_grid_gap` | `.pb-image-grid__wrapper` |

### two_columns_bundle / three_columns_bundle

The only custom properties this builder emits. The static column CSS consumes
them with `var()`, so these two *are* overridable.

| Variable | Field | Scope |
|----------|-------|-------|
| `--pb-col-anim-ms` | `pb_display_2c_anim_ms` / `pb_display_3c_anim_ms` |
`.pb-two-columns` / `.pb-three-columns` |
| `--pb-col-sticky-top` | `pb_display_2c_stk_top` / `pb_display_3c_stk_top` |
Same |

### 3d_carousel_bundle

| Property | Field | Scope |
|----------|-------|-------|
| `perspective` | `pb_display_3d_car_perspective` | `>
.pb-3d-carousel__inner` |

### icon_bundle

| Property | Field(s) | Scope |
|----------|----------|-------|
| `font-variation-settings` | `pb_content_gms_fill`, `_weight`, `_grade`,
`_optical_size` | `.icon-wrapper` |
| `color` | `pb_content_gms_icon_color` | `.icon-wrapper span` |
| `font-size` | `pb_content_gms_icon_font_size` | `.icon-wrapper span` |

### 3d_flip_box_bundle

| Property | Field | Scope |
|----------|-------|-------|
| *(none via builder)* | `pb_display_3d_height` | Inline `height` on
`.pb-3d-flip-box__scene` in Twig |

### parallax_section_bundle — written by Twig, not by the builder

This bundle is the one exception to the two-layer model: it keeps v1's inline
`style` attributes, written by
`paragraph--parallax-section-bundle.html.twig`, because the JS reads them.
Every value there passes through the `pb_css_value` Twig filter, which applies
the same sanitizer the PHP builders use.

| Variable / property | Field | Scope |
|---------------------|-------|-------|
| `--section-height` | `pb_display_parallax_height` + `pb_display_para_height_unit`
| `.pb-ui-inner.pb-parallax-section__inner` |
| `background-image` (a real property, not a variable) | `pb_content_image`,
resolved by `pb_image_field_url` | `.pb-parallax__bg` |
| `--animation-speed` | `pb_display_para_bg_anim_speed` | `.pb-parallax__bg`
(non-legacy scroll only) |
| `--animation-easing` | `pb_display_para_anim_easing` | `.pb-parallax__bg`
(non-legacy only) |
| `--background-position` | `pb_display_parallax_bg_position` |
`.pb-parallax__bg` (non-legacy only) |

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
