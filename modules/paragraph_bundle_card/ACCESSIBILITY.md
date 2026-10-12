# Card bundle: accessibility (WCAG / ARIA)

> **Private class:** `pb-card`
> **Status:** pending
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)

## Widget pattern

- **Pattern:** static
- **Interactive:** yes

Semantic HTML, heading order, sufficient color contrast, and keyboard-accessible
links/controls. No custom widget roles unless interaction is added.

## Implementation files

- **Twig:** `templates/paragraph--card-*.html.twig`
- **JavaScript:** `js/paragraph-bundle-card.js`

## Keyboard

- Tab through links and controls in card sections

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
- [ ] Ajax refresh / detach does not leave duplicate handlers or traps
