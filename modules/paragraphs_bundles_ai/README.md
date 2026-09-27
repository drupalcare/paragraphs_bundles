# Paragraphs Bundles AI

Write a section by saying what it should say, from a bar at the foot of the
page you are looking at.

This module is **optional**. Paragraphs Bundles works exactly as it does
without it, and nothing here changes how the parent module behaves.

## What it does

Open a page you can edit. A bar appears at the bottom, with a small half-circle
button on its top edge that hides it and brings it back — the choice is
remembered in your browser.

1. **Choose a section** — the list is the parent sections this site has
   installed. A slideshow, a tabs section and a card section are all in it;
   the individual slides, tab panels and card panels are not, because those
   belong inside a section rather than on a page.
2. **Say how many go inside it** — this appears for sections with ONE place
   for children: slides, rows, tabs, carousel items. Sections with several
   places — columns, a flip box's two faces — get one in each. See *Children*.
3. **Say what it should say** — *"our autumn range, warm and short"*.
4. **Pick a style**, if you want one.
5. **Choose where it goes**, if the content type has more than one paragraph
   field. With one field there is no question to ask and no control.
6. **Press "Add it to this page."**

The section is created, filled, its children nested, and saved as a new
revision. The page reloads onto it, the new section is marked **New**, and
there is an **Undo** link.

To change something, hover a section: a small note shows its id, such as
`pb-slideshow-186`. Choose that id in **"Or edit a section"**, say what should
change, and press **Edit that section**. Sections the assistant cannot
meaningfully reword — a Views section is a pointer at a view — are left out of
that list rather than offered and then refused.

### The box tells you what to say

Choosing a section rewrites the hint inside *"What should it say?"*. It is built
from that section's real fields, never from a list written by hand, so it cannot
disagree with what the section actually holds:

| Section | The hint you get |
|---|---|
| Hero | Heading "…" as an h2, centred, then the Body and the Image Link Title. |
| Accordion | Heading "…" as an h2, centred, then say what goes in each Accordion Section. |
| Modal | Heading "…" as an h2, centred, then the Modal Button and the Modal Title. |
| 3D carousel | Say the Large Screen Height and the Small Screen Height. |
| AI image | AI Image takes no words — add it, then fill it in yourself. |

Measured across the 33 sections offered: **every one gets a hint, none is empty,
and the longest is 91 characters.**

A section is only told it "takes no words" when there is genuinely nothing to
say — **2** of the 33. A dropdown or a set of sizes still counts as something you
can say: *"make the large panels 250 and the small ones 150"* sets both carousel
heights, and *"use the outlined icon style"* sets the icon's style.

The heading habit is in there on purpose. Saying *"as an h2, centred"* in the
same sentence sets the heading level and the alignment together, because both
are real fields whose actual keys the model is handed — and it is the single
most useful thing to know here. Fields you must choose yourself are **not**
repeated in the hint; they have their own notice beside the dropdown.

### Children

Since 2026-09-27 the assistant writes what goes INSIDE a section, not just its
heading. Before that, a slideshow came back with slide titles and nothing in the
slides, and two columns, a carousel or a grid came back with a heading and
nothing else.

**What goes in each place comes from two sources, in this order:**

1. The field itself, when it names one kind — a slideshow's slides, a lightbox's
   pictures, a card column's card image or card text.
2. The `nesting:` list in `config/install/paragraphs_bundles_ai.briefs.yml`, for
   a place that accepts any section. Its FIRST entry is used unless the prompt
   asks for another from the same list — *"each slide holds a Hero"*. Nothing
   outside the list is ever built.

A place that accepts anything and has no `nesting:` entry is left empty, and the
assistant says why rather than guessing.

**How many.** One place: the "How many" box. Several places: one in each.
**How deep.** One level inside: a slide holds a Simple, and that Simple holds
nothing. A deeper request is refused, not built.

### Pictures are supplied, not invented

A model can write a hero's words; it cannot choose its picture. Until
2026-09-17 it did not try and said nothing about it, so a hero arrived with real
copy and an empty — *required* — image field. A hero without a picture is not a
hero.

So the module ships one: `images/pb-ai-placeholder.png`, drawn rather than
downloaded, so no third-party licence travels with it. Any **required** picture
field left empty gets it, and you are told by label to replace it with your own.
An **optional** picture field is left alone — a stand-in nobody asked for is
litter.

It is copied into your files once and reused, so a page of twelve sections adds
**one** picture to your media library, not twelve. It becomes a media item of the
type that field accepts. (Paragraphs Bundles requires the Media module, so every
PB picture field is a media field; `ai_image_bundle` is a plain image field for
sections you build yourself without media.)

**The placeholder shows the size to replace it with.** Since 2026-09-27 a slot
whose image style crops to a fixed size gets a grey placeholder drawn at that
size with the numbers on it — `1700 x 600` for a hero, `900 x 540` for a card
image, `1700 x 1700` for a narrow image — and the note names each one. The size
is READ from the field's view mode and image style, so a site that restyles gets
its own numbers. A slot with no fixed crop gets the picture above.

Measured on a stock install: **5** of the 33 sections offered needed only a
picture — hero, image, image narrow, image overlay, image wide — and all five
are now filled for you, along with **3** inner section bundles (card image,
image grid, parallax).

### Features are switched on, and named

A new section is created with its features on — a slideshow's arrows, dots and
timing, a carousel's looping — and the page then says which, and that each can be
changed or turned off in the section's Display tab. Otherwise nobody learns what
a section can do. The list is the `features:` block of each brief, written by a
person; `FeaturesContractTest` fails if one names a field or value that does not
exist. It never includes a colour.

### Sections that still need you to finish them

**6** need something no model can choose: a block, a block content item, a
contact form, a view, a webform, or the numeric settings an icon section takes.
The section is still created with its words, and you are told, by label, what to
go and choose.

## What the AI is and is not asked

**It is asked for words.** Nothing else.

It is handed a schema built from that one section's real fields — the field
names this site uses, the exact keys its dropdowns accept, the character limit
each one has — and it answers inside that shape. An invented field name has
nowhere to go.

**It never chooses** which section to build, which page to put it on, which
field to write into, or which section to change. You answered all four with a
dropdown and the page you are standing on.

**It never chooses a colour.** Styles are named presets, written and checked by
a person. The model may be given the list; it cannot invent a value.

**It never chooses a picture.** It is never shown one and never asked for one.
Where a picture is required, the module supplies its own placeholder for you to
replace.

**It is told what the section IS, not just what it is called.** Each bundle's
own description travels with the schema — *"a background picture that drifts
more slowly than the page as you scroll"* — so the model writes content **for** a
parallax instead of a description **of** one. Given only the label "Parallax
Section" it used to fill the body with prose about the layout. This is why every
shipped section now carries a sentence, the inner ones included.

**A formatted field keeps its text format.** Rewriting a body does not change
the format it was authored in, and a newly written body is given your own
default format rather than being left without one.

Whether this holds in practice is a measurement, not a promise:
[`AI-TESTING.md`](AI-TESTING.md) records what was run, against which model, with
the prompts used — and, at the end, an honest list of what has **not** been
tested.

## What it will not do

- **It never publishes.** Everything lands as a new revision of a page you can
  already edit, and the revision number comes back so it can be undone.
- **It never touches a section you did not point at**, and never another page.
- **It never changes a section's type, or what is inside it.** Changing a
  section changes its words.
- **It never acts beyond your permissions.** Every write re-checks that you may
  edit that page.

## Requirements

- `paragraphs_bundles`
- [`drupal/ai`](https://www.drupal.org/project/ai)
- An AI provider module with a key configured — this module never asks for,
  stores or transmits a key; `drupal/ai` and the provider do that.

**`ai_assistant_api`, `ai_agents` and `ai_chatbot` are not needed.** This
module talks to `drupal/ai` directly, so there is no assistant to create and no
agent to wire up.

Drupal 11.3 or later. Not Drupal 12 yet — `drupal/ai` does not declare `^12`,
and advertising a compatibility a dependency does not have would offer this
module on a site where it cannot install. The parent module supports Drupal 12
regardless.

## Setting it up

```bash
composer require drupal/ai drupal/ai_provider_openai
drush en ai ai_provider_openai paragraphs_bundles_ai
```

1. **Create the key first** — *Configuration › System › Keys › Add key*. This
   step is easy to skip and nothing later will work without it: the provider
   page offers a **select of existing keys**, not a text box, so its dropdown
   is empty until a key exists.
2. **Point the provider at it** — *Configuration › AI › Providers*.
3. **Choose a default chat provider and model** — *Configuration › AI ›
   Settings*. The bar does not appear until one is set, and it says so.
4. **Grant the permission** *"Use the Paragraphs Bundles assistant"* to the
   roles that should have it.

On a multi-site install, make sure you are on the site you think you are. A key
saved on one site lives in that site's database and is invisible to the others.

## Why is the bar not showing?

Five things have to be true, and the module names the one that failed:

- the page is being **viewed**, in **full** view mode — not a teaser or a
  listing;
- you have the **"Use the Paragraphs Bundles assistant"** permission;
- you may **edit** this page;
- its content type has a paragraph field that is **actually displayed** in the
  view mode being rendered;
- a default AI chat provider is configured.

They are checked in that order, and the permission comes before anything that
describes how the site is configured — so somebody without it learns nothing
about your setup.

## Styles

Four are shipped — Warm, Calm, Dark, High contrast — in
`config/install/paragraphs_bundles_ai.presets.yml`. Each is written in the
display fields that exist on every section with a Display tab, so one
definition covers them all, and a section missing a field simply does not get
that value.

A site can edit them, or add its own. Every colour pair in the shipped four
meets WCAG AA contrast; if you write your own, that check is yours to make —
nothing in the code measures it.

## Running the tests

From the site root, with this module in `modules/contrib/paragraphs_bundles`:

```bash
export SIMPLETEST_DB="sqlite://localhost/:memory:"
./vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/contrib/paragraphs_bundles/modules/paragraphs_bundles_ai
```

These build their own database and never touch a site.

## Prompts that work

[`AI-PROMPTS.md`](AI-PROMPTS.md) is a worked example for every section type —
the exact prompt, and the exact values it produced. All of them were run; none
were invented. It also shows the one habit that matters most: you steer the
heading text, its alignment and its heading level in plain English, in the same
sentence, and you must **name each child** or you get padding.

## What was actually tested

[`AI-TESTING.md`](AI-TESTING.md) is the record: the model used, the prompts
sent, every bundle it was run against, and what came back — including the
things that have **never been tested**, which are listed there rather than left
for you to discover.
