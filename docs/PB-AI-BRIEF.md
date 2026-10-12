# Paragraphs Bundles: brief for `paragraphs_bundles_ai`

Written 2026-09-03, before any code, because nobody starts work unbriefed.
This document and the agent description below are the two things that must
exist in writing before the first file is created.

Read this with [PB-V2-BUNDLE-VOCABULARY.md](PB-V2-BUNDLE-VOCABULARY.md) and
[DEVELOPING.md](DEVELOPING.md).

---

## 0 · What this looks like for the person using it

`paragraphs_bundles` works exactly as it does today with none of this
installed. `paragraphs_bundles_ai` is an **optional** sub-module: enable it,
and only if the site also has `drupal/ai` configured with a provider, an
assistant appears on node add and edit forms as a small chat bar, hidden until
you click it, gone when you dismiss it.

**Turning it on**

1. `composer require drupal/ai` and enable `ai`, plus `ai_assistant_api`,
   **`ai_agents`** and `ai_chatbot` for the chat surface.
2. Configure an AI provider and give it an API key (see the `ai` module's own
   documentation). This module never asks for or stores a key.
3. Enable `paragraphs_bundles_ai`.
4. Create an **agent** with the three `pb_content:*` tools enabled, then an
   **assistant** whose *AI Agent* is that agent, with *Use function calling* on.
5. Choose that assistant at *Configuration › Content authoring › Paragraphs
   Bundles AI*.
6. Grant the permissions it adds to the roles that should have it.

**Step 4 was missing from the first version of this brief, and the omission
was not cosmetic.** Measured 2026-09-04: `ai_assistant_api` builds an
assistant's tool list from `actions_enabled`, which holds *AiAssistantAction*
plugins. The tools in this module are *FunctionCall* plugins, and
`AssistantMessageBuilder::getFunctionCalls()` never looks at those. An
assistant without an agent therefore reaches none of them. The chat bar
appears, converses, and cannot do anything the brief promises. The agent is the
only bridge, and a setup guide that omits it produces a feature that looks
installed and does nothing.

Miss any of those and nothing appears; nothing breaks either.

**Using it.** Open a node form and describe what you want:

> *"add a slideshow with three slides"*

The assistant will not guess at anything that matters. If the content type has
more than one paragraph field it asks which. If the field does not accept
slideshows it says so and lists what it does accept. If you have not said which
content type, it names the ones that have paragraph fields. It asks once, for
everything it is missing, rather than one question at a time.

When it has enough, it creates the sections and tells you what it did, and
what it left blank. On an existing page it writes **a new revision** and gives
you the revision id, so any change is one click away from being undone.

**What it will not do.** It never changes a settings form, never publishes
anything, and never replaces existing sections unless you say "replace". If a
setting blocks the job it names the setting and stops rather than working
around it.

---

## 1 · Scope

**Build** an optional submodule, `modules/paragraphs_bundles_ai/`, that lets an
editor create and fill paragraph sections by describing what they want, using
`drupal/ai`. Three tools and one surface, in this order:

| | Tool | Writes? |
|---|---|---|
| B3 | `resolve_target`: negotiate a target, or return the question that blocks it | never |
| B4 | `create_sections`: create the paragraphs, re-checking every precondition itself | yes |
| B6 | `draft_field_text`: return drafted text for a field | never |
| B7 | Chat surface on node add/edit forms | — |

**May create or edit:** files under `modules/paragraphs_bundles_ai/` and this
brief. Nothing else.

**Must NOT touch**, and this is not negotiable:

- Any other file in `paragraphs_bundles`: the parent module, the other 26
  bundle sub-modules, `phpstan.neon`, `phpcs.xml.dist`, `.gitlab-ci.yml`.
- Any other project. `utilikit`, `vvj_core` and the ten VVJ formats are
  separate drupal.org projects and **must keep working with no knowledge of
  this module and no shared code**. There is no shared "AI kit" module.
- `sites/default`, the other sites in the `demo` multisite, or any vhost.

**Test site: `sts.local` only.** Drupal 11.4.5, `demo` multisite, database
`local_sts`, and note the PHP differs from the Drupal 12 work:

```bash
cd /Users/alaahaddad/Sites/demo
export PATH=/opt/homebrew/opt/php@8.4/bin:$PATH   # 8.4, NOT the 8.5 d12 needs
./vendor/bin/drush --uri=sts.local <command>
```

The `d12` site is for Drupal 12 compatibility work and is **out of bounds for
this task**. No third site, for any reason.

**Never push to drupal.org.** Commits are for the owner to review; pushing is
theirs alone.

---

## 2 · Evidence standard

**Measure with the project's own config, never an ad-hoc one.** This is the
most expensive lesson of the session that produced this brief. Twice, an
ad-hoc analyzer config produced confident nonsense: 32 phantom Drupal 12
failures in this very module (the real number was 1) and 1200 phantom PHPCS
errors (the real number was 0). Run `phpstan.neon` and `phpcs.xml.dist` as they
are. If a measurement needs a different config, that fact is itself a finding
to report, not a license to substitute one.

**A clean local run is evidence about this machine, not about the gate.**
`phpstan.neon` in this repo says so: drupal.org CI passes `.` on the command
line, overriding `paths`, and runs PHPStan 2.x where `level: max` means 10. The
CI log is the verdict.

**Write "needs checking" rather than assert.** An unverified belief stated as
fact is worse than an admitted gap, because it stops anyone else looking.

**Mutation-probe anything claimed to be load-bearing.** Break it, watch the
test fail, restore it byte-identical, and say so. A test that passes both with
and without the code it covers is measuring nothing.

**Read the console on every browser check, and report it, including
"console clean".** Call the tool once before loading the page: capture starts
when the tool is first called, so a page loaded earlier reports an empty
console that has simply not been recorded. Prove capture is live before
trusting a filtered empty result.

**Verify with the project's own tests, not by reasoning about the code.** In
this codebase specifically: Drupal 12 requires `#[RunTestsInSeparateProcesses]`
on kernel test classes, and PHPUnit 12 ignores the `@dataProvider` docblock in
favor of the attribute. Both were invisible until the suites actually ran.

---

## 3 · Deliverable shape

A working submodule, committed but **not pushed**, with:

- **`paragraphs_bundles_ai.info.yml`** depending on `paragraphs_bundles` and
  `ai:ai`. Hard dependencies are correct here because the submodule is itself
  optional. That is the whole trick, and it is verified working in
  `apex_ai_bridge`.
- **Tools** declaring `module_dependencies` so they are not discovered without
  their parent.
- **Agent and assistant config** in `config/optional/` with
  `dependencies.enforced.module`, so they install only when `ai_agents` /
  `ai_assistant_api` are present and vanish with the submodule.
- **A chat surface** that checks `ai_chatbot` is installed before rendering.
- **Kernel tests** for every precondition in §5, each asserting the exact
  sentence a caller receives.
- **A `docs/` note** recording the pattern, because the three AI submodules
  across three projects cannot share code and will otherwise drift.

**Done means:** the module's own PHPUnit suite passes, `phpcs.xml.dist` is
clean, `phpstan.neon` at `level: max` reports nothing new, and the three tools
have been exercised on `sts.local` against a real provider.

---

## 4 · Refusal conditions: stop and ask, do not guess

- **The work needs a file outside `modules/paragraphs_bundles_ai/`.** Write the
  change into a handover note instead and ask.
- **A precondition cannot be determined from field config.** Ask rather than
  infer. Guessing which paragraph field an editor meant is the failure this
  design exists to prevent.
- **The design starts to want the AI to write configuration.** It must not, in
  any of the three projects. Report the blocking setting, name where it lives,
  stop.
- **A decision would change what the module advertises**: a
  `core_version_requirement`, a dependency, a public API. That is the owner's
  call, as narrowing `utilikit` to `^11.2 || ^12` was.
- **A test fails for a reason not understood.** A green suite reached by
  changing the test is worse than a red one.
- **The requested nesting is on the forbidden list.** Refuse and say which rule
  it breaks, rather than creating it and letting the page look wrong. See
  below. This is a refusal condition, not a preference.

### 4a · Nesting is a hard constraint, not a style choice

**[PB-NESTING-GUIDE.md](PB-NESTING-GUIDE.md) is the authority on what may
contain what**, and it ends with the same rules as JSON so this module can read
them instead of re-deriving them. Six rules, each traced to a measured trait of
the bundle rather than to taste:

1. **Leaves go anywhere.** Thirteen bundles have no child slot; they are always
   a safe choice and should be the default. The count is of bundles this module
   ships. A site's own paragraph types are outside this contract.
2. **Perspective never nests in perspective**: `parallax`, `3d_carousel`,
   `3d_flip_box` flatten each other.
3. **Nothing that autoplays goes somewhere hidden**: no carousel, slideshow or
   3D carousel inside a modal, a closed accordion pane or an inactive tab.
4. **Parallax needs the real viewport**: never inside a transformed ancestor.
5. **Backgrounds do not stack.**
6. **One container deep.**

⚠ **A slot accepting a bundle is not permission to put it there.** Eighteen
slots across twelve bundles accept *any* bundle, so field config will not stop
a nesting that renders wrong. The guide is what stops it, which is why refusing
belongs in the list above.

📐 Width behaves differently on `pb_content` than on a plain landing page, and
a generated page has to expect that: see
**[PB-CONTENT-WIDTH-CONTRACT.md](PB-CONTENT-WIDTH-CONTRACT.md)**.

⚠ **Do not set a bundle max-width expecting it to show on a `pb_content` node.**
The node's own width wins there, and the bundle value is deliberately blanked.
Only two elements are exempt: the 3D carousel's inner box and the modal, and
that exemption is a template decision, not something content can request.

---

## 5 · The preconditions: this list *is* the feature

`resolve_target` returns either a resolved target or **every** unmet
precondition at once, so the bot asks one combined question rather than
interrogating turn by turn:

1. A content type was named, and it exists: *"landing" is not a content type;
   did you mean Landing page?*
2. It has at least one paragraph reference field.
3. If it has more than one, which: listed by name.
4. **That field allows the requested bundle.** Paragraph fields carry
   allowed-bundle restrictions and this is the check people forget.
5. Cardinality permits the number of items asked for.
6. The bundle's module is installed.
7. The user may create, or edit, that content type.

`create_sections` **re-checks all seven itself.** It must never trust that
`resolve_target` was called, because the model may skip it. That redundancy is
the safety model, not belt-and-braces.

**Rules the tools enforce, not the prompt.** A system prompt saying "ask which
field if there is more than one" fails intermittently; a tool that cannot
proceed, and whose refusal names the missing information, forces the
conversation. It is also what makes the behavior testable. You cannot
unit-test "the bot felt smart", but you can test thirty refusals.

**Editing existing content always writes a new revision** and reports the
revision id. Append and replace are both explicit: refuse to replace existing
sections unless the user said replace, and never guess an insert position.

**Fill what the user supplied, draft what they asked for, leave the rest empty
— and say which.** "Three slides, headings left blank" is a better outcome than
silently inventing headings nobody asked for.

---

## 6 · How this will be verified, and it will be

1. **Kernel tests**, one per precondition, asserting the exact sentence.
2. **A mutation probe** on `create_sections`' independent re-validation: remove
   it, prove a test fails, restore byte-identical.
3. **`phpstan.neon` at level max** and `phpcs.xml.dist`, both as they ship.
4. **A live run on `sts.local`** against the configured OpenAI provider,
   exercising all three tools, with node counts read before and after. A tool
   that reports success while creating nothing is this codebase's signature
   defect.
5. **A browser check** of the chat surface on a node form, console read and
   reported.
6. **The drupal.org pipeline**, once the owner pushes. Local green is
   provisional until then.

Nothing is "done" on the strength of reasoning about the code.

---

## Agent description

**Who does this work.** A single agent with write access limited to
`modules/paragraphs_bundles_ai/`, read access to the whole repository and to
`apex_ai_bridge` as the reference implementation, and shell access to
`sts.local` via the drush invocation in §1.

**The priors it is given, in full, every time**: these are not to be inferred
from the code:

- **Better safe than sorry.** Aggressive on architecture, conservative on
  process. Verify before commit. Validate at trust boundaries. When uncertain,
  hold and ask.
- **One source of truth.** Every fact has exactly one owner. Two copies of a
  fact IS the bug. Enforce it with a test, because an unenforced rule decays
  into a suggestion.
- **Build once and done.** No shortcuts, no "we will fix it in v2".
- **Understand before you change.** Never accept a change, mine or an agent's,
  without understanding why the code is the way it is. A plausible fix to
  code you do not understand turns a design into a regression.
- **Measure, do not assume.** Report what was measured and how. Say plainly
  when a number is a guess. A red result from your own tooling is as likely to
  be a bad measurement as a bad module. Check the tool before blaming the
  work.
- **No leftovers.** A rule without a harness, a decision that lives only in a
  conversation, a pattern discussed but not written down: each one costs the
  next session the same work again.

**Refusal is success, not failure.** An agent that stops at §4 and asks has
done its job correctly.
