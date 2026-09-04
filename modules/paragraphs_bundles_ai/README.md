# Paragraphs Bundles AI

Create paragraph sections by describing them, from a chat bar on the node form.

This module is **optional**. Paragraphs Bundles works exactly as it does today
without it, and nothing here changes how the parent module behaves.

## What it does

Open a node form, click the assistant in the corner, and say what you want:

> *add a slideshow with three slides*

The assistant will not guess at anything that matters:

- If you have not said which content type, it names the ones that have a
  paragraph field.
- If the content type has more than one paragraph field, it asks which.
- If the field does not accept that section, it says so and lists what it
  does accept.
- If the section does not exist on this site, it says so rather than
  inventing one.

It asks once, for everything it is missing, instead of one question per turn.

## What it will not do

- **It never publishes anything.** New pages are created unpublished.
- **It never replaces existing sections** unless you say "replace".
- **Editing always writes a new revision**, and tells you the revision number,
  so any change is one step from being undone.
- **It never changes a settings form** — not yours, not another module's. Where
  a setting blocks the job it names the setting and stops.
- **It never writes text into a field.** It drafts text and shows it to you;
  putting it on the page stays your decision.

Everything it can do, you could already do by hand. Every tool re-checks your
permissions on every call.

## Requirements

- `paragraphs_bundles`
- [`drupal/ai`](https://www.drupal.org/project/ai) — required
- `ai_assistant_api`, `ai_agents` and `ai_chatbot` — submodules of `drupal/ai`,
  needed for the chat bar. **All three.** See the note below, because
  `ai_agents` is the one people miss.
- An AI provider with an API key. This module never asks for, stores, or
  transmits a key; the provider does that.

Drupal 11.3 or later. Not Drupal 12 yet — `drupal/ai` does not declare `^12`,
and advertising a compatibility a dependency does not have would offer this
module on a site where it cannot install. The parent module supports Drupal 12
regardless; a Drupal 12 site simply does not get this submodule until
`drupal/ai` catches up.

## Setting it up

```bash
composer require drupal/ai
drush en ai ai_assistant_api ai_agents ai_chatbot paragraphs_bundles_ai
```

1. **Configure a provider** and give it a key — see the `ai` module's own
   documentation.

2. **Create an agent** at *Configuration › AI › AI Agents*, and enable these
   three tools on it:

   - `pb_content:resolve_target`
   - `pb_content:create_sections`
   - `pb_content:draft_field_text`

   **This step is not optional and it is the one that gets missed.** An AI
   assistant on its own builds its tool list from *actions*, which are a
   different kind of plugin. The tools in this module are *function calls*, and
   an assistant only reaches those through an agent. Skip this and the chat bar
   appears, answers politely, and cannot do a single thing described above.

3. **Create an assistant** at *Configuration › AI › AI Assistants*. Set its
   **AI Agent** to the agent from step 2, and turn on **Use function calling**.

4. **Choose that assistant** at *Configuration › Content authoring ›
   Paragraphs Bundles AI*. That page also tells you, in a sentence, why the
   chat bar is or is not currently showing.

5. **Grant the permission** *"Use the Paragraphs Bundles assistant"* to the
   roles that should have it.

Miss any step and nothing appears — and nothing breaks either.

## Why is the chat bar not showing?

Go to *Configuration › Content authoring › Paragraphs Bundles AI*. It names the
missing piece directly: the permission, a module that is not enabled, no
assistant chosen, or an assistant that was chosen and later deleted.

The bar only appears on node **add** and **edit** forms, and only for users
with the permission.

## The tools, if you are building your own agent

| Tool | Writes? | What it is for |
|---|---|---|
| `pb_content:resolve_target` | never | Works out where sections can go, or returns every question blocking that at once. |
| `pb_content:create_sections` | yes | Creates the sections. Re-checks every precondition itself rather than trusting that `resolve_target` was called. |
| `pb_content:draft_field_text` | never | Reports what a section can hold, with real character limits, and fits a draft to a field. |

They work through any `drupal/ai` caller, not only the chat bar.

## Running the tests

From the site root, with this module in `modules/contrib/paragraphs_bundles`:

```bash
export SIMPLETEST_DB="sqlite://localhost/:memory:"
./vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/contrib/paragraphs_bundles/modules/paragraphs_bundles_ai
```
