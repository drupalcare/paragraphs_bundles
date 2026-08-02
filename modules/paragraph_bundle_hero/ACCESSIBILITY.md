# Hero bundle — accessibility (WCAG / ARIA)

> **Private class:** `pb-hero`  
> **Status:** pending  
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)

## Widget pattern

- **Pattern:** static
- **Interactive:** no

Semantic HTML, heading order, sufficient color contrast, and keyboard-accessible
links/controls. No custom widget roles unless interaction is added.

## ARIA roles and attributes

No custom ARIA roles are required for this static content bundle.

See the parent playbook for required attributes per pattern.

## Implementation files

- **Twig:** `templates/paragraph--hero-bundle.html.twig`
- **JavaScript:** none (markup/CSS only)

## Keyboard

- Tab through hero links and CTAs
- Heading hierarchy

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
