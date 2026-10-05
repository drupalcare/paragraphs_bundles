# 3D Carousel bundle — accessibility (WCAG / ARIA)

> **Private class:** `pb-3d-carousel`  
> **Status:** implemented (maintainer browser QA pending)  
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

- **Twig:** `templates/paragraph--3d-carousel-bundle.html.twig`
- **JavaScript:** `js/paragraph-bundle-3d-carousel.js`

## Two geometries

The editor picks **Carousel style** under Display → Additional:

- **Rotating ring** (default) — the drum. It stops at the first and last
  slide, and the arrow for the end it has reached is hidden
  (`pb-is-hidden`, `tabindex="-1"`).
- **Coverflow** — a fan of cards. It wraps, so both arrows always show.

Both share one script, so the ARIA, keyboard, autoplay and `inert` behaviour
below is identical for each. Only the geometry differs.

- **Ring:** only the active slide is exposed; the others are `inert` and
  `aria-hidden`. At either end focus moves to the arrow that still works.
- **Coverflow:** every card painted on screen stays exposed; only cards
  clipped out of the box are `inert`. Both arrows always stay in tab order.

Tested: arrow buttons, arrow keys, focus at the ends,
the slide counter, and `prefers-reduced-motion` (no animation runs). ⚠ Not
yet checked with a real screen reader.

## Keyboard

- Arrow keys for slide navigation
- Tab through controls

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
