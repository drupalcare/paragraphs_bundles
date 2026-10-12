# Modal bundle: accessibility (WCAG / ARIA)

> **Private class:** `pb-modal`  
> **Status:** implemented  
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)

## Widget pattern

- **Pattern:** dialog
- **Interactive:** yes

Follow [WAI-ARIA APG Modal
Dialog](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/). Require
`role="dialog"`, `aria-modal="true"` when open, valid name (`aria-labelledby` or
`aria-label`), optional `aria-describedby`, focus trap, Escape, return focus,
`inert` on background, and `Drupal.announce()` on open/close.

## ARIA roles and attributes

- `dialog`
- `button`

See the parent playbook for required attributes per pattern.

## Implementation files

- **Twig:** `templates/paragraph--modal-bundle.html.twig`
- **JavaScript:** `js/paragraph-bundle-modal.js`

## Keyboard

- Escape closes
- Tab trap
- Backdrop click closes
- Return focus to trigger

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
- [ ] `aria-modal="true"` when open; background inert
- [ ] Escape closes; focus returns to trigger
- [ ] Tab / Shift+Tab cycle inside dialog
- [ ] Backdrop click closes without activating content behind
