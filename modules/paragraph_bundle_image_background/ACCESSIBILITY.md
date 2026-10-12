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

No custom ARIA roles are required for this bundle.

See the parent playbook for required attributes per pattern.

## Implementation files

- **Twig:** `templates/paragraph--image-background-bundle.html.twig`
- **JavaScript:** `js/paragraph-bundle-image-background.js` (responsive bg swap
  only)

## Keyboard

- Foreground content must remain keyboard reachable

## Screen reader

- Decorative background images use empty alt when configured in the media field.
- Nested content links and controls keep accessible names from markup.

## Testing checklist

- [ ] Keyboard-only walkthrough (no mouse)
- [ ] VoiceOver (macOS) or NVDA (Windows) announces nested content correctly
- [ ] Focus order is logical through foreground content
- [ ] `prefers-reduced-motion: reduce` does not break layout
- [ ] High-contrast / forced-colors: focus indicators remain visible on links
- [ ] Ajax refresh / detach does not leave duplicate resize handlers
