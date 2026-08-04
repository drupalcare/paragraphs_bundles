# Upgrading Paragraphs Bundles from v1 to v2

v1 sites are **drop-in upgrades**: replace the module code, run database
updates, and existing paragraph content keeps working. Field machine names, CSS
custom property names and DOM ids are unchanged, new v2 fields are added
automatically, and form displays are updated in place. **No manual config
import is required.**

Read the two warnings below before you start.

---

## Before you upgrade

### 1. This upgrade is one-way

There is no downgrade path. The update hooks rewrite form and view displays and
add field storages; nothing reverses them. **Your backup is the only way back.**

```bash
# Database and files. Do both.
drush sql:dump --gzip --result-file=../backup-before-pb-v2.sql
tar czf ../backup-before-pb-v2-files.tar.gz web/sites/default/files
```

Put the site in maintenance mode for the duration — `drush updb` leaves config
partially migrated if it is interrupted, and editors saving paragraphs during
the run can write against half-updated displays:

```bash
drush state:set system.maintenance_mode 1 --input-format=integer
drush cr
```

### 2. You must be on the last 1.x release first

v2 declares `hook_update_last_removed()` = **9004**, so Drupal refuses to
upgrade a site whose `paragraphs_bundles` schema is below that, with
"requires an intermediate version". This is deliberate: v1's `9001`–`9004`
were removed in v2, and a site below 9004 cannot reach a consistent state
without them.

Check first:

```bash
drush php:eval "print \Drupal::keyValue('system.schema')->get('paragraphs_bundles');"
```

- **9004** — you are ready.
- **Lower** — update to the newest 1.x release, run `drush updb`, then come
  back.
- **9005 or higher** — you are already on v2.

### 3. The Material Symbols font is no longer bundled

If you use `paragraph_bundle_icon` with Material Symbols icons, v2 does **not**
ship the font (it is Apache-2.0, which is incompatible with this project's
GPL-2.0-or-later). Icons will render as their ligature name in plain text — a
`home` icon shows the word "home" — until your site supplies the font.
`modules/paragraph_bundle_icon/README.md` gives both ways to do that. Sites
using the image-icon field instead are unaffected.

---

## Upgrade steps

```bash
# 1. Replace the module code with v2 (composer or copy).

# 2. Rebuild caches BEFORE running updates. See "Expected warnings" below.
drush cr

# 3. Run database updates.
drush updb -y

# 4. Verify and rebuild the generated CSS.
drush cr
drush pb:verify-form-displays
drush pb:regenerate-css

# 5. Leave maintenance mode.
drush state:set system.maintenance_mode 0 --input-format=integer
```

### Expected warnings during the drop-over

While the database still holds the v1 extension list, Drupal emits warnings for
five sub-modules whose `.module` file v2 removed:

```
include_once(.../paragraph_bundle_3d_carousel.module): Failed to open stream
```

The five are `3d_carousel`, `3d_flip_box`, `image_grid`, `image_overlay` and
`layout`. **They are harmless** and stop once the update rebuilds caches.
Running `drush cr` before `drush updb` (step 2 above) avoids most of them.

### If an update fails part-way

Restore the backup. Do not re-run `drush updb` against a half-migrated
database and do not hand-edit `system.schema` to skip a hook — the hooks are
idempotent and safe to repeat, but only from a consistent starting point.

```bash
drush sql:drop -y
gunzip < ../backup-before-pb-v2.sql.gz | drush sql:cli
drush cr
```

Then report the failure with the output of `drush updb` before retrying.

---

## What happens to existing sites

| Area | v1 behaviour | v2 upgrade |
|------|--------------|------------|
| Paragraph content | Inline `style="--pb-*"` on inner wrapper | Unchanged in the database; a post-update writes per-paragraph CSS files |
| Field machine names | `pb_content_*`, `pb_display_*` | **Unchanged** |
| CSS class names / DOM ids | 30 bespoke id patterns, legacy `pb__` classes | Ids standardised to `pb-{slug}-{id}`; structural classes renamed — see `CSS-CLASS-MIGRATION.md` |
| Title tag / alignment | Missing on some bundles | `9006` imports instances and wires the Content tab |
| Link colour fields | Did not exist | `9006` creates instances and wires the Display tab |
| Display tab layout | Flat list / legacy group names | `9006` nests into Colors / Border / Spacing / Size |
| Manage display (`pb_display_*`) | Often visible formatters | `9006` hides all of them on view displays |
| Edit-form field order | Whatever v1 left, plus what `9006` computed | `9008` puts it back to the order a fresh v2 install ships — see below |
| Sub-theme CSS reading `--pb-*` | From the inline style | From the per-paragraph CSS file, same variable names |

Existing paragraph **values** are preserved. Editors see the new fields and the
grouped Display tabs after `drush updb`.

---

## Update hook chain

All parent hooks live in `paragraphs_bundles.install`. They run in numeric
order, then every sub-module hook, then post-updates last.

| Hook | Purpose |
|------|---------|
| **`9005`** | Shared field storages (title tag/alignment, link colours) plus title help text and optional flags |
| **`9006`** | Wire all shared v2 fields on **every** PB bundle, nest the Display tab, hide `pb_display_*` on view displays |
| **`9007`** | Repair v1 config that does not match v2's tightened config schema (node type `help`, a stray form-display `label`, range defaults stored as strings, null `target_bundles_drag_drop`) |
| **`9008`** | Realign form and view display layout with the config a fresh install imports — see below |
| `paragraph_bundle_content_update_9001` | Surgical `pb_content` form-display merge, preserving site-added fields |
| `paragraph_bundle_image_background_update_9001` | Bundle-specific display wiring |
| `paragraph_bundle_3d_carousel_update_9001` | Bundle-specific display wiring |
| **post-update** `regenerate_dynamic_css` | Batched backfill of every per-paragraph and `pb_content` CSS file |

The three sub-module hooks are declared in `hook_update_dependencies()` to run
**after** the parent's `9006`. Without that they would sort before it by module
name, which is the wrong order.

The CSS backfill is a **post-update** rather than another numbered hook, so it
is structurally guaranteed to run after every update hook instead of relying on
a numbering convention someone has to remember. A fresh v2 install registers it
as already-invoked, so new sites never run it.

Every hook is **idempotent** and safe to run again.

### `9006` — the catch-all

Discovers every paragraph bundle that ships `pb_display_bg`, then for each
calls `paragraphs_bundles_pb_wire_shared_fields_for_bundle()`:

1. Ensures shared field **storages** exist (title tag/alignment, link colours).
2. Imports field **instances** from optional YAML when the owning sub-module
   ships them; otherwise creates link field instances programmatically.
3. Normalizes legacy v1 field_group names (`group_tab_*`, `group_tabs_wrapper`,
   `group_content`, `group_display`).
4. Wires title fields onto the **Content** tab (when `pb_content_title` exists).
5. Wires link colour widgets into **Display → Colors**.
6. Sets Colors widget **weights** (opacity 16, bg 17, …) — field_group sorts by
   weight, not by children order.
7. Nests Display tab fields into collapsible sections.
8. Sorts Additional display fields by widget type.
9. Hides all **`pb_display_*`** fields on paragraph **Manage display**.

### `9008` — same version, same edit forms

`9006` wires the shared fields onto displays that already exist, so it has to
work out weights from what it finds. A fresh install imports them from
`config/optional` instead. The two therefore disagreed, and on most bundles by
enough to change the **order fields appear in** on the paragraph edit form — on
`icon_bundle`, two content fields ended up scattered among the display fields.
One module version, two different admin UIs depending on how the site got here.

`9008` treats the shipped `config/optional` file as the source of truth for
layout and re-applies it:

- component **weights**, for fields this module ships;
- **field group weights and child order**;
- stale entries left in a display's `hidden` list for a field that is also in
  `content` — a contradiction `9006` could leave behind and that nothing else
  repairs.

It is deliberately conservative about everything else. A field **your site
added** keeps its weight, a shipped field **you chose to hide** stays hidden,
and a field you dragged into one of this module's groups stays in it (it is
moved to the end of that group rather than dropped).

**What you may notice:** if you reordered this module's own `pb_*` fields on a
paragraph's *Manage form display*, that reordering is reset to the shipped
order. Re-apply it after upgrading; it will not be reset again.

---

## Enabling a sub-module after the parent upgrade

If the parent was upgraded first and a `paragraph_bundle_*` sub-module is
enabled later, optional config imports the bundle YAML; run `drush updb` so
`9006` wires the shared fields and nests the Display groups on active config.

---

## Sub-theme / Twig overrides

If your sub-theme overrides PB Twig templates:

1. Copy the v2 template from the module.
2. Re-apply your overrides.
3. Remove any dependency on inline `style="--pb-*"` — v2 attaches a
   per-paragraph library instead.

CSS custom property names are unchanged, but **structural class names and
outer element ids were both renamed** in v2. If your theme targets PB classes
or ids, work through [`CSS-CLASS-MIGRATION.md`](CSS-CLASS-MIGRATION.md) — it
lists all 299 class renames, the 69 frozen tokens that did not change, and the
full id table.

**Element ids changed for 29 of the 30 bundles that had one in v1.** v2 emits
one derivable form everywhere — `#pb-{bundle-slug}-{paragraph id}` — replacing
30 bespoke v1 patterns, most of them abbreviations (`pb__nf-`,
`pb__accor-wrap-`, `pb__slide-wrap-`). CSS or JS naming an old id stops
applying; nothing else is affected, and no stored data changes.

| Bundle | v1 id | v2 id |
|---|---|---|
| Accordion | `#pb__accor-wrap-{id}` | `#pb-accordion-{id}` |
| Node reference | `#pb__nf-{id}` | `#pb-node-reference-{id}` |
| Slideshow | `#pb__slide-wrap-{id}` | `#pb-slideshow-{id}` |
| Parallax | `#pb__parallax-{id}` | `#pb-parallax-{id}` |
| 3D carousel | `#pb__caro-wrap-{id}` | `#pb-3d-carousel-{id}` |
| … 25 more | | [full table](CSS-CLASS-MIGRATION.md#outer-element-ids-v1--v2) |

Only `card_text_section_bundle` keeps its v1 id — it was already `pb-`.

---

## Helpers (for custom code / Drush one-offs)

```php
require_once \Drupal::service('extension.list.module')
  ->getPath('paragraphs_bundles') . '/paragraphs_bundles.install';

$bundles = paragraphs_bundles_get_pb_bundles();
paragraphs_bundles_pb_upgrade_all_bundles();
paragraphs_bundles_pb_wire_shared_fields_for_bundle('block_bundle');
```

Verify active config:

```bash
drush pb:verify-form-displays
```

The canonical Display group map is
`paragraphs_bundles_pb_display_subgroup_definitions()` in
`paragraphs_bundles.install`.
