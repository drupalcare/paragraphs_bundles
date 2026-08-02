# Link bundle — accessibility (WCAG / ARIA)

> **Private class:** `pb-link`, `pb-link-section`  
> **Status:** Phase B scoped (markup only)  
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)
> **Project authority:**
[`docs/requirements/accessibility.md`](../../../../docs/requirements/accessibility.md)

## Widget pattern

- **Pattern:** static
- **Interactive:** nested link sections only

Parent `link_bundle` is a container; each `link_section_bundle` renders a native
link (and optional plain-text title prefix).

## ARIA roles and attributes

None on paragraph shells. Links use native `<a>` semantics from the link field.

## Implementation files

- **Twig:** `templates/paragraph--link-bundle.html.twig`,
  `templates/paragraph--link-section-bundle.html.twig`
- **JavaScript:** none

## Keyboard

- Tab through each section link in DOM order
- Meaningful link text from title + URL field labels

## Screen reader

- Link field provides accessible name from configured link text
- Section title (when set) is plain text before the link in reading order

## Testing checklist

- [ ] Keyboard-only: reach every section link
- [ ] Display link colors on parent apply to nested section links
- [ ] External/target links behave per link field configuration
- [ ] High-contrast / forced-colors: focus indicators visible on links
