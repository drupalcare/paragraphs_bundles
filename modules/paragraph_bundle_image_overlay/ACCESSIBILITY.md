# Image Overlay bundle: accessibility (WCAG / ARIA)

> **Private class:** `pb-image-overlay`  
> **Status:** Phase B scoped (static markup + CSS transitions)  
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)

## Widget pattern

- **Pattern:** static
- **Interactive:** optional image link only

Semantic HTML, heading order, sufficient color contrast, and keyboard-accessible
links. Overlay reveal uses CSS `:hover` and `:focus-within` (no custom widget
roles).

## ARIA roles and attributes

None required. Twig does not render `<figure>` semantics.

## Implementation files

- **Twig:** `templates/paragraph--image-overlay-bundle.html.twig`
- **CSS:** `css/paragraph-bundle-image-overlay.css`
- **JavaScript:** none

## Keyboard

- Tab to overlay link when configured; `:focus-within` reveals overlay like
  hover
- `:focus-visible` outline on `.pb-ui-link` (forced-colors uses `CanvasText`)
- Overlay text reading order: image, then overlay container/title

## Screen reader

- Image alt from media field
- Link `title` when link title field is set
- Title field via `pb-content-title.html.twig` (semantic heading)

## Testing checklist

- [ ] Keyboard-only: each overlay mode (fade, zoom, title, slide *) reveals on
  focus
- [ ] Mouse hover still works for all modes
- [ ] VoiceOver / NVDA announces link purpose and heading
- [ ] `prefers-reduced-motion: reduce` disables transitions
- [ ] Forced-colors: focus outline remains visible
- [ ] Print: overlay content visible (`opacity: 1`, `transform: none`)
