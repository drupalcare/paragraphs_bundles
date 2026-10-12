# Paragraph Bundle Hero

One paragraph type:

- `hero_bundle`: full-width background image with a positioned text overlay
  (title, body, and an optional wrapper link)

The image is a required reference to a `pb_media_image` item, rendered in the
`pb_media_responsive_wide` view mode.

## Display Classes

`pb_display_hero` places the overlay inside the hero container. It is required
and defaults to `pb__ds_middle`. The nine positions are:

| Value | Position |
|---|---|
| `pb__ds_top_left` | top left |
| `pb__ds_top_middle` | top center |
| `pb__ds_top_right` | top right |
| `pb__ds_left` | middle left |
| `pb__ds_middle` | center (default) |
| `pb__ds_right` | middle right |
| `pb__ds_bottom_left` | bottom left |
| `pb__ds_bottom_middle` | bottom center |
| `pb__ds_bottom_right` | bottom right |

## Dependencies

Declared in `paragraph_bundle_hero.info.yml`:

- `paragraphs_bundles` (parent, which brings `paragraphs` and
  `entity_reference_revisions`)
- `paragraph_bundle_image` (media type `pb_media_image` and its responsive
  image styles)

## Enable

```bash
drush en paragraph_bundle_hero -y
drush cr
drush pb:regenerate-css
```

## Demo QA

1. Add a Hero paragraph with an image, a title, and body text.
2. Step through the nine Display Classes values and confirm the overlay moves.
3. Set Display colors and confirm dynamic CSS lands on
   `#pb-hero-{id} .pb-hero__text-inner`.
4. Add an image link and link title; confirm the whole overlay becomes the
   link and carries the `title` attribute.
5. Confirm width, border, border radius, padding, and shadow classes apply to
   `.pb-hero__text-inner`, and margin to `.hero-bundle-inner`.

## v2 notes

- Display colors use per-paragraph dynamic CSS, not inline `--pb-*`.
- The dynamic CSS target is the text overlay, not the outer wrapper, so
  background color tints the overlay above the image.
- Layout CSS ships in `paragraph_bundle_hero/paragraph-bundle-hero`.
