# Paragraph Bundle Parallax

Two paragraph types:

- `parallax_bundle`: wrapper with optional title and nested section items
- `parallax_section_bundle`: image background, body text, legacy CSS or JS
  parallax scroll effects, breakpoint CSS libraries

## Dependencies

- `paragraphs_bundles` (parent)
- `paragraph_bundle_image` (media type `pb_media_image`; the `pb_media_parallax`
  view mode it is rendered in ships here, not there)
- Core `media_library`, `options`, `text`
- `paragraphs`, `entity_reference_revisions`

## Enable

```bash
drush en paragraph_bundle_parallax -y
drush cr
drush pb:regenerate-css
```

## Demo QA

1. Add a Parallax paragraph with one or more section children.
2. Set scroll effect (legacy CSS vs JS), speed, breakpoints, and height.
3. Content tab on wrapper: Title → Title tag → Title alignment → Sections.
4. Confirm dynamic CSS on wrapper `#pb-parallax-{id} > .pb-ui-inner`.
5. Confirm section colors on `#pb-parallax-{id} .pb-parallax__text`.
6. Test reduced-motion and breakpoint-specific CSS libraries.

## v2 notes

- Display colors use per-paragraph dynamic CSS (not inline `--pb-*` on text).
- Section keeps inline `--section-height`, animation vars on `.pb-parallax__bg`,
  and `background-image` URL (v1 behavior).
- The two bundles now have distinct ids: `pb-parallax-{id}` and
  `pb-parallax-section-{id}`. In v1 both were `pb__parallax-{id}`.
