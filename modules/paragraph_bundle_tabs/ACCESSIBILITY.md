# Tabs bundle — accessibility (WCAG / ARIA)

> **Private class:** `pb-tabs`  
> **Status:** implemented  
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)
> **Project authority:**
[`docs/requirements/accessibility.md`](../../../../docs/requirements/accessibility.md)

## Widget pattern

- **Pattern:** tabs
- **Interactive:** yes

Follow [WAI-ARIA APG Tabs](https://www.w3.org/WAI/ARIA/apg/patterns/tabs/). Use
`role="tablist"`, `role="tab"`, `role="tabpanel"`, `aria-selected`,
`aria-controls`, and roving `tabindex` / arrow-key navigation.

## ARIA roles and attributes

- `tablist`
- `tab`
- `tabpanel`

See the parent playbook for required attributes per pattern.

## Implementation files

- **Twig:** `templates/paragraph--tabs-bundle.html.twig`
- **JavaScript:** `js/paragraph-bundle-tabs.js`

## Keyboard

- Arrow keys between tabs
- Tab into panel
- aria-selected

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
- [ ] `aria-selected` updates on tab change
- [ ] Arrow keys move between tabs
- [ ] Active panel is associated via `aria-labelledby` / `aria-controls`
