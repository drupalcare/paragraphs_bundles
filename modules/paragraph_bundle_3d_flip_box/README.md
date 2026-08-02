# Paragraph Bundle 3D Flip Box

`3d_flip_box_bundle` paragraph type — CSS 3D flip animation with nested front
and back paragraph regions.

## Dependencies

- `paragraphs_bundles` (parent)
- Core `options`
- `paragraphs`, `entity_reference_revisions`

## Enable

```bash
drush en paragraph_bundle_3d_flip_box -y
drush cr
drush pb:regenerate-css
```

If the module was already enabled before v2 shared fields shipped, reinstall
so optional config imports link color fields:

```bash
drush pmu paragraph_bundle_3d_flip_box -y
drush en paragraph_bundle_3d_flip_box -y
drush cr
```

## Demo QA

1. Add a 3D Flip Box paragraph with front and back nested content.
2. Set flip axis (X/Y) and box height (px).
3. Set Display colors; confirm dynamic CSS on `#pb__3d-flip-box-{id} >
   .paragraph__inner`.
4. Hover the box — confirm 3D flip animation.

## v2 notes

- Dynamic CSS on inner for Display colours; flip axis class and inline height
  px on `.pb-3d-flip-box__scene` in Twig.
- Title block weights 1–3 on Content tab.
