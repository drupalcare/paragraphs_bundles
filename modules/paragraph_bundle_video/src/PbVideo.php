<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_video;

/**
 * Machine names shared by the preprocess, form and validation code.
 */
final class PbVideo {

  /**
   * The paragraph type.
   */
  public const string BUNDLE = 'video_bundle';

  public const string FIELD_VIDEO = 'pb_content_video';

  public const string FIELD_POSTER = 'pb_content_image';

  public const string FIELD_TITLE = 'pb_content_title';

  public const string FIELD_MODE = 'pb_display_video_mode';

  public const string FIELD_RATIO = 'pb_display_video_ratio';

  public const string FIELD_BREAKPOINT = 'pb_display_video_breakpoint';

  public const string MODE_PLAYER = 'player';

  public const string MODE_BACKGROUND = 'background';

  /**
   * The self-hosted media type and its fields.
   */
  public const string MEDIA_FILE = 'pb_media_video';

  public const string MEDIA_FIELD_FILE = 'pb_video_file';

  public const string MEDIA_FIELD_CAPTIONS = 'pb_video_captions';

  public const string MEDIA_FIELD_CAPTIONS_LANG = 'pb_video_captions_lang';

  /**
   * The remote media type and its source field.
   */
  public const string MEDIA_REMOTE = 'pb_media_remote_video';

  public const string MEDIA_FIELD_URL = 'pb_video_url';

}
