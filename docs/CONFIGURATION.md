# Paragraphs Bundles: configuration guide

How editors and site builders configure paragraphs, theme integration,
and the dynamic CSS pipeline.

---

## Paragraph form: Content and Display tabs

Every bundle uses a consistent form layout:

| Tab | Purpose |
|-----|---------|
| **Content** | Body fields (`pb_content_*`): titles, text, images, nested
paragraphs, embeds |
| **Display** | Presentation (`pb_display_*`): colors, borders, spacing,
width, bundle-specific toggles |

The **Display** tab is grouped with Field Group:

1. **Colors**: background, text, border, link colors (+ hover fields
   where the bundle supports them)
2. **Border & radius**: border width, radius, shadow
3. **Spacing**: margin and padding (dimension widgets)
4. **Size & effects**: max width, opacity, bundle-specific size fields
5. **Additional** (optional): bundle-only display fields

Reference layout:
`config/optional/core.entity_form_display.paragraph.simple_bundle.default.yml`.

After changing optional config in a sub-module, run:

```bash
drush pb:verify-form-displays
```

This checks that every bundle with display colors still nests fields
into the expected groups.

---

## Display fields (`pb_display_*`)

Shared display fields are defined once on the parent module and reused
across bundles (for example `pb_display_bg`, `pb_display_text`,
`pb_display_padding`). Bundle sub-modules attach only the fields they
need.

**Important:** stored list values (e.g. `pb__nmw_1024` for max width)
are part of the v1 contract. Do not change `allowed_values` in
`field.storage.*.yml` on an existing site. The BEM/CSS layer depends
on those tokens.

On the **view** display, all `pb_display_*` formatters are hidden.
Presentation comes from dynamic CSS + static bundle CSS, not from
formatter output.

**Icon cards and hover.** An icon (`paragraph_bundle_icon`) acts as a
card when its **Border**, **Background** or **Shadow** option is set.
A card gets the chosen **Padding** on all four sides and lifts on
hover, with its shadow one level stronger than the one chosen. An icon
with none of those options set stays still on hover, even when it has
a link: it has no surface to raise. To make an icon lift, give it a
border, background or shadow.

---

## Dynamic CSS (per paragraph)

### What happens on save

1. `PbEntityHook` runs on paragraph insert/update.
2. `PbDynamicCssBuilder` reads display field values and builds CSS
   scoped to `#pb-{slug}-{id} > .pb-ui-inner` (and bundle-specific targets).
3. `PbCssFileManager` writes
   `public://paragraphs_bundles/pb-{slug}-{id}.{hash}.css`.
4. `PbPreprocessHook` attaches the matching library in Twig
   (`paragraphs_bundles/dynamic--{slug}-{id}`).

### Why no cache clear

The filename includes a **content hash**. When editor settings change,
the hash changes, so browsers fetch a new URL. Library definitions are
rebuilt via `hook_library_info_build` when library cache tags invalidate
on save.

### When to use Drush

Editors never need Drush for day-to-day edits. Use:

| Command | When |
|---------|------|
| `drush pb:regenerate-css` | After `drush updb`, bulk imports, or disaster
recovery |
| `drush pb:regenerate-css --purge` | Orphan file cleanup + full rebuild |
| `drush pb:verify-form-displays` | After enabling bundles or altering form
displays |

`paragraph_bundle_content` nodes with display background fields also
get dynamic CSS files (same pipeline, node slug `pb-content`).

---

## Theme bridge

Paragraphs Bundles uses **`--pb-*`** custom properties for
editor-driven colors. Host themes (Solo, APEX) use **`--r-*`** region
variables. The **theme bridge** maps empty editor tokens to theme
defaults without either side overwriting the other.

| Theme family | Bridge library | Maps (examples) |
|--------------|----------------|-----------------|
| Solo | `theme-bridge-solo` | `--pb-tx` → `--r-tx`, `--pb-br` → `--r-br` |
| APEX | `theme-bridge-apex` | `--pb-tx` → `--r-text`, `--pb-br` → `--r-border`
|
| Other | `theme-bridge-neutral` | System color keywords as fallbacks |

`PbThemeBridgeService` picks the bridge from the active theme and its
base themes. **Background and hover tokens are never bridged.** They
apply only when the editor sets them.

Details: **`docs/PB-V2-THEME-BRIDGE.md`**. Variable list:
**`docs/reference/pb-css-variables.md`**.

Foundation CSS uses chains like
`var(--pb-tx, var(--r-tx, CanvasText))` so sub-themes can still
override via `--pb-*` on the paragraph root.

**Theme-drawn cards.** PB's icon hover lifts only its own cards, marked
`pb-icon--card` on `.pb-icon__inner`. If a theme draws the card itself,
for example with a class on the outer `.pb-icon` element added through
the CSS classes field, PB leaves that card alone on hover. Style its
hover in the theme, on the same element that draws the card:

```css
.my-card.pb-icon:hover {
  box-shadow: 0 4px 20px rgb(0 0 0 / 20%);
}
```

---

## `pb_content` max-width (`paragraph_bundle_content`)

The optional **`pb_content`** node type adds a site-builder control for
landing-page content width:

1. Enable `paragraph_bundle_content`.
2. Create or edit a **PB Content** node.
3. On the **Display** tab → **Width & Colors**, set **Max Content
   Width** (`pb_display_content_width`).

Allowed values map to body classes (`pb-nmw-1024`, etc.) and set
`--pb-content-width` on the page. Theme-specific stylesheets clamp
`.pb-content__full` inside the main column for Solo, APEX, and neutral
themes.

**Excluded from the clamp** (stay full-bleed): carousel viewport,
3D carousel inner, slideshow wrap inner, modal inner content. See
`paragraph-bundle-content-width.css`.

Nested `.pb-content__full` inside another content region resets width to
`initial` so nested paragraphs are not double-clamped.

---

## Sub-module optional config

Each `paragraph_bundle_*` sub-module ships `config/optional/`:

- Paragraph type (`paragraphs.paragraphs_type.*.yml`)
- Fields and storage (bundle-specific `pb_content_*` / `pb_display_*`)
- Form and view displays with Content/Display tabs

Optional config imports when dependencies are met (parent module +
paragraphs enabled). You do not need to copy YAML manually unless you
maintain **custom** display overrides: merge carefully and re-run
`drush pb:verify-form-displays`.

---

## Accessibility and motion

Interactive bundles (carousel, tabs, accordion, modal, lightbox, etc.)
ship keyboard support and `prefers-reduced-motion` handling in CSS.
Verify behavior per bundle using
**`docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md`**.

---

## Related reading

- **`docs/DEVELOPING.md`**: extend services, add bundles, OOP hooks
- **`docs/UPGRADE-V1-TO-V2.md`**: upgrade from v1
- **`README.md`**: install and bundle families overview
