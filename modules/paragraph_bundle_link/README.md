# Paragraph Bundle Link

Two paragraph types:

- `link_bundle` — container with title and nested link sections (dedicated
  `paragraph--link-bundle.html.twig` + dynamic CSS).
- `link_section_bundle` — single title + URL link (minimal Twig).

## Dependencies

- `paragraphs_bundles` (parent)
- Core `link`, `options`, `text`
- `paragraphs`, `entity_reference_revisions`
- `link_attributes` (admin link widget on link sections)

## Enable

```bash
drush en paragraph_bundle_link -y
drush cr
drush pb:regenerate-css
```

## Demo QA

1. Add a **Link** paragraph; set title and add one or more **Link section**
   children.
2. Set Display colors on the parent (background, text, link colors).
3. Confirm dynamic CSS selector `#pb-link-{id} > .pb-ui-inner` in the
   generated file under `public://paragraphs_bundles/`.
4. Confirm nested section links inherit `--pb-lk` / `--pb-lk-h` from the parent
   `.pb-link__inner :where(a)` scope.
5. Edit form: Content tab order is title → tag → alignment → link sections;
   link colors appear under Display → Colors after border hover.

## v2 notes

- `link_bundle` uses dedicated Twig with L0 `pb-link` and dynamic CSS on
  `#pb-link-{id} > .pb-ui-inner` (`.pb-link__inner`).
- `link_section_bundle` has L0 `pb-link-section`; no Display tab; link colors
  cascade from the parent inner scope (`:where(a)` rules).
