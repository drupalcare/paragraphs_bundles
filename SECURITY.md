# Security Policy

## Reporting a vulnerability

**Please do not report security vulnerabilities through public GitHub issues,
the drupal.org public issue queue, or any other public channel.**

Paragraphs Bundles follows the [Drupal Security Team][dst] process. Report a
suspected vulnerability privately using the **"Report a security issue"** link
on the project's drupal.org page, or by emailing the Drupal Security Team at
`security@drupal.org`. See the team's
[reporting guidelines](https://www.drupal.org/security-team/report-issue).

Please include:

- the module version and Drupal core version,
- a description of the issue and its impact,
- steps to reproduce (a proof of concept if possible).

You will receive an acknowledgement, and the issue will be handled under the
Drupal Security Team's coordinated-disclosure process.

## Supported versions

Security fixes are provided for the latest stable **2.0.x** release. Older or
development releases are not supported — upgrade to the latest stable release
before reporting.

## Content-Security-Policy

A strict CSP is only worth having if inline code is forbidden, so this section
states exactly what Paragraphs Bundles puts on a page. The numbers are checked
by `PbInlineAssetSurfaceTest`, not maintained by hand — if they drift, the test
fails.

**No inline JavaScript, in any form.** No `<script>` blocks, no `on*` event
handler attributes, no `eval()` or `new Function()`. Nothing here requires
`script-src 'unsafe-inline'`.

`<script src="...">` is not inline code — it is a source URL governed by
`script-src`, and this project emits none of those either. JavaScript that
assigns `element.style` at runtime is a CSSOM write, which CSP does not police.

**Inline CSS: a small, known set.**

| What | Where | Directive it needs |
| --- | --- | --- |
| 6 `style="..."` attributes, in 4 templates | per-paragraph values that differ per entity | `style-src-attr 'unsafe-inline'` |
| 1 `<style>` element, as a fallback only | `PbPreprocessHook`, when the generated CSS file is missing | `style-src-elem` — a nonce works here |

Everything else is delivered through the Libraries API, including the
per-paragraph custom properties, which are written to a real CSS file and
attached as a library. The [Content-Security-Policy module][csp] reads the
Libraries API, so those are allowed automatically with no configuration.

**A note on nonces.** A nonce can authorise a `<script>` or `<style>`
*element*. It cannot authorise a `style="..."` *attribute* — that needs
`style-src-attr`, a hash, or removing the attribute. This matters when
planning work: nonces do not address the six attributes above.

**Direction of travel.** Those six are the remainder of a larger set; in 1.0.x
the per-paragraph custom properties were injected as `style="--pb-bg:…"`
attributes throughout. They now go to the generated CSS file, and the intent is
for the rest to follow, which removes them from the policy surface entirely.

[csp]: https://www.drupal.org/project/csp

[dst]: https://www.drupal.org/drupal-security-team
