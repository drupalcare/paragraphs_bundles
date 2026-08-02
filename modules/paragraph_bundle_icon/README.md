# Paragraph Bundle Icon

`icon_bundle` paragraph type with Google Material Symbols or an uploaded image
icon, optional title/body/link, and the standard Paragraphs Bundles Display tab.

## Dependencies

- `paragraphs_bundles` (parent)
- Core `image`, `link`, `options`, `text`
- **The Material Symbols web font, supplied by your site** — see below

## The Material Symbols font is not bundled

Version 1.x shipped the three Material Symbols `.woff2` files inside this
module. 2.0.0 removed them: they are Apache-2.0 licensed, which is not
compatible with this project's GPL-2.0-or-later, and drupal.org does not
package bundled third-party font binaries. **Your site must now supply the
font.** The module still ships the class rules that turn the ligature into a
glyph (`css/material-symbols-{outlined,rounded,sharp}.css`), only the binary is
gone.

If the font is missing, the icon renders as its ligature name in plain text —
a paragraph set to `home` shows the word "home". That is the symptom.

Pick one of:

**1. Load it in your theme (simplest).** Add the Google Fonts stylesheet for
each style you use to your theme's library. Note this makes a request to
`fonts.googleapis.com` on every page, which has privacy/GDPR consequences you
should confirm are acceptable for your site:

```html
<link rel="stylesheet"
  href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
```

**2. Self-host it (recommended for privacy).** Download the woff2 from
[Google Fonts](https://fonts.google.com/icons), put it somewhere your site
serves, and declare the family in your theme's CSS with the exact family name
this module's classes reference — `Material Symbols Outlined`,
`Material Symbols Rounded` or `Material Symbols Sharp`:

```css
@font-face {
  font-family: 'Material Symbols Outlined';
  font-style: normal;
  font-weight: 100 700;
  src: url("/path/to/material-symbols-outlined.woff2") format('woff2');
  font-display: swap;
}
```

Sites that only use the **image icon** field (`pb_content_image_icon`) instead
of Material Symbols need none of this.

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
