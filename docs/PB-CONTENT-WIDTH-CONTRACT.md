# pb_content — The Width Contract

> Three width sources compete on a `pb_content` node and two of them have to
> lose. This is how that is done today, what it costs, and the one place it
> currently leaks. Measured 2026-09-11 on Drupal 11.4.6 with Solo, against two
> generated pages carrying 93 paragraphs and 1,778 populated fields each.

## 1 · Why pb_content exists

A landing page is simple: add bundles and each one honours its own max-width.
`pb_content` is different **on purpose** — it exists to build a page that
ignores the theme's site width and lets the editor choose the width per node.
To do that it has to defeat two widths that would otherwise apply.

## 2 · The three layers

| | Layer | Verdict | How |
|---|---|---|---|
| 1 | **Site width** — the theme's global setting | must lose | `.page-node-type-pb-content … .pb-content__full { max-width: var(--pb-content-width) }`, written once each in the **neutral**, **solo** and **apex** stylesheets |
| 2 | **Bundle max-width** — the per-bundle field | must lose, with exceptions | `pb-u-mw--300 … --900`. Two halves: one rule **sets a token** (`--pb-width: 56.25rem`), a separate rule **consumes it** (`max-width: var(--pb-width)`). Only the consumer carries the pb_content negation. |
| 3 | **Node width** — chosen per node | **must win** | `pb_display_content_width` → body class `pb-nmw-*` (set by `PbcHtmlHook`) → token `--pb-content-width`. A nested `.pb-content__full` resets that token to `initial`, so the outermost one owns the page. |

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

## 3 · Full-bleed backgrounds

📐 **`pb-content__full` goes on an element *inside* the one carrying the
background, never on the background element itself.** That is what lets a
background stretch the full viewport width while the text on top of it stays
clamped to the node width. The same pattern applies to **hero** and to
**image_background**. Moving the class outward would clamp the background;
moving it inward would stop clamping the content.

## 4 · Measured behaviour

Two identical pages, every bundle, every field filled, the same bundle
max-width of **900px** on both. The only difference is that the `pb_content`
page also carries a node width.

| Page | Node width token | Still at 900px | Neutralised | Clamped to node width |
|---|---|---|---|---|
| Landing page | *(none)* | **79 / 79** ✅ | — | — |
| `pb_content` | `80rem` | **4 / 79** ❌ | 70 | 5 |

The landing page is the control and behaves exactly as it should — every bundle
honours its own 900px. On `pb_content`, 75 of 79 correctly give way.
**Four do not**, and two of those four are a real defect.

### Two are intended ✅

`.pb-modal__inner` and `.pb-3d-carousel__inner` keep their own width by design —
they are on the exclusion list, because a modal and a 3D carousel own their
internal sizing.

### Two are a leak — `.pb-parallax__text` ❌

Layer 2 works in two halves, and **parallax's two halves live in different
files**:

```css
/* custom.css — sets the token. No negation needed here. */
.paragraphs-bundles .pb-parallax__text.pb-u-mw--900 { --pb-width: 56.25rem; }

/* paragraph-bundle-parallax.css — CONSUMES it, and has no negation. */
.paragraphs-bundles.pb-parallax-section .pb-parallax__text { max-width: var(--pb-width); }
```

Every other consumer sits in `custom.css` and carries
`:not(.page-node-type-pb-content …)`. Parallax's consumer sits in its own module
stylesheet and was never given one, so the bundle width wins on a `pb_content`
node where it should not.

🚨 **One module out of forty forgot, and nothing caught it.**

## 5 · The structural problem

The leak above is a symptom, not the disease. Layer 2 is enforced by **repeating
a negation** — 19 selectors carry it — and layer 1's exclusion list is **copied
into four stylesheets**. Both mean the same thing: a correct rule depends on
every future author remembering it.

| What | Count | Failure mode |
|---|---|---|
| Templates emitting `pb-content__full` | 28 of 43 | The class alone cannot say which element owns the page width |
| Files repeating the 4-item exclusion list | **4** | A new special case means four edits; missing one fails silently |
| Selectors carrying the pb_content negation | **19** | A new width rule that forgets the negation silently wins. Parallax did. |

### The intended fix

Move the decision out of structural CSS selectors and into a class the template
emits. A template whose element must keep its own width adds `pb-width-own`
beside `pb-content__full`; **one** rule consumes it, replacing the four copies;
and a drift test fails when a template emits `pb-content__full` without being on
the known list.

The decision then lives where the markup is, where it is visible and testable —
and a module stylesheet cannot forget it, because it is no longer asked to
remember.

## 6 · Reproducing the measurement

⚠ Read the **computed** style, never the class list. A class says what was
asked for; only the computed value says what the browser did.

```js
// On a pb_content node, in the browser console:
const token = getComputedStyle(document.body).getPropertyValue('--pb-content-width').trim();
const leaks = [...document.querySelectorAll('[class*="pb-u-mw--"]')]
  .filter(e => /^\d+px$/.test(getComputedStyle(e).maxWidth))
  .map(e => ({
    bundle: (e.closest('[class*="paragraph--type--"]')?.className
              .match(/paragraph--type--([a-z0-9-]+)/) || [])[1],
    maxWidth: getComputedStyle(e).maxWidth,
  }));
console.log({ token, leaks });
// Expect: every entry's maxWidth equals the node width, or the element is a
// documented exception. Anything still at the BUNDLE width is a leak.
```

---

What may be nested inside what is a separate question:
[PB-NESTING-GUIDE.md](PB-NESTING-GUIDE.md).
