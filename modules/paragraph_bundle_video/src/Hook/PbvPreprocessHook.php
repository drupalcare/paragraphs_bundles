<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_video\Hook;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Language\LanguageManager;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\paragraph_bundle_video\PbVideo;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Preprocess hook — prepares the video data the template prints.
 *
 * The media is read here, not through its display, because a Player must
 * print its own video markup with a captions track and a remote embed must
 * be deferred behind Play. Access and cache metadata are therefore this
 * class's job.
 */
final class PbvPreprocessHook {

  use StringTranslationTrait;

  /**
   * Ratio field values mapped to the CSS class token.
   */
  private const array RATIOS = [
    '16_9' => '16-9',
    '4_3' => '4-3',
    '1_1' => '1-1',
    '9_16' => '9-16',
  ];

  /**
   * Used only when the required breakpoint field holds no usable value.
   */
  private const int DEFAULT_BREAKPOINT = 768;

  public function __construct(
    private readonly EntityRepositoryInterface $entityRepository,
    private readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * Implements hook_preprocess_paragraph().
   *
   * @param array<string, mixed> $variables
   *   The template variables.
   */
  #[Hook('preprocess_paragraph')]
  public function preprocessParagraph(array &$variables): void {
    $paragraph = $variables['paragraph'] ?? NULL;
    if (!$paragraph instanceof ParagraphInterface || $paragraph->bundle() !== PbVideo::BUNDLE) {
      return;
    }

    $cacheability = new CacheableMetadata();
    $langcode = $paragraph->language()->getId();
    $media = $this->video($paragraph, $langcode, $cacheability);

    $data = [
      'mode' => $this->fieldValue($paragraph, PbVideo::FIELD_MODE) === PbVideo::MODE_BACKGROUND ? PbVideo::MODE_BACKGROUND : PbVideo::MODE_PLAYER,
      'ratio' => self::RATIOS[$this->fieldValue($paragraph, PbVideo::FIELD_RATIO)] ?? self::RATIOS['16_9'],
      'breakpoint' => $this->breakpoint($paragraph),
      'kind' => '',
      'sources' => [],
      'tracks' => [],
      'embed' => NULL,
      'thumbnail' => NULL,
      'link' => '',
    ] + $this->labels($paragraph, $media);

    if ($media?->bundle() === PbVideo::MEDIA_FILE) {
      $data['kind'] = 'file';
      $file = $this->file($media, PbVideo::MEDIA_FIELD_FILE, $cacheability);
      if ($file !== NULL) {
        $url = $file->createFileUrl();
        $data['sources'] = [['url' => $url, 'type' => $file->getMimeType()]];
        $data['tracks'] = $this->tracks($media, $langcode, $cacheability);
        $data['link'] = $url;
      }
    }
    elseif ($media?->bundle() === PbVideo::MEDIA_REMOTE) {
      $data['kind'] = 'remote';
      $data['embed'] = $this->embed($media, $cacheability);
      $data['thumbnail'] = $this->thumbnail($media, $cacheability);
      $data['link'] = $this->httpUrl($this->fieldValue($media, PbVideo::MEDIA_FIELD_URL));
    }

    $variables['pb_video'] = $data;
    CacheableMetadata::createFromRenderArray($variables)->merge($cacheability)->applyTo($variables);
  }

  /**
   * Returns the referenced media when the visitor may see it.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph.
   * @param string $langcode
   *   The display language.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects the access and entity cache metadata.
   *
   * @return \Drupal\media\MediaInterface|null
   *   The media in the display language, or NULL.
   */
  private function video(ParagraphInterface $paragraph, string $langcode, CacheableMetadata $cacheability): ?MediaInterface {
    if (!$paragraph->hasField(PbVideo::FIELD_VIDEO)) {
      return NULL;
    }
    $media = $paragraph->get(PbVideo::FIELD_VIDEO)->entity;
    if (!$media instanceof MediaInterface) {
      return NULL;
    }
    $media = $this->entityRepository->getTranslationFromContext($media, $langcode);
    if (!$media instanceof MediaInterface) {
      return NULL;
    }
    $access = $media->access('view', NULL, TRUE);
    $cacheability->addCacheableDependency($access);
    if (!$access->isAllowed()) {
      return NULL;
    }
    $cacheability->addCacheableDependency($media);
    return $media;
  }

  /**
   * Returns a file referenced by the media when the visitor may see it.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity holding the file field.
   * @param string $field
   *   The file field name.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects the access and entity cache metadata.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file, or NULL.
   */
  private function file(FieldableEntityInterface $entity, string $field, CacheableMetadata $cacheability): ?FileInterface {
    if (!$entity->hasField($field)) {
      return NULL;
    }
    $file = $entity->get($field)->entity;
    if (!$file instanceof FileInterface) {
      return NULL;
    }
    $access = $file->access('view', NULL, TRUE);
    $cacheability->addCacheableDependency($access);
    if (!$access->isAllowed()) {
      return NULL;
    }
    $cacheability->addCacheableDependency($file);
    return $file;
  }

  /**
   * Returns the captions track of a self-hosted video.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media.
   * @param string $langcode
   *   Used when the media does not name a usable language.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects the file cache metadata.
   *
   * @return list<array{src: string, srclang: string, label: string}>
   *   The track, or an empty list.
   */
  private function tracks(MediaInterface $media, string $langcode, CacheableMetadata $cacheability): array {
    $file = $this->file($media, PbVideo::MEDIA_FIELD_CAPTIONS, $cacheability);
    if ($file === NULL) {
      return [];
    }
    $code = trim($this->fieldValue($media, PbVideo::MEDIA_FIELD_CAPTIONS_LANG));
    if (preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]{1,8})*$/', $code) !== 1) {
      $code = $langcode;
    }
    return [
      [
        'src' => $file->createFileUrl(),
        'srclang' => $code,
        'label' => $this->languageName($code),
      ],
    ];
  }

  /**
   * Returns a human-readable language name for a language code.
   *
   * @param string $code
   *   The language code.
   *
   * @return string
   *   The name, or the code when it is not a known language.
   */
  private function languageName(string $code): string {
    $language = $this->languageManager->getLanguage($code);
    if ($language !== NULL) {
      return $language->getName();
    }
    return LanguageManager::getStandardLanguageList()[$code][0] ?? $code;
  }

  /**
   * Returns the remote video's embed, for printing inside the deferred markup.
   *
   * The URL is rendered through core's oEmbed formatter, so the iframe is
   * core's own and goes through its signed media.oembed_iframe route.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects the formatter's cache metadata, also when it renders nothing.
   *
   * @return array<string, mixed>|null
   *   The render array, or NULL when the provider gave nothing to embed.
   */
  private function embed(MediaInterface $media, CacheableMetadata $cacheability): ?array {
    if (!$media->hasField(PbVideo::MEDIA_FIELD_URL) || $media->get(PbVideo::MEDIA_FIELD_URL)->isEmpty()) {
      return NULL;
    }
    $build = $media->get(PbVideo::MEDIA_FIELD_URL)->view([
      'type' => 'oembed',
      'label' => 'hidden',
      'settings' => [
        'max_width' => 0,
        'max_height' => 0,
        // The visitor already chose to play it, so nothing is gained by
        // deferring the request further.
        'loading' => ['attribute' => 'eager'],
      ],
    ]);
    $cacheability->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
    return Element::children($build) === [] ? NULL : $build;
  }

  /**
   * Returns the local thumbnail core stored when the remote media was saved.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects the file cache metadata.
   *
   * @return array<string, mixed>|null
   *   An image render array, or NULL.
   */
  private function thumbnail(MediaInterface $media, CacheableMetadata $cacheability): ?array {
    $file = $this->file($media, 'thumbnail', $cacheability);
    if ($file === NULL) {
      return NULL;
    }
    $item = $media->get('thumbnail')->first();
    return [
      '#theme' => 'image',
      '#uri' => $file->getFileUri(),
      '#width' => $item?->get('width')->getValue(),
      '#height' => $item?->get('height')->getValue(),
      // The Play button next to it already names the video.
      '#alt' => '',
      '#attributes' => ['loading' => 'lazy'],
    ];
  }

  /**
   * Returns the Play and link labels.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph.
   * @param \Drupal\media\MediaInterface|null $media
   *   The media, when the visitor may see it.
   *
   * @return array{play_label: \Drupal\Core\StringTranslation\TranslatableMarkup, link_label: \Drupal\Core\StringTranslation\TranslatableMarkup}
   *   The labels.
   */
  private function labels(ParagraphInterface $paragraph, ?MediaInterface $media): array {
    $title = trim($this->fieldValue($paragraph, PbVideo::FIELD_TITLE));
    if ($title === '') {
      $title = trim((string) $media?->label());
    }
    if ($title === '') {
      return [
        'play_label' => $this->t('Play video'),
        'link_label' => $this->t('Watch video'),
      ];
    }
    return [
      'play_label' => $this->t('Play @title', ['@title' => $title]),
      'link_label' => $this->t('Watch @title', ['@title' => $title]),
    ];
  }

  /**
   * Returns the viewport width in pixels below which a film is not loaded.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph.
   *
   * @return int
   *   The width.
   */
  private function breakpoint(ParagraphInterface $paragraph): int {
    if (preg_match('/^pb__(\d+)$/', $this->fieldValue($paragraph, PbVideo::FIELD_BREAKPOINT), $matches) === 1) {
      return (int) $matches[1];
    }
    return self::DEFAULT_BREAKPOINT;
  }

  /**
   * Returns a field's main value as a string.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity.
   * @param string $field
   *   The field name.
   *
   * @return string
   *   The value, or an empty string.
   */
  private function fieldValue(FieldableEntityInterface $entity, string $field): string {
    if (!$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      return '';
    }
    $item = $entity->get($field)->first()?->getValue();
    $value = is_array($item) ? ($item['value'] ?? '') : '';
    return is_scalar($value) ? (string) $value : '';
  }

  /**
   * Returns the URL only when it is an http or https address.
   *
   * @param string $url
   *   The stored URL.
   *
   * @return string
   *   The URL, or an empty string.
   */
  private function httpUrl(string $url): string {
    $scheme = parse_url($url, PHP_URL_SCHEME);
    return in_array(is_string($scheme) ? strtolower($scheme) : '', ['http', 'https'], TRUE) ? $url : '';
  }

}
