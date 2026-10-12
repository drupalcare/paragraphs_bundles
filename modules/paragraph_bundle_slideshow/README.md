# Paragraph Bundle Slideshow

Two paragraph types:

- `slideshow_bundle`: wrapper with title, nested slide sections, arrows, dots,
  and autoplay interval
- `slideshow_section_bundle`: single slide (body nested paragraphs + optional
  slide title); rendered by the parent template

## Dependencies

- `paragraphs_bundles` (parent)
- `paragraph_bundle_image`
- Core `options`
- `paragraphs`, `entity_reference_revisions`

## Enable

```bash
drush en paragraph_bundle_slideshow -y
drush cr
drush pb:regenerate-css
```

## Demo QA

1. Add a Slideshow paragraph with 2+ section children (each with body items).
2. Set bottom navigation (dots, numbers, play/pause) and prev/next arrows.
3. Content tab order: Title → Title tag → Title alignment → Sections.
4. Confirm dynamic CSS on `#pb-slideshow-{id} > .pb-ui-inner`.
5. Test keyboard focus, dot navigation, arrows, and autoplay pause on hover.

## v2 notes

- Display colors and active slide color (`--pb-bt-a`) use per-paragraph dynamic
  CSS, not inline `--pb-*`.
- Outer wrapper id prefix is `pb-slideshow-{id}` (v1 parity).
