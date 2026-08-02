# Alert bundle — accessibility (WCAG / ARIA)

> **Private class:** `pb-alert`  
> **Status:** implemented  
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)
> **Project authority:**
[`docs/requirements/accessibility.md`](../../../../docs/requirements/accessibility.md)

## Widget pattern

- **Pattern:** alert
- **Interactive:** yes

Use `role="alert"` or `role="status"` for live messages. Dismiss control needs
an accessible name. Do not rely on color alone for severity.

## ARIA roles and attributes

- `alert`
- `button`

See the parent playbook for required attributes per pattern.

## Implementation files

- **Twig:** `templates/paragraph--alert-bundle.html.twig`
- **JavaScript:** `js/paragraph-bundle-alert.js`

## Keyboard

- Tab to dismiss control
- Enter/Space dismisses

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
