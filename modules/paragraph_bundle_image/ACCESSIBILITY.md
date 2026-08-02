# Image bundle — accessibility (WCAG / ARIA)

> **Private class:** `pb-image`, `pb-image-wide`, `pb-image-narrow`  
> **Status:** Phase B scoped (static markup)  
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)

## Widget pattern

- **Pattern:** static
- **Interactive:** no (optional image link only)

Semantic HTML, heading order, sufficient color contrast, and keyboard-accessible
links. No custom widget roles.

## ARIA roles and attributes

None required. Use native `img` alt text and semantic headings for titles.

## Implementation files

- **Twig:** `templates/paragraph--image-*.html.twig`
- **CSS:** `../../css/paragraphs-bundles-custom.css` (scoped L0)
- **JavaScript:** none

## Keyboard

- Tab to image link when configured
- Alt text on images
- Caption association when media caption is shown

## Screen reader

- Image link uses `title` attribute when link title field is set
- Title field renders via `pb-content-title.html.twig` (semantic heading)

## Testing checklist

- [ ] Keyboard-only walkthrough (no mouse)
- [ ] VoiceOver (macOS) or NVDA (Windows) announces image alt and link purpose
- [ ] Linked image: entire tile is one focus stop with clear name
- [ ] Display colors on inner shell meet contrast when bg/text fields set
- [ ] High-contrast / forced-colors: focus indicators remain visible on links
