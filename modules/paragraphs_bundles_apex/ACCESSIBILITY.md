# Paragraphs Bundles APEX — accessibility (WCAG / ARIA)

> **Private class:** none — this module ships no markup of its own
> **Status:** Phase B scoped (config only)
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)

## Widget pattern

- **Pattern:** static
- **Interactive:** no

This module adds three media view modes and nothing else. It renders no Twig,
no CSS and no JavaScript. The accessible surface is whatever the
`apex_responsive_image` field formatter emits, which is a single `img`
element — so the a11y properties below are inherited, not introduced.

## ARIA roles and attributes

None required, and none added. Native `img` alt text carries the meaning.

## Implementation files

- **Twig:** none
- **CSS:** none
- **JavaScript:** none
- **Config:** `config/optional/core.entity_view_display.media.pb_media_image.pb_apex_*.yml`

## What this module can affect

- **Alt text is preserved.** The formatter renders the media image field's own
  `alt` value; selecting an APEX view mode does not touch it. An image with no
  alt text was inaccessible before the switch and still is — this module
  neither fixes nor worsens it.
- **The caption field stays visible.** Each shipped display keeps
  `pb_image_caption` in the content region, matching PB's own displays. A
  display that dropped it would silently remove a visible text alternative.
- **The field label is `visually_hidden`, not `hidden`.** Screen readers still
  reach it; sighted users do not see a redundant label above the picture.

## ⚠ One real accessibility consideration before switching a bundle

APEX's modes crop to a **fixed aspect ratio** (16:9, 4:3, 3:2). PB's own wide
styles vary per breakpoint, from 1.50 to 3.37. Switching changes the crop, so
an image whose meaning depends on its edges — text in the picture, a face near
the border, a diagram — can lose that content at some breakpoints. Where the
image carries information, check the crop at every breakpoint after switching,
or keep the PB view mode.

## Keyboard

- Nothing focusable is added. The image is not a link in any shipped display.

## Screen reader

- The image is announced from its `alt` value, unchanged by this module.
- The caption, when set, is announced after the image.

## Testing checklist

- [ ] Alt text survives the view-mode switch and is announced
- [ ] Caption still renders and is announced after the image
- [ ] Crop at every breakpoint still shows the meaningful part of the image
- [ ] No focus stop is added or removed by switching view modes
- [ ] Forced-colors / high-contrast: the image still renders (no CSS involved)
