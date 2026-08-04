# Paragraphs Bundles

Ready-made Paragraph types for Drupal 11.3+ / 12 — layout, media,
carousels, accordions, and more. Theme-agnostic, jQuery-free, with
per-paragraph dynamic CSS (no inline styles on the page).

## Requirements

- Drupal `^11.3 || ^12`
- PHP `8.3+`
- [Paragraphs](https://www.drupal.org/project/paragraphs),
  Entity Reference Revisions, Field Group

## Install

```bash
composer require drupal/paragraphs_bundles
drush en paragraphs_bundles -y
```

Enable only the bundle sub-modules you need (each lives under
`modules/paragraph_bundle_*` inside the package). Example:

```bash
drush en paragraph_bundle_layout paragraph_bundle_card \
  paragraph_bundle_carousel -y
drush cr
```

Optional: install the demo recipe when available — see
`recipes/paragraphs_bundles_demo` (Workstream 2).

## Configuration

Editors configure each paragraph on the **Content** tab (body fields)
and **Display** tab (colours, spacing, borders, bundle-specific
options). Site builders can use the optional **pb_content** content
type (`paragraph_bundle_content`) for a dedicated landing-page width.

See **`docs/CONFIGURATION.md`** for Display tabs, the theme bridge,
`pb_content` max-width, and how dynamic CSS files work.

## Usage

### 1. Add a Paragraphs field

1. Structure → Content types → your type → Manage fields.
2. Add a **Paragraph** field (unlimited or limited cardinality).
3. Manage form display → widget **Paragraphs**; choose **Closed**
   or **Modal** edit mode as you prefer.
4. On the field settings, allow the paragraph types you enabled
   (e.g. `two_columns_bundle`, `card_two_columns_bundle`).

### 2. Pick bundle families

Enable sub-modules for the bundles you need. Bundles group by role:

| Family | Sub-module | Example paragraph types |
|--------|------------|-------------------------|
| Layout | `paragraph_bundle_layout` | one/two/three columns |
| Cards & hero | `paragraph_bundle_card`, `paragraph_bundle_hero` | card
columns, hero |
| Grid & media | `paragraph_bundle_grid`, `paragraph_bundle_image`,
`paragraph_bundle_image_background`, `paragraph_bundle_image_overlay`,
`paragraph_bundle_image_grid` | grid, image variants, lightbox grid |
| Interactive | `paragraph_bundle_accordion`, `paragraph_bundle_tabs`,
`paragraph_bundle_carousel`, `paragraph_bundle_slideshow`,
`paragraph_bundle_3d_carousel`, `paragraph_bundle_3d_flip_box`,
`paragraph_bundle_modal`, `paragraph_bundle_parallax` | accordion, tabs,
carousels, modal, parallax |
| Content & links | `paragraph_bundle_icon`, `paragraph_bundle_link`,
`paragraph_bundle_alert` | icon, link blocks, dismissible alert |
| Drupal embeds | `paragraph_bundle_block`, `paragraph_bundle_block_content`,
`paragraph_bundle_views`, `paragraph_bundle_webform`,
`paragraph_bundle_contact_form`, `paragraph_bundle_node_reference` | block,
custom block, view, webform, contact form, node teaser |
| Demo / simple | parent `paragraphs_bundles` | `simple_bundle` (minimal
example) |

Each sub-module ships optional config (paragraph type, fields, form and
view displays). Import happens on enable; no manual YAML copy for a
standard site.

### 3. Edit and save

When an editor saves a paragraph, Paragraphs Bundles writes a small CSS
file under `public://paragraphs_bundles/` and attaches it as a library.
No cache clear is required for colour or spacing changes.

Maintenance commands:

```bash
drush pb:verify-form-displays   # Display tab contract check
drush pb:regenerate-css         # Rebuild all dynamic CSS files
drush pb:regenerate-css --purge # Wipe folder, then rebuild
```

## Upgrading from 1.x

v2 is a **drop-in upgrade**: same field machine names, same stored
values, same `--pb-*` CSS variables. Replace the code, run updates,
verify form displays, regenerate CSS.

Full steps: **`docs/UPGRADE-V1-TO-V2.md`**. Sub-theme class renames:
**`docs/CSS-CLASS-MIGRATION.md`**.

## Screenshots

Bundle screenshots and a browsable demo will ship with the
`paragraphs_bundles_demo` recipe and the drupal.org project page
(Workstream 2). Until then, enable bundles on a test node and use the
front-end preview.

## Documentation

| Guide | Purpose |
|-------|---------|
| `docs/WHATS-NEW-IN-2.0.md` | **Start here if you are on 1.x** — what changed, and why |
| `docs/CONFIGURATION.md` | Display tab, theme bridge, dynamic CSS |
| `docs/DEVELOPING.md` | Services, hooks, adding a new bundle |
| `docs/UPGRADE-V1-TO-V2.md` | v1 → v2 upgrade |
| `docs/CSS-CLASS-MIGRATION.md` | v1 → v2 CSS class map |
| `docs/PB-V2-THEME-BRIDGE.md` | `--pb-*` ↔ `--r-*` contract |
| `docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md` | WCAG notes per bundle |
| `docs/reference/pb-css-variables.md` | `--pb-*` variable reference |

## What's new in v2

- Drupal 11.3+ / 12, PHP 8.3+, OOP hooks (`src/Hook/`).
- Per-paragraph dynamic CSS files (no inline `style="--pb-*"`).
- Element-id scoping (`#pb__{slug}-{id}__inner`) so editor settings
  beat theme defaults without `!important`.
- BEM class naming with Twig `pb_class` filter; stored field values
  unchanged from v1.
- Theme bridge for Solo, APEX, and neutral (any other theme).

## Maintainers

- **Alaa Haddad** —
  [drupal.org/u/flashwebcenter](https://www.drupal.org/u/flashwebcenter),
  [alaahaddad.com](https://alaahaddad.com)

## Support

- **Issues:** https://www.drupal.org/project/issues/paragraphs_bundles
- **Source:** https://git.drupalcode.org/project/paragraphs_bundles

## License

GPL-2.0-or-later. See `LICENSE.txt`.
