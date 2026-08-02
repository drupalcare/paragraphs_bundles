# Contact Form bundle — accessibility (WCAG / ARIA)

> **Track A:** no bundle-private L0 class — uses parent `paragraph--default`.  
> **Status:** implemented (maintainer browser QA pending)  
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)
> **Project authority:**
[`docs/requirements/accessibility.md`](../../../../docs/requirements/accessibility.md)

## Widget pattern

- **Pattern:** form
- **Interactive:** delegates to Contact module form

## Implementation files

- **Twig:** parent `paragraphs_bundles/templates/paragraph--default.html.twig`
- **JavaScript:** none

## Keyboard

- Delegate to Drupal Contact module form a11y

## Testing checklist

- [ ] Keyboard-only walkthrough (no mouse)
- [ ] VoiceOver (macOS) or NVDA (Windows) announces name, role, and state
- [ ] Focus order is logical; focus is not lost after dynamic updates
- [ ] `prefers-reduced-motion: reduce` does not break required functionality
- [ ] High-contrast / forced-colors: focus indicators remain visible
