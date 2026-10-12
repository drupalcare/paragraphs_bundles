# Carousel bundle: accessibility (WCAG / ARIA)

> **Private class:** `pb-carousel`  
> **Status:** implemented  
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)

## Widget pattern

- **Pattern:** carousel
- **Interactive:** yes

Follow [WAI-ARIA APG
Carousel](https://www.w3.org/WAI/ARIA/apg/patterns/carousel/). Label the
carousel region, provide prev/next controls, pause autoplay, and use `inert` or
equivalent on off-screen slides where implemented.

## ARIA roles and attributes

- `region`
- `group`

See the parent playbook for required attributes per pattern.

## Implementation files

- **Twig:** `templates/paragraph--carousel-bundle.html.twig`
- **JavaScript:** `js/paragraph-bundle-carousel.js`

## Keyboard

- Arrow keys / prev-next buttons
- Tab through controls
- Pause when autoplay

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
- [ ] Autoplay can be paused
- [ ] Slide changes announced or reflected in visible label
- [ ] Off-screen slides not in tab order (`inert` or equivalent)
