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

- `button`, inside a heading (`.pb-accordion__heading`) one level below the
  accordion title tag (h1 gives h2 ... h5 and h6 give h6); h3 when no title is
  printed or the title tag is "div". The heading is reset in CSS so it adds no
  margin, font or color of its own (WCAG 1.3.1, APG accordion pattern)
- `region` on each pane, named by its button, only when the accordion has six
  sections or fewer (more would flood the landmark list)
- "Toggle all sections" keeps one label; its `aria-expanded` is `true` only while
  every section is open, and it updates when one section is opened or closed

See the parent playbook for required attributes per pattern.

## Implementation files

- **Twig:** `templates/paragraph--accordion-bundle.html.twig`
- **JavaScript:** `js/paragraph-bundle-accordion.js`

## Keyboard

- Enter/Space toggles sections
- Tab through headers
- Opening a section from a link scrolls to it; with
  `prefers-reduced-motion: reduce` the scroll jumps instead of gliding

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
