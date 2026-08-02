# PB Block (`pb_block` custom block type) — accessibility

> **Utility class:** `pb-ui-block`  
> **Status:** implemented (maintainer browser QA pending)  
> **Parent playbook:**
[`PB-V2-ACCESSIBILITY-PLAYBOOK.md`](../../docs/PB-V2-ACCESSIBILITY-PLAYBOOK.md)
> **Project authority:**
[`docs/requirements/accessibility.md`](../../../../docs/requirements/accessibility.md)

## Widget pattern

- **Pattern:** static
- **Interactive:** delegates to nested paragraph bundles

Custom block content type with a single paragraphs field
(`pb_content_paragraph_in_block`).
No Display tab — styling comes from nested paragraph bundles.

## Implementation files

- **Twig (placed block):** `templates/block--pb-block.html.twig`
- **Twig (entity view):** `templates/block-content--pb-block.html.twig`
- **JavaScript:** none

## Keyboard

- Delegate to nested paragraph markup and controls

## Testing checklist

- [ ] PB Block placed in a region renders `<section class="pb-ui-block">`
- [ ] PB Block referenced from pb_content node renders same wrapper
- [ ] Nested paragraphs (hero, text, etc.) keyboard reachable
- [ ] No Display fields on block type form — only block title + paragraphs
