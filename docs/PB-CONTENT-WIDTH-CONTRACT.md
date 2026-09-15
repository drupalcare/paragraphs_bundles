# pb_content — The Width Contract

> Three width sources compete on a `pb_content` node and two of them have to
> lose. This is how that is arranged, which elements are exempt and why, and how
> it is kept from drifting.
>
> Measured 2026-09-12 on Drupal 11.4.6, against two generated pages carrying 92
> paragraphs and ~1,770 populated fields each, under **all three theme
> families**.

## 1 · Why pb_content exists

A landing page is simple: add bundles and each one honours its own max-width.
`pb_content` is different **on purpose** — it exists to build a page that
ignores the theme's site width and lets the editor choose the width per node.
To do that it has to defeat two widths that would otherwise apply.

## 2 · The three layers

| | Layer | Verdict | Enforced by |
|---|---|---|---|
| 1 | **Site width** — the theme's global setting | must lose | `.page-node-type-pb-content … .pb-content__full:not(.pb-width-own) { max-width: var(--pb-content-width) }`, once per theme variant |
| 2 | **Bundle max-width** — the per-bundle field | must lose, except for owners | **one** rule blanks the token: `[class*="pb-u-mw--"]:not(.pb-width-own) { --pb-width: none }` |
| 3 | **Node width** — chosen per node | **wins** | `pb_display_content_width` → body class `pb-nmw-*` (`PbcHtmlHook`) → `--pb-content-width` |

### Node width values

| Field value | Body class | `--pb-content-width` |
|---|---|---|
| `pb__nmw_800` | `pb-nmw-800` | `50rem` |
| `pb__nmw_1024` | `pb-nmw-1024` | `64rem` |
| `pb__nmw_1280` | `pb-nmw-1280` | `80rem` |
| `pb__nmw_1440` | `pb-nmw-1440` | `90rem` |
| `pb__nmw_1600` | `pb-nmw-1600` | `100rem` |
| `pb__nmw_1920` | `pb-nmw-1920` | `120rem` |
| `pb__nmw_2560` | `pb-nmw-2560` | `160rem` |
| `pb__nmw_100` | `pb-nmw-100` | `100%` |

## 3 · `pb-width-own` — the only exemption

An element carrying **`pb-width-own`** alongside `pb-content__full` keeps its
own width: the node width does not clamp it, and its bundle max-width is not
blanked.

**Exactly two templates emit it**, and each says why in a comment beside the
class:

| Element | Template | Why it may |
|---|---|---|
| `.pb-3d-carousel__inner` | `paragraph_bundle_3d_carousel` | The slide transforms and `perspective` are computed from **this box**. Clamping it skews the 3D geometry rather than merely narrowing it. |
| `.pb-modal__inner` | `paragraph_bundle_modal` | A modal is an **overlay**, lifted out of the content column, so the node's content width has no authority over it. |

⚠ **`pb-width-own` is a modifier, never a standalone class.** On an element
without `pb-content__full` it is inert, and the element silently takes the node
width — the opposite of what marking it was meant to achieve.

### Two of the four inherited exemptions were wrong

This contract replaced a four-item structural exclusion list. On review, **half
of it was incorrect and had been since it was written**:

| Old exclusion | Verdict |
|---|---|
| `.pb-slideshow__wrap-inner > .pb-content__full` | ❌ **wrong** — the slideshow rendered full-bleed on every pb_content node. Now follows the node width. |
| `.pb-carousel__viewport.pb-content__full` | ❌ **wrong** — same fault. Now follows the node width. |
| `.pb-3d-carousel__inner.pb-content__full` | ✅ correct |
| `.pb-modal__content .pb-content__full` | ✅ correct |

📐 **That is the argument for the class contract in one line.** As four
structural selectors duplicated across four stylesheets, nobody could see them
as a list to review, and two were quietly wrong for the life of the design. As
two template classes with the reason written next to each, they are reviewable.

## 4 · Full-bleed backgrounds

📐 **`pb-content__full` goes on an element *inside* the one carrying the
background, never on the background element itself.** That is what lets a
background stretch the full viewport width while the text on top of it stays
clamped. The same pattern applies to **hero** and **image_background**. Moving
the class outward would clamp the background; moving it inward would stop
clamping the content.

## 5 · Why layer 2 is one rule and not nineteen

A bundle's max-width works in two halves: one rule **sets** `--pb-width`,
another **consumes** it as `max-width: var(--pb-width)`. Previously every
consumer carried its own `:not(.page-node-type-pb-content .paragraphs-bundles)`
— **19 selectors, each a chance to forget.**

🚨 **`paragraph-bundle-parallax.css` forgot.** Its consumer lived in the module's
own stylesheet rather than `custom.css`, never received the negation, and
`.pb-parallax__text` kept the bundle width on pb_content nodes where the node
width should have won. One module out of forty, silent, found only by rendering
a real page and reading computed styles.

Blanking the **token** instead fixes that class of bug outright:

```css
.page-node-type-pb-content .paragraphs-bundles [class*="pb-u-mw--"]:not(.pb-width-own) {
  --pb-width: none;
}
```

Any consumer of `var(--pb-width)`, in any stylesheet, present or future,
resolves to `none` without knowing this rule exists. **Parallax was fixed
without its stylesheet being touched.**

## 6 · Measured behaviour

Two pages, every bundle, every field filled, bundle max-width **900** on both.
The only difference is that the pb_content page also carries a node width.

**Landing page** (no layer 3): **31 of 31** top-level bundles render at exactly
900px. Zero exceed, zero unclamped.

**pb_content**, node width **800**, measured under each theme family:

| Theme family | Stylesheet loaded | Neutralised | Clamped to node | Owners | Off-target |
|---|---|---|---|---|---|
| Solo | `content-solo.css` | 72 | 5 | 2 | **0** |
| Neutral | `content-neutral.css` | 72 | 5 | 2 | **0** |
| Apex | `content-apex.css` | 72 | 5 | 2 | **0** |

⚠ **Each variant only loads under its own theme, so each must be tested with
that theme active.** `PbcThemeFamilyResolver` picks one of the three; a
measurement taken under Solo says nothing about the Apex stylesheet. All three
were run with their theme as the site default.

The discriminating case: with the node at **800** and bundles at **900**, the
3D carousel renders **900** (its own) while the plain carousel, the slideshow
and every ordinary bundle render **800** (the node's).

## 7 · The drift test

`tests/src/Unit/PbWidthContractTest.php` fails when:

1. a template emits `pb-width-own` without being on the approved list;
2. an approved owner emits `pb-width-own` on an element that does not also
   carry `pb-content__full`;
3. any retired structural exclusion reappears in a stylesheet;
4. `all:` is declared after other declarations in a rule — see below.

All four were **red-proofed**: each was deliberately broken and observed to
fail, then restored. That exercise immediately earned itself — assertion 2
originally checked only that both class names appeared *somewhere in the file*,
and stayed green when the modal was broken. It now checks the class context
around each occurrence.

### Why `all:` is in a width test

Not strictly a width rule, but the same class of silent failure, and it has
bitten
twice. `all: unset` declared **last** in a block wipes every declaration above
it; only `!important` ones survive, which is what disguises it both times:

| Where | What it removed | Symptom |
|---|---|---|
| `paragraphs-bundles-base.css` | `box-sizing: border-box` on every button and link | accordion's `+` icon clipped in half |
| `paragraph-bundle-slideshow.css` | `position: absolute` (and six more) | arrows fell from mid-slide to the bottom |

**`all: unset` belongs first** — it strips the UA styling *before* our own
declarations, not after.

## 8 · Reproducing the measurement

⚠ Read the **computed** style, never the class list. A class says what was asked
for; only the computed value says what the browser did.

```js
// On a pb_content node, in the browser console:
const token = getComputedStyle(document.body).getPropertyValue('--pb-content-width').trim();
const leaks = [...document.querySelectorAll('[class*="pb-u-mw--"]')]
  .filter(e => /^\d+px$/.test(getComputedStyle(e).maxWidth))
  .filter(e => !e.classList.contains('pb-width-own'))
  .map(e => ({
    bundle: (e.closest('[class*="paragraph--type--"]')?.className
              .match(/paragraph--type--([a-z0-9-]+)/) || [])[1],
    maxWidth: getComputedStyle(e).maxWidth,
  }));
console.log({ token, leaks });   // leaks should be empty
```

⚠ **Automation caveat.** A headless or MCP-driven tab reports
`document.hidden: true`, which suspends `requestAnimationFrame` and can leave
`getComputedStyle` returning stale values for JS-driven state. Geometry
(`getBoundingClientRect`) stays reliable; paint state does not. Verify anything
that depends on a JS reveal in a real, focused browser tab.

---

What may be nested inside what is a separate question:
[PB-NESTING-GUIDE.md](PB-NESTING-GUIDE.md).
