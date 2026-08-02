# Paragraph Bundle Parallax

Two paragraph types:

- `parallax_bundle` — wrapper with optional title and nested section items
- `parallax_section_bundle` — image background, body text, legacy CSS or JS
  parallax scroll effects, breakpoint CSS libraries

## Dependencies

- `paragraphs_bundles` (parent)
- `paragraph_bundle_image` (`pb_media_parallax` view mode)
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
4. Confirm dynamic CSS on wrapper `#pb__parallax-{id} > .paragraph__inner`.
5. Confirm section colors on `#pb__parallax-{id} .pb__parallax-text`.
6. Test reduced-motion and breakpoint-specific CSS libraries.

## v2 notes

- Display colors use per-paragraph dynamic CSS (not inline `--pb-*` on text).
- Section keeps inline `--section-height`, animation vars on `.pb__parallax-bg`,
  and `background-image` URL (v1 behavior).
- Both bundles share outer id prefix `pb__parallax-{id}`.
