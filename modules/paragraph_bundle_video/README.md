# Paragraph Bundle Video

One paragraph type, `video_bundle` (Video), with two modes:

- **Player:** a poster picture with a Play button, and the title outside the
  picture. Pressing Play starts the video in place.
- **Background:** a muted, looping film behind a section's own content, with a
  pause button. Video files only.

## What it installs

- Paragraph type `video_bundle`.
- Media type `pb_media_video` (source `video_file`; field `pb_video_file`
  accepts mp4 and webm; optional `pb_video_captions` for a `.vtt` file and
  `pb_video_captions_lang` for its language code).
- Media type `pb_media_remote_video` (source `oembed:video`; field
  `pb_video_url`; providers YouTube and Vimeo).
- Form and view displays for both media types, including the media library
  displays.
- Fields on `video_bundle`: `pb_content_video`, `pb_content_image` (poster),
  `pb_display_video_mode`, `pb_display_video_ratio`,
  `pb_display_video_breakpoint`, `pb_content_body` (transcript),
  `pb_content_video_main` (the description in Player mode, the plate's content
  in Background mode), plus the title and Display tab fields the Image bundle
  uses.

In Background mode the Background Color setting colors the text plate, not the
film.

The poster reuses `pb_content_image` and the `pb_media_image` media type from
`paragraph_bundle_image`, which is why that module is a dependency.

## Third-party connection and Content Security Policy

A video from a provider is not loaded until the visitor presses Play. Pressing
Play connects the visitor's browser to the provider. A site with a Content
Security Policy must allow the provider in `frame-src`. Add the video provider
to the site's privacy information as its owner sees fit; this module does not
make claims about consent or law.

## Dependencies

- `paragraphs_bundles` and `paragraph_bundle_image`.
- Core `file`, `image`, `media`, `media_library`, `options`, `text`.
- Contrib `paragraphs`, `entity_reference_revisions`, `field_group`.

## Enable

```bash
drush en paragraph_bundle_video -y
drush cr
```

## Editor notes

- A video file needs a poster. A video file without captions shows a warning on
  the editor form, and it can still be saved.
- Background mode accepts a video file only, and the film is decorative: do not
  say anything only in the film.
- The Display tab holds the shape (16:9, 4:3, 1:1, 9:16) and the screen width
  below which Background mode shows the poster instead of the film.
