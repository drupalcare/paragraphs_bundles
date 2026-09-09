# Paragraph Bundle Icon

`icon_bundle` paragraph type with Google Material Symbols or an uploaded image
icon, optional title/body/link, and the standard Paragraphs Bundles Display tab.

## Dependencies

- `paragraphs_bundles` (parent)
- Core `image`, `link`, `options`, `text`
- The Material Symbols web fonts — **bundled with this module**, nothing to install

## The Material Symbols fonts are bundled

The three Material Symbols `.woff2` files ship inside this module, in `fonts/`,
and `css/material-symbols-{outlined,rounded,sharp}.css` declare the matching
`@font-face`. **Icons work on install with nothing to configure.**

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
