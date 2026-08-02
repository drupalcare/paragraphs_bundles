# Block Content bundle — accessibility (WCAG / ARIA)

> **Track A:** no bundle-private L0 class — uses parent `paragraph--default`.  
> **Status:** implemented (maintainer browser QA pending)  
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)

## Widget pattern

- **Pattern:** static
- **Interactive:** no (delegates to embedded block content)

## Implementation files

- **Twig:** parent `../../templates/paragraph--default.html.twig`
- **JavaScript:** none

## Keyboard

- Delegate to embedded block content

## Testing checklist

- [ ] Keyboard-only walkthrough (no mouse)
- [ ] VoiceOver (macOS) or NVDA (Windows) announces name, role, and state
- [ ] Focus order is logical; focus is not lost after dynamic updates
- [ ] `prefers-reduced-motion: reduce` does not break required functionality
- [ ] High-contrast / forced-colors: focus indicators remain visible
