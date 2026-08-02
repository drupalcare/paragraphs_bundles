# Upgrading Paragraphs Bundles from v1 to v2

> **Install parity:** v2 must expose the **same fields in the same order** on
> **fresh install** and **v1 drop-over**. See
> [`docs/PB-V2-INSTALL-PARITY.md`](PB-V2-INSTALL-PARITY.md) — mandatory testing
> for v2 release readiness.
>
> **Maintainers:** trash local DB and test both paths like real users. See
> [`docs/PB-V2-TESTING-WORKFLOW.md`](PB-V2-TESTING-WORKFLOW.md).

v1 sites are **drop-in upgrades**: replace the module code, run database
updates,
and existing paragraph content keeps working. New v2 fields are added
automatically; form displays are updated in place. **No manual config import is
required** for the shared field wiring.

---

## Maintainer steps

```bash
# 1. Replace modules/contrib/paragraphs_bundles with v2 (composer or copy).
# 2. Run update hooks (see table below).
drush updb -y

# 3. Clear caches, verify form displays, rebuild CSS.
drush cr
drush pb:verify-form-displays
drush pb:regenerate-css
```

Optional: re-import sub-module optional config only when you have **custom**
form/view display overrides you need to merge — not required for the standard
v2 field wiring.

---

## What happens to existing sites

| Area | v1 behaviour | v2 upgrade |
|------|----------------|------------|
| Paragraph content | Inline `style="--pb-*"` on inner wrapper | Unchanged in
DB; `update_9005` writes CSS files |
| Field machine names | `pb_content_*`, `pb_display_*` | **Unchanged** — same
names |
| CSS class names / DOM ids | `pb__{slug}-{id}`, BEM classes | **Unchanged** |
| Title tag / alignment | Some bundles missing on form | `9006` imports
instances + wires Content tab |
| Link color fields | Did not exist in v1 | `9006` creates instances + wires
Display tab |
| Display tab layout | Flat field list / legacy group names | `9006` nests into
Colors / Border / Spacing / Size |
| Manage display (`pb_display_*`) | Often visible formatters | `9006` hides all
on view displays |
| Sub-theme CSS reading `--pb-*` | From inline style | From per-paragraph CSS
file (same variable names) |

Existing paragraph **values** are preserved. Editors see new fields (link
colors,
title tag/alignment where applicable) and grouped Display tabs after `drush
updb`.

---

## Update hook chain (v2 pre-release)

All hooks live in `paragraphs_bundles.install`. Sub-modules do **not** need
their own update hooks for shared paragraph fields.

| Hook | Purpose |
|------|---------|
| **`9001`** | Shared field storages (title tag/alignment, link colors) + title
help text and optional flags |
| **`9005`** | **Batch backfill** — per-paragraph dynamic CSS files for all
existing paragraphs |
| **`9006`** | **Wire ALL shared v2 fields, nest Display tab, hide
`pb_display_*` on view displays — every PB bundle** |

**`paragraph_bundle_content` sub-module:** single hook **`9001`** —
disable-region
values, field help, and surgical pb_content form display merge (preserves
site-added fields on the node type).

v2 development used fragmented hooks `9007`–`9015`; those are **removed** before
release. Logic from old `9014`/`9015` is inside **`9006`**. Dev databases that
already ran the old chain must be **reset** (trash DB, retest from v1 baseline)
—
see `docs/PB-V2-TESTING-WORKFLOW.md`.

### `9006` — catch-all for existing bundles

Discovers every paragraph bundle that ships `pb_display_bg`, then for each calls
`paragraphs_bundles_pb_wire_shared_fields_for_bundle()`:

1. Ensures shared field **storages** exist (title tag/alignment, link colors).
2. Imports field **instances** from optional YAML when the owning sub-module
   ships them; otherwise creates link field instances programmatically.
3. Normalizes legacy v1 field_group names (`group_tab_*`, `group_tabs_wrapper`,
   `group_content`, `group_display`).
4. Wires title fields onto the **Content** tab (when `pb_content_title` exists).
5. Wires link color widgets into **Display → Colors**.
6. Sets Colors widget **weights** (opacity 16, bg 17, … — field_group sorts by
   weight, not children list order).
7. Nests Display tab fields into collapsible sections.
8. Sorts Additional display fields by widget type.
9. Hides all **`pb_display_*`** fields on paragraph **Manage display** (view
   displays).

Safe to run multiple times — every step is **idempotent**.

---

## Enabling a sub-module after parent upgrade

If the parent was upgraded first and a `paragraph_bundle_*` sub-module is
**enabled later**, optional config imports the bundle YAML; run `drush updb` so
`update_9006` wires shared fields and nests Display groups on active config.

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

Verify repo optional YAML (from mix repo root):

```bash
php modules/v2/paragraphs_bundles/scripts/verify-bundle-form-display.php --all
```

---

## Sub-theme / Twig overrides

If your sub-theme overrides PB Twig templates:

1. Copy the v2 template from the module.
2. Re-apply your overrides.
3. Remove any dependency on inline `style="--pb-*"` — v2 uses
   `attach_library(pb_dynamic_library)` instead.

Class names and CSS custom property names are unchanged.

---

## Porting new bundles (developers)

See `docs/BUNDLE-PORT-WORKFLOW.md` for the full port checklist. Canonical
Display
group map: `paragraphs_bundles_pb_display_subgroup_definitions()` in
`paragraphs_bundles.install`.
