# Paragraph Bundle Icon

`icon_bundle` paragraph type with Google Material Symbols or an uploaded image
icon, optional title/body/link, and the standard Paragraphs Bundles Display tab.

## Dependencies

- `paragraphs_bundles` (parent)
- Core `image`, `link`, `options`, `text`
- The Material Symbols web fonts — **bundled with this module**, nothing to
  install
- Outbound access to `fonts.googleapis.com` and `fonts.gstatic.com` for the
  default font path — see below; the bundled files cover sites without it

## How the icon font loads

**By default a page downloads only the icons your site actually uses.** The
module collects every Material Symbols name in use, per style, and points the
stylesheet at a Google Fonts subset naming exactly those icons. Measured on a
page with one outlined icon: **9,118 bytes, against 3,411,436 for the bundled
Outlined font — 374x smaller.** All four variable axes (Fill, Weight, Grade,
Optical Size) still work, and icon names remain free text.

The subset URL is rebuilt whenever an icon paragraph is added, changed or
deleted. Nothing to configure, and no change to any template or library name.

**The three `.woff2` files still ship, in `fonts/`, and are the fallback.** The
local `@font-face` in `css/material-symbols-{outlined,rounded,sharp}.css` is
left in place for any style with no icons in use, and for any site whose icon
names cannot be validated. Sites with no outbound access to Google keep working
by overriding the family from a theme — see the override note below.

⚠ **Names are validated against `data/material-symbols-names.txt`**, the 4,284
names Google publishes. A name Google does not recognise is dropped silently by
their API, so an unvalidated typo would simply not render; a request where every
name is unrecognised would fall back to the complete 344 KB font. Google adds
icons over time, so a name newer than that file will not be recognised until the
list is refreshed.

2.0.0 briefly removed these files and required every site to supply the font
itself. That was a mistake and it was reverted: upgrading from 1.x silently
turned every icon into its ligature name in plain text — a paragraph set to
`home` rendered the word "home" — with no update path and no error anywhere.
Breaking working sites on upgrade is a worse outcome than the repository being
11 MB larger.

The fonts are Google's Material Symbols, licensed Apache-2.0 — see
`fonts/LICENSE-Material-Symbols.txt`. Apache-2.0 permits redistribution with
attribution, and is compatible with this project's GPL-2.0-**or-later** via the
"or later" clause (Apache-2.0 is GPLv3-compatible).

If you would rather not serve the bundled binaries, you can still override the
family from your theme — declare the same family names
(`Material Symbols Outlined`, `Material Symbols Rounded`,
`Material Symbols Sharp`) against your own woff2 or the Google Fonts
stylesheet, and your declaration wins by cascade order.

Sites that only use the **image icon** field (`pb_content_image_icon`) instead
of Material Symbols never touch these fonts.

## Enable

```bash
drush en paragraph_bundle_icon -y
drush cr
drush pb:regenerate-css
```

## v2 notes

- Display colors use per-paragraph dynamic CSS (`pb_dynamic_library`), not
  inline
  `--pb-*` styles on the inner wrapper.
- List display fields read stored keys via `paragraph.field.value` in Twig.
- GMS font-load reveal: JS scopes to `.paragraphs-bundles.pb-icon` per
  paragraph.
