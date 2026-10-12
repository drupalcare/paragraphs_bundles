# Views bundle: accessibility (WCAG / ARIA)

> **Track A:** no bundle-private L0 class. It uses parent `paragraph--default`.  
> **Status:** implemented (maintainer browser QA pending)  
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)

## Widget pattern

- **Pattern:** static
- **Interactive:** no (delegates to Views output)

Semantic HTML, heading order, sufficient color contrast, and keyboard-accessible
links/controls. No custom widget roles unless interaction is added.

## Implementation files

- **Twig:** parent `../../templates/paragraph--default.html.twig`
- **JavaScript:** none

## Keyboard

- Delegate to Views output and pagers

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
