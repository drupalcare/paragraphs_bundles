# Paragraph Bundle Node Reference

`node_reference_bundle` paragraph type: references `pb_node_reference` nodes
and renders them by view mode (title only, teaser text, slideshow, or 3D
carousel).

## Dependencies

- `paragraphs_bundles` (parent)
- Core `node`, `options`, `image`
- `paragraphs`, `entity_reference_revisions`

Optional: enable `paragraph_bundle_slideshow` and
`paragraph_bundle_3d_carousel` when those bundles are ported for full
slideshow / 3D carousel node view modes.

## Enable

```bash
drush en paragraph_bundle_node_reference -y
drush cr
drush pb:regenerate-css
```

## Demo QA

1. Create `pb_node_reference` nodes with body and image.
2. Add a Node Reference paragraph; pick a view mode and reference a node.
3. Confirm only the matching NF field shows on the Content tab (others hidden).
4. Content tab order: Title → Title tag → Title alignment → View mode → NF
   fields.
5. Set Display colors; confirm dynamic CSS on `#pb-node-reference-{id} >
   .pb-ui-inner`.

## v2 notes

- L0 `pb-node-reference` on paragraph root; L1 `pb-node-reference__inner`.
- Display colors use per-paragraph dynamic CSS, not inline `--pb-*`.
- Outer wrapper id prefix is `pb-node-reference-{id}` (v1 parity).
- Admin conditional fields: `#states` via `PbnrFieldWidgetHook` plus
  `pb-conditional-fields` JS behavior.
