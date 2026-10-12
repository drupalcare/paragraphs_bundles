# Prompts that work, and what they produced

Every prompt below was **actually run** against OpenAI
**`gpt-5-nano`**, the most cost-effective model available, and every value shown is what
came back. Nothing here is illustrative.

**30 sections written, 0 failures, 0 values over a character limit, 0 values
outside an allowed list.**

> **Note:** The schema constraints (character limits, allowed values) are enforced
> by the structured output, not by the model's behavior. The *quality* of results
> improves when the model is given each section's description. The parallax entry
> below demonstrates this difference.

### Finding the prompt for a section you are looking at

Hover any section on the page and a small note shows its id: `pb-accordion-281`,
`pb-card-two-columns-299`. The middle of that id is the section type. Every
entry below names its machine name, so `pb-card-two-columns-299` →
`card_two_columns_bundle` → the row in the table at the end.

---

## The one thing worth learning

Most sections expose **three** writable things: the title, its alignment, and
its heading level. You steer all three in plain English, in the same sentence:

> …heading **"Questions we get a lot"** as an **h2**, **centered**.

came back as:

```
pb_content_title           = Questions we get a lot
pb_content_title_tag       = h2
pb_content_title_alignment = center
```

"centered" became `center`, and "h2" became `h2`, because those are the real
values that field accepts. The module hands the model the actual list, so it
picks from it rather than guessing. This worked on **29 of 29** sections that
have a title.

**Say what you want in quotes.** A heading in quotation marks comes back
verbatim. A vague prompt gets a vague heading.

---

## Sections that can hold real copy

These have a body, so the prompt is worth writing properly.

### Simple

> Write a short welcome for Hearth, a small Bristol bakery. Heading "Baked here every morning" as an h2, left aligned, then two warm sentences about sourdough starters and early opening.

```
pb_content_title = Baked here every morning
pb_content_body  = Our sourdough starters wake with the dawn, filling Hearth,
                   our small Bristol bakery, with a warm, comforting aroma. We
                   open early every morning so you…
pb_content_title_tag = h2      pb_content_title_alignment = left
```

### Hero

> A hero for the autumn range. Heading "Autumn baking is here" as an h1, centered. Two sentences underneath about seasonal loaves and spiced buns. The link should read "See the autumn range".

```
pb_content_title            = Autumn baking is here
pb_content_body             = Seasonal loaves bring autumn warmth to every bite.
                              Spiced buns complete the seasonal bake…
pb_content_image_link_title = See the autumn range
pb_content_title_tag = h1      pb_content_title_alignment = center
```

### Icon

Name the icon and the style and you get both.

> A feature block about our flour. Use the outlined icon style with the icon name "grain". Heading "Stone-milled flour" as an h3, left aligned, and one sentence about the mill we buy from.

```
pb_content_gms_icon_name = grain
pb_content_gms_style     = outlined
pb_content_title         = Stone-milled flour
pb_content_body          = We buy from a local, family-owned mill known for
                           traditional stone-ground methods.
```

### Modal

Three separate pieces of text. Say which is which.

> An allergen notice. The section heading is "Before you order" as an h3, centered. The button says "Allergens". The modal title is "What is in our bread".

```
pb_content_title       = Before you order
pb_content_modal_button = Allergens
pb_content_modal_title  = What is in our bread
```

---

## Sections that hold other sections

**Name every child, or you get padding.** This is the single biggest
difference between a good result and a poor one.

### Accordion: named children

> Our most-asked questions, heading "Questions we get a lot" as an h2, centered. Two entries: which areas we deliver to, and where to find allergen information.

```
pb_content_title = Questions we get a lot
child 1 = which areas we deliver to
child 2 = where to find allergen information
```

### Tabs

> A tabbed menu, heading "What we bake" as an h2, left aligned. Two tabs: everyday loaves, and weekend specials.

```
child 1 = everyday loaves       child 2 = weekend specials
```

### Slideshow

> A slideshow of the seasonal range, heading "Through the year" as an h2, centered. Two slides: winter rye, and spring sourdough.

```
child 1 = winter rye            child 2 = spring sourdough
```

### Links

> A set of quick links, heading "Order and visit" as an h3, centered. Two links: order online, and find the shop.

```
child 1 = order online          child 2 = find the shop
```

### Image grid: `image_grid_bundle`

> A grid of the bakery at work, heading "In the bakehouse" as an h2, left aligned. Two images: the mixing bench, and the stone oven.

```
pb_content_title = In the bakehouse
child 1 = Mixing bench      child 2 = Stone oven
```

The children came back with their own title, alignment and heading level set,
because a grid item has those fields too. The images themselves are yours to
add.

### Parallax: `parallax_bundle`: naming children matters

If you ask for a parallax without naming the children:

> A full-width banner over a photograph of the oven, heading "Fired at five in the morning" as an h2, centered.

The heading comes back correctly, but the children are generic. The model describes
the layout back rather than writing copy for it:

```
child 1 = Full-width banner over a photograph of the oven.
child 2 = Centered heading fired at five in the morning.
```

The model does better when it understands what the section is, not just its label.
Every section bundle now ships a description that travels in the prompt.

With that understanding in place, an equally unspecific request produces real copy:

> a hero banner for a mountain guiding company running guided treks in the Alps

```
child 1 = Ascend with expert guides on unforgettable Alpine treks. From mellow
          ridge walks to demanding summit routes, we tailor journeys for every
          level.
child 2 = From Mont Blanc to the Dolomites, explore pristine valleys, granite
          faces, and sweeping high passes with safety, local knowledge, and
          small-group camaraderie.
```

**Naming each child is the best thing you can do**. It is the difference between
copy about *your* business and copy about a business like yours.

Only six sections can have children written at all: accordion, image grid,
link, parallax, slideshow, tabs. The rest refuse with a reason. See the
*Children* section of the README.

---

## Sections with a picture

The AI writes the words around the picture and never chooses the picture itself.

Where a picture is **required**, the module puts its own shipped
placeholder in, so the section works the moment it is created, and tells you by
label to replace it. See *Pictures are supplied, not invented* in the README. An
**optional** picture field is still left empty.

| Prompt | Came back as |
|---|---|
| …heading "Find us on Gloucester Road" as an h3, centered, and the image link reads **"Get directions"**. | `pb_content_image_link_title = Get directions` |
| …heading "Five in the morning" as an h2, centered, image link **"See more photographs"**. | `= See more photographs` |
| …heading "Meet Ama" as an h3, left aligned, image link **"Read her story"**. | `= Read her story` |
| …heading "Bread worth queueing for" as an h2, centered, image link **"Order ahead"**. | `= Order ahead` |

---

## Two unusual ones

### Node reference: you can pick the view mode in plain English

> Point at our news pages, **shown as teasers**. Heading "Latest from the bakery" as an h3, left aligned.

```
pb_content_view_mode = vm_teaser_text
```

"shown as teasers" resolved to the real machine name. The other choices on a
stock install are `vm_title`, `vm_slideshow` and `vm_3d_carousel`.

### 3D carousel: no title at all, just sizes

> A 3D carousel sized for a wide banner: make the large panels **250** and the small ones **150**.

```
pb_content_3dcar_lheight = 250
pb_content_3dcar_sheight = 150
```

---

## Sections that only take a heading

For these the prompt is one line. Each of these ran and wrote correctly:

| Section | Machine name (the id on the page) | Prompt used |
|---|---|---|
| Alert | `alert_bundle` | An opening-hours notice, heading "We close early on Sundays" as an h3, centered. |
| Carousel | `carousel_bundle` | A rotating set of customer photographs, heading "From our regulars" as an h2, centered. |
| Card, two columns | `card_two_columns_bundle` | Two cards side by side, heading "Bread and pastry" as an h2, left aligned. |
| Card, three columns | `card_three_columns_bundle` | Three cards, heading "Order, collect, enjoy" as an h2, centered. |
| One column | `one_column_bundle` | A single full-width block, heading "Our story" as an h2, left aligned. |
| Two columns | `two_columns_bundle` | Two columns of text, heading "The mill and the bakehouse" as an h2, left aligned. |
| Three columns | `three_columns_bundle` | Three columns, heading "Flour, water, time" as an h2, centered. |
| Grid | `grid_bundle` | A grid layout, heading "Everything we make" as an h2, left aligned. |
| 3D flip box | `3d_flip_box_bundle` | A card that flips to show the recipe, heading "Our rye, front and back" as an h3, centered. |
| Block | `block_bundle` | A place for the opening-hours block, heading "When we are open" as an h3, left aligned. |
| Block content | `block_content_bundle` | A place for the newsletter sign-up block, heading "Bread news, once a month" as an h3, centered. |
| Views | `views_bundle` | A place for the latest-news view, heading "Latest from the bakery" as an h2, left aligned. |
| Webform | `webform_bundle` | A place for the wholesale enquiry form, heading "Wholesale enquiries" as an h2, left aligned. |
| Contact form | `contact_form_bundle` | A place for the contact form, heading "Say hello" as an h2, centered. |

The last five hold a block, a view or a form. The AI writes the heading; **you
choose the thing itself**, and the module tells you so by name after it is
added.

## Editing an existing section

Point at a section by its id, then say what should change:

> Make it one sentence, and mention we open at six.

Only the fields you are changing come back. Everything else is left alone, and
the previous revision is kept so it can be undone.

---

## Two sections take no words at all

`image_background` and `pb_target` have no text fields. The assistant refuses
rather than inventing something:

> The section Image Background has no text fields to fill in.

---

## Caveats

- One model (`gpt-5-nano`), one provider, one language, one run each. A
  different model will word things differently; the **constraints**, character
  limits and allowed values are enforced by the schema, not by the model's
  good behavior.
- The prompts above are English and benign. Nothing adversarial was tested.
- See [`AI-TESTING.md`](AI-TESTING.md) for the full list of what has **not**
  been tested.
