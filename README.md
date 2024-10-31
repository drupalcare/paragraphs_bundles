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

The module supports types of paragraph bundles, one content type and one
block type:

# Module Categories Description

## 1. Display Formats
These modules focus on presenting content in visually appealing and dynamic
formats:

- **3D Carousel**: Presents content in a rotating 3D carousel format.
- **3D Flip Box**: Displays content in boxes that flip in a 3D space.
- **Lightbox Image Grid**: Arranges images in a responsive grid layout and opens
 each
  image in a lightbox.
- **Slideshow**: Showcases content in a slideshow format.
- **Responsive Image Narrow**: Adapts images to fit different screen sizes,
in square
  formats.
- **Responsive Image Wide**: Adapts images to fit different screen sizes, in
rectangle
  formats.
- **Parallax**: Creates a parallax scrolling effect for images or other content.

## 2. Interactive Elements
Modules that enhance user interaction and engagement through dynamic and
interactive
features:

- **Accordion**: Displays content in a collapsible format, allowing users to
expand or
  hide sections.
- **Tabs**: Organizes content in a tabbed interface.
- **Modal**: Displays content in a modal window, often used for login forms,
  notifications, or additional information.
- **Alert**: Shows alert messages or important information in an interactive
 format.

## 3. Layout Structures
These modules provide different structural layouts to organize content on a
 page:

- **Grid**: Displays content in a grid layout, adjustable between one and
 twelve columns.
- **One Column**: A simple, single-column layout.
- **Two Columns**: Divides content into two columns.
- **Three Columns**: Arranges content in three separate columns.
- **Card Two Columns**: Displays content in a card-style layout within two
 columns.
- **Card Three Columns**: Displays content in a card-style layout within three
 columns.
- **Hero**: Typically a large, full-width banner or image, often used at the
 top of a
  page.

## 4. Content Integration
Modules that are used to integrate or display specific types of content or
 external
services:

- **Node Reference**: For referencing any content.
- **Block Content**: For placing and arranging blocks of content.
- **Drupal Block**: Integrates Drupal blocks into the layout.
- **Image**: A module dedicated to displaying individual images.
- **Image Overlay**: Adds overlay effects to images.
- **Link**: Specifically for adding and managing hyperlinks.
- **PB Content & PB Block**: Special modules for integrating Paragraph
 Bundles' own
  content and block system.
- **Image Background**: Likely a straightforward to build content with an image
 background.
- **Simple**: Likely a straightforward, minimalistic content display.
- **Icon**: Display Google Material Symbols or upload an Image Icon.
- **Views**: Integrates the Views module, a powerful Drupal tool for
 organizing and
  displaying content.
- **Contact Form**: Embeds contact forms.
- **Webform**: Embeds web forms for user input and interaction.


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
