# 3D Flip Box bundle — accessibility (WCAG / ARIA)

> **Private class:** `pb-3d-flip-box`  
> **Status:** implemented (maintainer browser QA pending)
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)

## Widget pattern

- **Pattern:** disclosure (button toggle, plus hover and `:focus-within`)
- **Interactive:** yes

The box presents one of two faces. A flip control (`.pb-3d-flip-box__toggle`)
sits in the top-right corner of the scene and stays visible, so the box shows
that it turns. Hovering the box, and moving focus into the presented face,
still flip it as before.

`backface-visibility` is a **visual** property and does not touch the
accessibility tree, so the script applies `inert` and `aria-hidden` to
whichever face is turned away. Without that, both faces are exposed at once
and links on the hidden face stay in the tab order while invisible.

## Implementation files

- **Twig:** `templates/paragraph--3d-flip-box-bundle.html.twig`
- **CSS:** `css/paragraph-bundle-3d-flip-box.css`
- **JavaScript:** `js/paragraph-bundle-3d-flip-box.js`

## Keyboard

- Tab to the flip control; Enter or Space turns the box. It is a native
  `<button>`, so both keys work without a key handler.
- Only the presented face is reachable. Tabbing past the control moves into
  that face's links and buttons; the face turned away is `inert`.
- The control's state is sticky — an explicit flip is not undone by the
  pointer leaving the box.

## Screen reader

- The control is named `Flip <box title>`, or `Flip the box` when the
  paragraph has no title. The icon is `aria-hidden`.
- `aria-expanded` reports whether the back face is presented, and
  `aria-controls` points at the faces wrapper.
- The face turned away carries `aria-hidden="true"`, so only the presented
  face is announced.

## Without JavaScript

The control renders `hidden` and the script reveals it, so a page without the
script never shows a control that cannot work. The box then behaves exactly as
it did before this change: hover and `:focus-within` reveal the back face, and
both faces stay in the accessibility tree.

## Testing checklist

- [ ] Keyboard-only walkthrough (no mouse)
- [ ] VoiceOver (macOS) or NVDA (Windows) announces name, role, and state
- [ ] Focus order is logical; focus is not lost after dynamic updates
- [ ] `prefers-reduced-motion: reduce` does not break required functionality
- [ ] High-contrast / forced-colors: focus indicators remain visible
