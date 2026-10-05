# Content-Security-Policy — findings, and what to do

Raised by
[#3620924](https://www.drupal.org/project/paragraphs_bundles/issues/3620924),
which asked for "nonce generation for your elements, if csp is active in the
system", on the premise that inline **scripts** in the paragraphs and/or theme
break CSP.

Everything below was measured on a purpose-built rig, not reasoned about. Where
a number appears, the method that produced it is stated so it can be re-run.

**Why this is worth doing properly.** The
[Content-Security-Policy module](https://www.drupal.org/project/csp) is on
~24,000 sites. "This module forces `unsafe-inline`" is the kind of line that
gets a project struck off an approved list during a security review, and a
strict CSP is increasingly a procurement requirement in government, finance and
healthcare. Being able to answer this question with numbers — and to keep
answering it as the code changes — is what makes the work travel.

---

## The rig

a test site rebuilt from scratch for this:

| | |
|---|---|
| Drupal | 11.4.5, standard profile |
| Theme | Solo `1.0.x-dev`, default |
| Module | Paragraphs Bundles `1.0.x-dev` (**V1**), all 28 modules enabled |
| CSP | `csp` 2.2.4, **report-only**, `default-src/script-src/style-src/img-src/font-src` all `'self'`, no `unsafe-inline` |
| Content | one node per bundle (`/node/1`–`/node/39`) plus `/node/40` rendering **all 39 bundles at once** |

Violations were captured with the DOM `securitypolicyviolation` event, not the
console. That distinction matters — see "How to measure this" at the end.

---

## Finding 1 — there is no inline JavaScript. The premise does not hold.

Static scan of every template, PHP and JS file:

| | inline `<script>` | `on*=` handlers | `eval()` / `new Function()` |
|---|---|---|---|
| PB V1 | **0** | **0** | **0** |
| PB V2 | **0** | **0** | **0** |
| Solo | **0** | **0** | **0** |

The rendered page *does* contain 9 inline `<script>` tags, which is almost
certainly what the reporter saw. Every one is a Drupal core data block —
one `application/json` (drupal-settings-json) and eight
`application/vnd.drupal-ajax`. They are not executable, and they do not
violate the policy. Verified by appending each type and watching for a
violation:

| appended element | violation |
|---|---|
| `<script type="application/json">` | **none** |
| `<script type="application/vnd.drupal-ajax">` | **none** |
| `<script>` executable — positive control | `script-src-elem` ✓ |

Solo emits one `<script>` tag, and it carries `src` (the generated
`solo-js-dynamic.js`, same-origin, deferred). A `src` reference is a source
URL under `script-src`, not inline code; `'self'` covers it.

**Consequence: nonce generation for scripts would fix nothing, because there
is nothing for a nonce to authorise.**

---

## Finding 2 — the surface is inline CSS, and it is not mostly ours

On `/node/40`, with all 39 bundles rendered:

| source | `style="…"` attributes | distinct values |
|---|---|---|
| **Solo** | 17 | 17 |
| **PB V1** (12 bundles) | 15 | 14 |
| total | **32** | |

Plus two inline `<style>` **elements**, neither of them PB's:

- **Drupal core's htmx** — `.htmx-indicator{opacity:0;visibility:hidden}`,
  injected by `core/assets/vendor/htmx/htmx.js`.
- **Solo's logo gradient** — `.solo-logo-outer-circle { fill: url(#…) }`.

Source-level counts, for context (excluding Twig comments and `data-*`
attributes that merely end in `style`):

| | inline style attributes in source |
|---|---|
| PB V1 | 52 across 32 templates |
| Solo | 23 across 22 templates |
| **PB V2** | **6 across 4 templates** |

PB V2 is already most of the way there: the per-paragraph custom properties
that V1 injects as `style="--pb-bg:…"` are written to a generated CSS file and
attached through the Libraries API.

---

## Finding 3 — what the CSP module actually offers

Read from the module's code, which is authoritative over any summary. There
are four integration points.

**1. `hook_csp_policy_alter()` — themes only.** From `csp.api.php`:

```php
function hook_csp_policy_alter(Csp $policy, Response $response): void {
  $policy->appendDirective('img-src', 'https://example.com');
}
```

The docblock is explicit: *"This hook is only invoked for themes, modules
should add an event subscriber listening to the CspEvents::POLICY_ALTER
event."* **This is Solo's route.**

**2. `CspEvents::POLICY_ALTER` event subscriber — modules.** PB's route, if it
ever needs to alter the policy globally.

**3. `#attached['csp']` — declare extra sources per render element.** The
cleanest option, and per-element rather than global:

```php
'#attached' => [
  'csp' => [
    'img-src'  => ['https://img.example.com'],
    'font-src' => ['https://fonts.example.com'],
  ],
],
```

**4. `#attached['csp_nonce']` and `#attached['csp_hash']`.** The nonce pattern
is documented on `NonceBuilder`:

```php
$element['#attached'] = [
  'placeholders' => [
    \Drupal::service('csp.nonce_builder')->getPlaceholderKey() => [
      '#lazy_builder' => ['csp.nonce_builder:renderNonce', []],
    ],
  ],
  'csp_nonce' => [
    'script' => [Csp::POLICY_UNSAFE_INLINE],
  ],
];
```

Note the lazy-builder placeholder: a nonce must be unique per request, so it
cannot be baked into cached markup. Any implementation has to go through this
mechanism rather than printing a value into a template.

---

## Finding 4 — a nonce cannot authorise a style attribute. The module enforces it.

`RenderElementAttachedCspSubscriber::applyNoncesAndHashes()`:

```php
assert(
  preg_match('<^(script|style)(?:-src(?:-elem)?)?$>', $directiveType),
  "Nonces can only be added to script or style element directives"
);
```

The pattern admits `-elem` and never `-attr`. This is not a limitation of the
module — it is the CSP specification, which the module is faithfully
enforcing. **So the fix the issue asks for cannot address a single one of the
52 style attributes in V1, or the 6 in V2.**

Where a nonce *is* the right tool:

- PB V2's single inline `<style>` fallback in `PbPreprocessHook`.
- Solo's CSS-injector `<style>` block, and its logo-gradient `<style>`.
- PB V1: nowhere. It emits no inline `<style>` element at all.

---

## Finding 5 — hashes *can* cover attributes, but not for our values

`PolicyHelper::appendHash()` accepts `attr`, and adds `'unsafe-hashes'`
automatically, because hashes for attributes need it under CSP Level 3:

```php
if ($type === 'attr') {
  $value = [$value, Csp::POLICY_UNSAFE_HASHES];
}
```

So the module does offer a real answer for style attributes — it is a hash,
not a nonce, and it costs `'unsafe-hashes'`.

**It does not fit our values.** Every distinct attribute value needs its own
hash in the response header, and on `/node/40` PB produced **14 distinct
values from 15 instances** — because they carry per-paragraph content:

```
height: 350px;
--section-height: 400px;
--pb-bg:rgba(0,0,0, 0.80); --pb-tx:#FFFFFF;
grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:20px;
```

Distinct values scale with the amount of content on the page, so the header
would grow with every paragraph an editor adds, and it would still carry
`'unsafe-hashes'`. That is a worse outcome than the problem.

Solo's 17 are the opposite shape — 17 distinct values that never vary with
content, differing only in a custom-property name. Those are genuinely
hashable, and better still, removable.

---

## Finding 6 — the first real violation anyone looked at was Drupal core's

A violation reported from the running rig:

```
Applying inline style violates the following Content Security Policy directive
'style-src 'self' 'report-sample' …'. Either the 'unsafe-inline' keyword, a hash
('sha256-faU7yAF8NxuMTNEwVmBz+VcYeIoBQ2EMHW3WaVxCvnk='), or a nonce is required.
```

The hash in a violation report identifies the exact content, so it can be
matched rather than guessed at. Hashing every inline style in the served HTML
of all 41 pages produced **no match** — meaning the style is not in any markup
this project or the theme emits, and is inserted by JavaScript after load.

Hashing the live DOM's runtime content found it:

```
MATCH: htmx <style> element
.htmx-indicator{opacity:0;visibility: hidden} .htmx-request .htmx-indicator, …
```

That is **Drupal core**. `core/assets/vendor/htmx/htmx.js` injects it in
`insertIndicatorStyles()`, and core's `navigation` module pulls `core/htmx` in
on admin pages — which is why it appears when logged in and never on an
anonymous node page.

htmx itself provides the fix, and core does not use it:

```js
const nonceAttribute = htmx.config.inlineStyleNonce ? ` nonce="${htmx.config.inlineStyleNonce}"` : ''
```

`htmx.config.inlineStyleNonce` and `htmx.config.includeIndicatorStyles` are
never set anywhere in core outside the vendored library. Wiring the first to
the CSP module's nonce, or the second to `false`, would remove this violation
for every Drupal 11.4 site running a strict policy. That is a core issue, not
ours.

**Why this matters for the issue.** The first violation examined on a site
running this module was not caused by this module, or by the theme. It is the
clearest possible illustration that "the browser console shows a load of
blocked elements" is not evidence about any one project, and that reports need
to be attributed before they are acted on.

**How to attribute one yourself:** take the `sha256-…` from the report and
match it against candidates. See "How to measure this again" below.

---

## What to do

Ranked. The theme of it: **generated CSS files beat hashes, hashes beat
nonces, and nonces only ever applied to a small part of this.**

### Paragraphs Bundles 2.0.x — the real work

1. **Move the remaining 6 style attributes into the generated per-paragraph
   CSS file**, the mechanism `PbCssFileManager` already provides for custom
   properties. This is the only change that takes the policy requirement to
   zero: no attributes, no hashes, no `'unsafe-hashes'`, and the Libraries API
   means the CSP module allows the file automatically with no configuration.
   Affected: `paragraph--3d-flip-box-bundle`, `paragraph--image-overlay-bundle`,
   `paragraph--parallax-section-bundle` (×3), `pb-content-title`.
2. **Add a nonce to the inline `<style>` fallback** in `PbPreprocessHook`,
   using the placeholder pattern above, and only when `csp` is installed. This
   is the one place the issue's suggestion applies exactly. Small, and it
   closes the last `style-src-elem` gap.
3. **Keep `PbInlineAssetSurfaceTest` honest.** It already fails the build on a
   new inline script or a new style attribute. As items 1 and 2 land, the
   allowlist shrinks; when it reaches zero, tighten the test to forbid style
   attributes outright.
4. **Then say so in `SECURITY.md`** — already stated there, update the numbers.

### Paragraphs Bundles 1.0.x — documentation only

Do **not** backport the CSS-file architecture. It is a large change to
delivery on a branch running production sites, and it buys those sites
nothing they cannot get by upgrading. The README now states the surface and
points at 2.0.x. That is the right level of investment for a branch being
replaced.

### Solo — small, and two of them are worth doing anyway

1. ~~**Fix `fill:--solo-xx` → `fill:var(--solo-xx)`.**~~ **DONE**, Solo
   `1.0.x` commit 6827481. The browser was **dropping the declaration as
   invalid**, so all 17 social icons rendered grey (`rgb(147,162,185)`) instead
   of their brand colours, even though `--solo-fb: #1877f2` was correctly
   defined throughout. Verified after the fix: 17 of 17 parse, computed
   `rgb(24,119,242)` Facebook, `rgb(29,161,242)` Twitter, `rgb(10,102,194)`
   LinkedIn. Already tracked in Solo's `docs/V2-ANALYSIS.md:643` as *[LOW]
   Invalid CSS in 19 SVG partials* — the measurement showed it was visible
   rather than cosmetic.

   Note this fixed the COLOUR, not the CSP surface: they remain inline style
   attributes. Moving them to CSS classes would remove 17 of Solo's 23
   attributes as well, and is the larger change still open.
2. **Nonce the CSS-injector `<style>` block and the logo-gradient block**, via
   the placeholder pattern, when `csp` is installed.
3. **Move the Google Font `<link>` out of `html_head` and into a library**
   (`type: external`). Assets attached through `html_head` are invisible to
   the CSP module's Libraries API scanning, so they must be allowed by hand;
   a library is discovered automatically. Solo is a theme, so it uses
   `hook_csp_policy_alter()` if it ever needs to alter the policy directly.

### What not to do

- **Do not implement nonces for style attributes.** It is impossible, and the
  module asserts against it.
- **Do not hash the per-paragraph style attributes.** The header grows with
  content and still needs `'unsafe-hashes'`.
- 🚨 **Write NOTHING csp-specific into this project.** The old rule here said an
  integration "should degrade to nothing when the module is absent". That is
  FALSE and it took alaahaddad.com down on 2026-09-23 — 500 on every page,
  14 occurrences, from one unconditional `#attached['csp']` in
  `paragraph_bundle_icon`. Core's `HtmlResponseAttachmentsProcessor` throws
  `LogicException` for any `#attached` key outside its allow-list, and `csp` is
  only tolerated because the csp module decorates that processor to strip it.
  No csp module, no stripping, fatal page.

  Maintainer, the same day: *"I dont want anything to mention or wait on csp, it
  work with it if it is there, if not I don't care."* So the rule is not "guard
  it" — it is **do not write it at all**. A library's external URL is picked up
  by csp automatically when that module is present, which is all this project
  needs. Anything a strict site must allow by hand belongs in that module's
  README, not in code.

---

## Incidental bugs this turned up

- **Solo:** `fill:--solo-xx` was invalid CSS, dropped by the browser; 17 icons
  affected on one page. **Fixed** in Solo `1.0.x` (6827481).
- **PB V1:** one bundle emits an **empty** `style=""` attribute.
- **PB V1:** `--pb-bg:rgba(0,0,0, 100)` looks wrong but is not. The template
  leaves 100 alone and converts every other value with
  `'0.' ~ "%02d"|format(x)`, so 80 becomes `0.80` and 8 becomes `0.08`. An
  alpha of 100 is clamped by the browser to fully opaque, which is the intended
  result. Ugly, correct, leave it.
- **PB V1:** a display field's raw text is interpolated straight into a style
  attribute (`perspective:<value>px`), so whatever is typed reaches CSS. Twig
  escaping prevents an attribute break-out, but the value is unvalidated.

---

## How to measure this again

**CSP violations do not reach the console-capture API** used by tooling here —
verified: violations fired via the DOM event while console capture recorded
only a deliberate probe. They *are* visible in real DevTools. To capture them
programmatically, listen for the event instead:

```js
window.__v = [];
document.addEventListener('securitypolicyviolation', e => window.__v.push({
  directive: e.effectiveDirective, blocked: e.blockedURI, line: e.lineNumber,
}));
```

Always run a positive control — append a known-violating element and confirm
it fires — before trusting an empty result. A listener that is not attached
and a page that is clean look identical from the outside.

To count what is actually on a rendered page:

```js
document.querySelectorAll('style').length                       // inline <style> elements
document.querySelectorAll('[style]').length                     // style attributes
[...document.querySelectorAll('script')].filter(s => !s.src)    // inline scripts (check .type)
new Set([...document.querySelectorAll('[style]')].map(e => e.getAttribute('style'))).size
```

That last one is the number that decides whether hashing is viable.

**Attributing a violation from its hash.** A report quotes the hash of the
exact content, so identification is deterministic. `crypto.subtle` needs a
secure context and will be unavailable over plain HTTP, so hash server-side:

```php
$h = fn(string $s): string => 'sha256-' . base64_encode(hash('sha256', $s, TRUE));
```

Hash every `style` attribute value and every `<style>` text content, first from
the **served HTML** (`file_get_contents` + `DOMXPath`), then — if nothing
matches — from the **live DOM**, which catches anything JavaScript injects
after load. A miss in the served HTML is itself the finding: it means the
content is not in any template.
