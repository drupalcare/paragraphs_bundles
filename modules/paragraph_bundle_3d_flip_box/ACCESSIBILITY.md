# 3D Flip Box bundle — accessibility (WCAG / ARIA)

> **Private class:** `pb-3d-flip-box`  
> **Status:** implemented (maintainer browser QA pending)
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)
> **Project authority:**
[`docs/requirements/accessibility.md`](../../../../docs/requirements/accessibility.md)

## Widget pattern

- **Pattern:** css-disclosure (hover + `:focus-within` on scene)
- **Interactive:** yes (CSS-only reveal; no bundle JS)

The flip scene (`.pb-3d-flip-box__scene`) is focusable via `tabindex="0"`.
Keyboard users Tab to the scene; `:focus-within` mirrors hover flip. Reduced
motion swaps to opacity fade instead of 3D rotation.

## Implementation files

- **Twig:** `templates/paragraph--3d-flip-box-bundle.html.twig`
- **CSS:** `css/paragraph-bundle-3d-flip-box.css`
- **JavaScript:** none

## Keyboard

- Tab to `.pb-3d-flip-box__scene` to reveal the back face (same as hover).
- Links and controls inside front/back faces remain in normal tab order.

## Screen reader

- No custom ARIA roles on the scene; back-face content stays in the DOM.
- Nested paragraph content keeps native heading and link semantics.

## Testing checklist

- [ ] Keyboard-only walkthrough (no mouse)
- [ ] VoiceOver (macOS) or NVDA (Windows) announces name, role, and state
- [ ] Focus order is logical; focus is not lost after dynamic updates
- [ ] `prefers-reduced-motion: reduce` does not break required functionality
- [ ] High-contrast / forced-colors: focus indicators remain visible
