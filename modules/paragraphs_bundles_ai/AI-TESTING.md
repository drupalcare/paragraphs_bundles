# What this module was tested with, and what it can actually do

Tested on Drupal 11.4.6, against **OpenAI `gpt-5-nano`**
through `drupal/ai` and `ai_provider_openai`.

Every number below came from running the thing and reading the result. Where
something has **not** been tested, it says so. That list is at the end, and it
is not short.

---

For a worked prompt against every section type, with the values each one
produced, see [`AI-PROMPTS.md`](AI-PROMPTS.md).

## What the model is actually asked to do

The model is never asked to choose a page, a section type, or a field. Those
come from two dropdowns and the page the person is on. It is asked for **words
only**, inside a JSON schema built from the chosen bundle's own field
definitions:

- every property name is a real field machine name on that bundle;
- a list field becomes an `enum` of its real allowed values;
- a character limit becomes a `maxLength`;
- a field that holds a reference, an image or a color never reaches the schema.

`strict` is deliberately **FALSE**. A provider's strict mode requires every
property to be required, which would force the model to invent a value for
every field on the section, including ones nobody mentioned. A half-filled
section is the honest outcome of "add a hero saying we deliver on Tuesdays".

### The system prompt sent when adding a section

```
You are writing the content of one "<label>" section on a Drupal site.
What a "<label>" is: <the bundle's own description>                             <- only when the bundle has one
Answer only with the fields in the schema. Leave out any field the person did
not ask for rather than inventing something to fill it.
Write plain text. No HTML, no Markdown, no quotation marks around the whole value.
Keep to any character limit in the schema: the field will not hold more.
Also write N "<child label>" child section(s) in "children", in the order they should appear.
Each "<child label>" is: <the child bundle's own description>                   <- only when the child has one
Write real content for each child, not a description of what the section looks like.
```

A key improvement is that the model is now handed each bundle's description,
not just its label. Before this, `getDescription` appeared **zero** times in the
whole module, so the shipped bundle descriptions never reached the model. Given
only `"Parallax Section"` and nothing else, it wrote prose *about* a parallax layout
instead of copy *for* one.

Worse, the eight inner section bundles (a slide, a tab, an accordion row) had
shipped `description: ''` **on purpose**, recorded in an update hook: a slide is
never chosen from a list, so a site builder needs no sentence for it. True of the
person, and false of the model, which is the half that has to write the slide.
All eight now carry one, and `PbBundleDescriptionContractTest` fails if any
shipped bundle loses it.

**The same prompt, before and after** (`gpt-5-nano`, parallax with 2 children,
*"a hero banner for a mountain guiding company running guided treks in the
Alps"*):

> "Ascend with expert guides on unforgettable Alpine treks. From mellow ridge
> walks to demanding summit routes, we tailor journeys for every level."

### The system prompt sent when editing a section

```
You are rewriting one "<label>" section that already exists on a Drupal site.
It currently says:
- <field>: <current value>
Return ONLY the fields that should change. Leave out every field that should
stay as it is.
Write plain text, within any character limit in the schema. No HTML, no Markdown.
```

---

## The site it was measured on

| | |
|---|---|
| Parent bundles offered in the Add list | **33** |
| Of those, bundles that hold other sections | **18** |
| Bundles whose children can be **drafted** | **6** |
| Bundles that needed only a picture, now supplied automatically | **5** |
| Inner section bundles that need a picture, also supplied | **3** |
| Bundles that still need a human to finish them | **6** |
| Bundles carrying a description the model is given | **40 of 41** |

**33, where this file previously said 32**: `ai_image_bundle` is new. The
media-less fallback. The one bundle without a description is `pb_target_bundle`,
which belongs to `pb_import`, not to this project.

The gap between 18 and 6 is a real limit, not an oversight. See
*Children* below.

---

## Every bundle, written by the model

One call per bundle. Prompt used throughout:

> `A short section for a small Bristol bakery. Warm, plain, brief.`

| Result | Count |
|---|---|
| Drafted | **28** |
| Written to the page | **28** |
| Refused, correctly | **2** |
| Text over the field's real `maxLength` | **0** |
| Value outside a list field's allowed values | **0** |
| Asked for children and got none | **0** |
| Children nested | **12** |
| Bodies saved with a text format | **3**, none NULL |

The two refusals are `image_background_bundle` and `pb_target_bundle`. Both
genuinely have no text fields, so there is nothing for a model to write, and
the module says so rather than inventing something.

**This is the claim worth taking seriously:** across 28 different field shapes,
the model answered inside the schema every time, and never exceeded a character
limit or invented a list value. That is the schema doing the work, not the
prompt asking nicely.

## Adding and editing, end to end

Three further calls, on a real page:

| Prompt | Result |
|---|---|
| `A short welcome for a small bakery in Bristol. Warm, two sentences.` | 156 characters written into `pb_content_body` |
| `Three-season bread range: winter rye, spring sourdough.` (2 children) | title within its real 255 limit, **2 slides drafted**: "winter rye", "spring sourdough" |
| `Make it one sentence, and mention we open at six.` | section reworded, previous revision kept |

The body kept `format: basic_html` on **both** the create and the edit. A text
format of NULL renders authored markup as escaped source, which is what this
module did until the format handling was fixed on the same day.

## Children

Children are drafted only where the bundle names exactly **one** child type.
Of the 18 bundles that hold sections, **6** qualify: accordion, image grid,
link, parallax, slideshow, tabs.

For the other 12 the assistant **refuses with a reason** instead of creating an
empty parent:

- more than one field holds sections (a two-column bundle has a left and a
  right), so "put three cards in it" has not said where;
- the single child field accepts *any* bundle, which Drupal stores as an empty
  list, so the site has never said what belongs inside one.

The "How many" control only appears for bundles where children can actually be
written.

## More than one paragraph field

A content type with two paragraph fields was created, exercised and removed.
**17 assertions, all passing:**

- both fields are offered, with their human labels;
- a **Where** dropdown appears only when there is more than one;
- the Add list is per field: a field restricted to one bundle offers one, an
  unrestricted field offers all **32**, and the two lists genuinely differ;
- the Edit list spans both fields and names the field each section lives in;
- a submitted field name that was never offered is **refused**;
- a bundle the chosen field rejects is **refused**, with a control proving the
  same bundle is accepted by the other field, so the refusal is about the field
  and not the bundle.

## Automated suites

These build their own database and do not touch any site.

| Suite | Result |
|---|---|
| Kernel | **64 tests, 981 assertions**, 0 failures |
| Unit (includes CSS and config contracts) | **319 tests, 619 assertions**, 0 failures |

The unit suite went red on the way to this number, and the failure was real:
`PbModuleDependencyTest` reported that `paragraphs_bundles_ai.info.yml` did not
declare `image`, `file` and `paragraphs`, all three named by the
`ai_image_bundle` config added a day earlier. Drupal skips optional config whose
dependencies are unmet **silently**, and `drush en` still reports success, so
that bundle would have installed with no field and nothing to say why. Declared,
and green.

### The placeholder picture, end to end on a real site

When a picture field is required, a placeholder is supplied instead of leaving
the field empty. A slot with a fixed crop gets a placeholder drawn at that size
(for example `pb-ai-placeholder-1700x600.png`), and the person is told by label to replace
it with their own. See `DefaultImageTest` and the README.

Not a test double: composed a `hero_bundle` on a working site, which has a required
`pb_content_image`.

| | |
|---|---|
| Result | node 40, paragraph 317 |
| `pb_content_image` | → media 1 (`pb_media_image`), "Placeholder picture" |
| File | `public://pb-ai/pb-ai-placeholder.png`, 26,730 bytes, present on disk |
| Media items created | **1**, reused, not one per section |
| What the person is told | *"No picture can be written, so the placeholder that ships with this module was put in "Image". Open the section and replace it with your own."* |

The old note: *"this section still needs you to choose "Image""* is correctly
**gone**, rather than being printed alongside a field that is now filled.

### The placeholder changes with the section

The Add dropdown names a section and then offers one empty box. Nothing in it
said that a hero has a body **and** a link title, or that an accordion wants its
rows named, so a person who had never opened that bundle's edit form was
guessing.

Measured across the **33** parent sections offered: every one produces a hint,
**0 are empty**, and the longest is **91 characters**.

| Section | Hint produced |
|---|---|
| `hero_bundle` | Heading "…" as an h2, centered, then the Body and the Image Link Title. |
| `accordion_bundle` | Heading "…" as an h2, centered, then say what goes in each Accordion Section. |
| `modal_bundle` | Heading "…" as an h2, centered, then the Modal Button and the Modal Title. |
| `icon_bundle` | Heading "…" as an h2, centered, then the Body, the Icon Name and the Material Symbols Style. |
| `3d_carousel_bundle` | Say the Large Screen Height and the Small Screen Height. |
| `views_bundle` | Heading "…" as an h2, centered. |
| `ai_image_bundle` | AI Image takes no words. Add it, then fill it in yourself. |

**Fields needing a person are not repeated** in the hint, because they already
have their own notice beside the dropdown; saying it twice in two voices is worse
than saying it once. Pictures are left out for the same reason. The module
supplies one.

List fields are included if they hold real choices the person writes into —
such as icon style or carousel height, not steering fields like alignment or
heading level. The heading level and alignment are excluded because they are
matched on their real keys (`h2`, `center`) which means a person can say
*"as an h2, centered"* in one sentence and both fields are set.

The distinction between "fields a person writes words into" and "settings
fields" is derived from the field data itself, not from field names.

## The assistant bar

Verified in rendered markup on a real node: the bar, the form, the half-circle
toggle, `id="pb-ai-panel"`, `aria-controls`, `aria-expanded`, the accessible
name, the icon, the toggle nested inside the bar so it travels with it, and the
library attached.

---

## What has NOT been tested

Read this part before believing anything above is complete.

- **No browser has ever loaded this interface.** Every visual claim: the
  slide, the hover, the tooltip position, the highlight, the half circle is
  verified as markup and CSS, not as pixels. The DevTools console has never
  been read.
- **The AJAX path has never been exercised through a browser.** The *Add* and
  *Edit* buttons, the throbber, the redirect, the New/Updated badge and the
  Undo link have only been driven from PHP.
- **One model, one provider.** Only `gpt-5-nano` via OpenAI. No other model,
  no other provider, and no comparison between them.
- **One prompt style.** Short, plain, English, benign. No adversarial prompts,
  no other languages, no long or ambiguous requests.
- **No cost or latency measurements.**
- **Two kernel test files were never written**: `SectionComposer` and
  `PageSurface` have no dedicated tests, because a local security tool refused
  to write those two files and the refusal was reported rather than worked
  around.
- **The notes a person is shown after a write have no automated test.**
- **The style presets ship without an update hook**, so a site that already has
  this module installed will not receive them.

## Reproducing this

The measurements above were taken with throwaway scripts run through
`drush php:script`, not with anything shipped in this module. On a multi-site
install, pass `--uri` explicitly. Without it drush answers from
`sites/default`, which may be a different site with a different database, and
every answer will look confident and be wrong.
