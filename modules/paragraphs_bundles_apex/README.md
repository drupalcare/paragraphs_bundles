# Paragraphs Bundles APEX

An optional bridge between Paragraphs Bundles and
[APEX](https://www.drupal.org/project/apex). It lets a PB media image be
rendered by APEX's responsive image formatter, which sizes pictures from the
**width of the box they land in** rather than from viewport breakpoints.

**It changes nothing until a site chooses it.** Enabling this module only adds
three view modes to a select list. Existing sites keep the PB defaults.

## What it installs

- Media view modes `pb_apex_wide`, `pb_apex_narrow`, and `pb_apex_card` on the
  `pb_media_image` media type owned by `paragraph_bundle_image`.
- One entity view display per mode, each setting `pb_image_field` to the
  `apex_responsive_image` formatter:

  | View mode | APEX mode | Ratio |
  |---|---|---|
  | PB Media APEX Wide | `apex_hero` | 16:9 |
  | PB Media APEX Narrow | `apex_content` | 4:3 |
  | PB Media APEX Card | `apex_card` | 3:2 |

This module ships no image styles of its own — APEX owns those.

## Dependencies

Declared in `paragraphs_bundles_apex.info.yml`:

- `apex_responsive_image` (which brings `apex`, `apex_layout`, `apex_width`,
  and `apex_theme_settings` transitively)
- `paragraphs_bundles` and `paragraph_bundle_image` (which owns the
  `pb_media_image` media type these displays extend)
- Core `media`

**Not core `responsive_image`, deliberately.** Measured 2026-09-23: APEX's three
modes map plain `image_style` entities — five derivatives and a fallback each —
and reference a `responsive_image_style` entity zero times. Computing `sizes`
from the slot width is the whole point of its approach, so it never uses core's
breakpoint-mapped responsive images. Declaring that dependency would force every
installing site to enable a module this bridge never touches.

## Enable

```bash
drush en paragraphs_bundles_apex -y
drush cr
```

## Use

`/admin/structure/paragraphs_type/hero_bundle/display/default` → set
`pb_content_image` to **Rendered as PB Media APEX Wide**.

The same applies to any bundle carrying `pb_content_image`. Five of PB's eight
image bundles map onto a shipped mode:

| Paragraph bundle | Use |
|---|---|
| `hero_bundle`, `image_wide_bundle`, `image_overlay_bundle` | `pb_apex_wide` |
| `image_narrow_bundle` | `pb_apex_narrow` |
| `card_image_section_bundle` | `pb_apex_card` |

To go back, select the original PB view mode again. This module never edits
Paragraphs Bundles' own view modes, so they are still there, untouched.

⚠ **Do not switch `parallax_section_bundle`.** Its display uses the `image_url`
formatter to emit a CSS background, not an `img` element. The APEX formatter
renders an element, so swapping it there breaks the parallax rather than
degrading it.

## Why bother

Without APEX, PB picks an image by **viewport**: `pb_responsive_image_wide`
maps six screen widths to six fixed image styles. That is correct only when the
image fills the screen. A 300px image in a sidebar on a 1920px monitor gets a
1920px-class file.

APEX computes the **actual slot width** — site width minus padding, minus
container gaps, times the slot's grid fraction — and puts that in `sizes`. Its
own measurement, on a real site: 16 images spanning 290px to 1393px all carried
one identical `sizes` string, 12 downloaded the wrong derivative, and 2060 KB
was fetched where 1238 KB was correct.

The emitted attribute leads with `auto`, so browsers that support it measure
the rendered box exactly. The computed value is the fallback for those that do
not.

## ⚠ Two behavioural differences to know before switching

**1. The crop shape changes.** APEX's modes are a strict ratio at every
breakpoint. PB's wide styles are not — they run 1.50, 2.07, 2.88, 3.37, 2.80,
2.83 across the six. Switching a hero is a visible design change, not just a
file-size one.

**2. You lose the loading control — but the default does not change.** An
earlier version of this file claimed `pb_media_responsive_wide` loads its image
eagerly. **That was false.** Measured 2026-09-23 against both
the shipped config and a real site: every one of PB's media displays that
carries the setting ships `attribute: lazy`, `pb_media_responsive_wide`
included.

What actually changes is who decides. PB's displays **expose** `image_loading`,
so a site builder can switch one to `eager` on the Manage Display form. The
APEX formatter exposes no loading setting at all: it always emits
`loading="lazy"` and leaves LCP promotion to `apex_performance`, which unsets
that attribute and adds the preload. `sizes="auto"` is only valid on a lazy
image, which is the trade — lazy images get exact browser-measured sizing,
while a promoted LCP image falls through to the computed value.

Source images should be at least 1920px wide. `apex_ri_hero_xl` is 1920 and
will upscale anything narrower.

## If the view modes are missing

`apex_responsive_image` is a **hard** dependency, so Drupal always enables it
first — this module cannot be installed "before APEX", and `config/optional`
always has what it needs at import time.

If the three view modes are missing anyway (a partial import, or someone deleted
them), reinstalling the module recreates them:

```bash
drush pm:uninstall paragraphs_bundles_apex -y && drush en paragraphs_bundles_apex -y
```

⚠ That discards any customisation you made to those three displays. The backfill
`hook_install()` runs is idempotent and never overwrites an existing display, so
it is safe on its own; it is the uninstall that removes them.

⚠ **There is deliberately no update hook for this, and an earlier version of this
README was wrong to tell you to run one.** A `hook_update_N` shipped in a module's
first release can never run: core records a new module's schema version as the
highest available update number at install time
(`ModuleInstaller.php:407-421`), so `drush updatedb` skips it for every site,
forever. Measured on a real install: recorded version 10001, only available
update 10001.

## If an APEX mode is deleted

The three shipped displays each name an APEX responsive mode — `apex_hero`,
`apex_content`, `apex_card`. Those are **config entities**, and APEX ships a
delete form for them, so an administrator can remove one.

`ApexResponsiveImageFormatter::viewElements()` returns an **empty render array**
when the mode it is given does not load, so the image simply disappears: no
error, no log entry, nothing in the markup. Drupal's config dependency system
does not catch it either — the formatter does not declare the mode entity it
depends on, so nothing links the display to the mode.

This module therefore reports it on the status report at
`/admin/reports/status`, naming each display and the mode it cannot find.
Restore the mode, or point the display at one that exists.

## Demo QA

1. Enable the module; confirm nothing on the site changes.
2. Confirm `/admin/structure/media/manage/pb_media_image/display` lists the
   three APEX view modes.
3. Switch `hero_bundle`'s `pb_content_image` to **PB Media APEX Wide**.
4. View the page: the `<picture>` with six `<source media=…>` elements is
   replaced by one `<img>` with a width-descriptor `srcset` and
   `sizes="auto, …"`.
5. Switch back and confirm the original markup returns.
