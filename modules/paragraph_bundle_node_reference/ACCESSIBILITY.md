# Node Reference bundle — accessibility (WCAG / ARIA)

> **Private class:** `pb-node-reference`  
> **Status:** implemented (maintainer browser QA pending)
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)
> **Project authority:**
[`docs/requirements/accessibility.md`](../../../../docs/requirements/accessibility.md)

## Widget pattern

- **Pattern:** static
- **Interactive:** no

Semantic HTML, heading order, sufficient color contrast, and keyboard-accessible
links/controls. No custom widget roles unless interaction is added.

## ARIA roles and attributes

- `article`
- `link`

See the parent playbook for required attributes per pattern.

## Implementation files

- **Twig:** `templates/paragraph--node-reference-bundle.html.twig`
- **JavaScript:** none (markup/CSS only)

## Keyboard

- Teaser links and headings from referenced node

## Screen reader

- All interactive controls have an accessible name.
- State changes are exposed via ARIA attributes and/or `Drupal.announce()`.
- Hidden content uses `aria-hidden`, `inert`, or `display: none` consistently.

## Testing checklist

- [ ] Keyboard-only walkthrough (no mouse)
- [ ] VoiceOver (macOS) or NVDA (Windows) announces name, role, and state
- [ ] Focus order is logical; focus is not lost after dynamic updates
- [ ] `prefers-reduced-motion: reduce` does not break required functionality
- [ ] High-contrast / forced-colors: focus indicators remain visible
