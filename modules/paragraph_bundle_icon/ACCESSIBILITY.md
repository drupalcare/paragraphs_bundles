# Icon bundle: accessibility (WCAG / ARIA)

> **Private class:** `pb-icon`  
> **Status:** Phase B scoped (font-load reveal JS)
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)

## Widget pattern

- **Pattern:** decorative (GMS) or informative (uploaded image icon)
- **Interactive:** optional link wrapping icon/title

Decorative GMS icons use opacity hide-until-font-load only (no `aria-hidden` on
the span). Uploaded image icons use media alt text. Optional link uses native
`<a>`.

## ARIA roles and attributes

None on the paragraph shell. Image icon alt comes from the media field.

## Implementation files

- **Twig:** `templates/paragraph--icon-bundle.html.twig`
- **CSS:** `css/paragraph-bundle-icon.css`
- **JavaScript:** `js/paragraph-bundle-icon.js` (font-load reveal)

## Keyboard

- Tab to link when `pb_content_link` is set
- Title renders as semantic heading via `pb-content-title.html.twig`

## Screen reader

- GMS icon name is visible text in the span (not icon-font aria hack)
- Uploaded image: alt from media
- Link `title` when title field is set

## Testing checklist

- [ ] GMS icon visible after fonts load; no FOIT flash on Ajax insert
- [ ] Uploaded image icon: alt text announced
- [ ] Linked icon: one focus stop, clear name
- [ ] `prefers-reduced-motion: reduce` disables inner hover transform
- [ ] Ajax unload: no duplicate font-ready handlers (`detach` +
  `AbortController`)
