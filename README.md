# Custom Paragraph Bundles Module

This module provides a powerful and flexible way to create custom paragraph
bundles in Drupal. It leverages the Paragraph and Field Group modules to
create unique bundles. Each bundle can be customized with its own colors,
border, layout, box shadow, margin, and padding.

## FEATURES

- **Independent:** The module does not depend on jQuery, making it
  lightweight, efficient and compatible with any Drupal theme.
- **Customizable:** Fully customizable paragraph bundles with options for
  colors, border, layout, box shadow, margin, and padding.
- **5 Breakpoints:** Supports five breakpoints for two or three column bundles,
  ensuring compatibility across devices.
- **Easy to Use:** User-friendly customization of all bundles.
- **Integration:** Utilizes Paragraph and Field Group modules; integrates with
  the [Solo theme](https://www.drupal.org/project/solo).

The module allows creation of paragraph bundles with two group field horizontal
tabs: Content and Display.

### Display Tab

The Display tab offers customization options for paragraph bundle appearance,
including six color input fields:

- Background color
- Background color hover
- Text color
- Text color hover
- Border color
- Border color hover

Additional seven fields for customization:

- Border
- Border radius
- Margin
- Padding
- Width in Percentage
- Box shadow
- Background color opacity

These 13 fields are default in most bundles. The number may vary by bundle type.

### Content Tab

The Content tab contains the actual content fields of the bundle. The number
of fields varies depending on the bundle type.

## BUNDLES

The module supports 26 types of paragraph bundles, one content type and one
block type:

1. 3D Carousel
2. 3D Flip Box
3. Accordion
4. Alert
5. Block Content
6. Card Two Columns
7. Card Three Columns
8. Contact Form
9. Drupal Block
10. Hero
11. Image (original size)
12. Responsive Image Narrow
13. Responsive Image Wide
14. Image Grid
15. Image Overlay
16. Link
17. Modal
18. One Column
19. Parallax
20. Simple
21. Slideshow
22. Tabs
23. Three Columns
24. Two Columns
25. Views
26. Webform

Each bundle can be customized using the Content and Display tab options.

### The PB Block

A custom block with a paragraph field, compatible with all themes. It can be
used to create a paragraph block and positioned in any region.

### The PB Content

A custom content type with disabled left and right regions. It comes with width
settings and comprises two group field horizontal tabs: Content and Display.
Works with Solo Theme Only.

#### Display Tab

- List of all solo regions that can be disabled.
- Field to select the maximum content width.
- Input field for content background color.
- Slide range for background opacity (1 to 100).

#### Content Tab

- Paragraph field to add any paragraph bundle. (Width setting for the paragraph
  bundle is disabled; content settings override it.)
- Block content reference field.

This feature allows crafting unique page layouts with different color schemes
or without elements like headers or footers.

## REQUIREMENTS

- Paragraph module
- Field Group module

## INSTALLATION

1. Download and enable the Paragraph and Field Group modules.
2. Download and enable this module.

## USAGE

After enabling the module, start creating custom paragraph bundles. Customize
each bundle with colors, border, layout, box shadow, margin, and padding.

## SUPPORT

Encounter issues or have questions? Please open an issue in the issue queue.
