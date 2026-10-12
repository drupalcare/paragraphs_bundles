# Accordion bundle: accessibility (WCAG / ARIA)

> **Private class:** `pb-accordion`  
> **Status:** implemented  
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)

## Widget pattern

- **Pattern:** disclosure
- **Interactive:** yes

Follow [WAI-ARIA APG
Disclosure](https://www.w3.org/WAI/ARIA/apg/patterns/disclosure/). Trigger is a
`<button>` with `aria-expanded` and `aria-controls` pointing at the panel.

## ARIA roles and attributes

- `button`
- `region`

See the parent playbook for required attributes per pattern.

## Implementation files

- **Twig:** `templates/paragraph--accordion-bundle.html.twig`
- **JavaScript:** `js/paragraph-bundle-accordion.js`

## Keyboard

- Enter/Space toggles sections
- Tab through headers

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
