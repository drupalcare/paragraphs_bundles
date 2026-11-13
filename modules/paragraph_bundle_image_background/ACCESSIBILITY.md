# Image Background bundle: accessibility (WCAG / ARIA)

> **Private class:** `pb-image-background`  
> **Status:** pending  
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)

## Widget pattern

- **Pattern:** static
- **Interactive:** no

Semantic HTML, heading order, sufficient color contrast, and keyboard-accessible
links/controls in nested foreground content. JS only swaps decorative background
images at a breakpoint. No custom widget roles.

## ARIA roles and attributes

- When the background image has alt text, an empty, visually hidden element
  with `role="img"` and that alt as its `aria-label` exposes it as an image.
  An empty alt means decorative: nothing is exposed.

See the parent playbook for required attributes per pattern.

## Implementation files

- **Twig:** `templates/paragraph--image-background-bundle.html.twig`
- **JavaScript:** `js/paragraph-bundle-image-background.js` (responsive bg swap
  only)

## Keyboard

- Foreground content must remain keyboard reachable

## Screen reader

- Leave the image's alt empty when the background is decorative; fill it when
  the image carries meaning, and screen readers announce it as an image.
- Nested content links and controls keep accessible names from markup.

## Testing checklist

- [ ] Keyboard-only walkthrough (no mouse)
- [ ] VoiceOver (macOS) or NVDA (Windows) announces nested content correctly
- [ ] Focus order is logical through foreground content
- [ ] `prefers-reduced-motion: reduce` does not break layout
- [ ] High-contrast / forced-colors: focus indicators remain visible on links
- [ ] Ajax refresh / detach does not leave duplicate resize handlers
