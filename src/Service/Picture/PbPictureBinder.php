<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Service\Picture;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Element;
use Drupal\media\MediaInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Draws a patterned section's pictures with the view mode its Pattern names.
 *
 * The nearest Pattern wins: the paragraph's own CSS classes first, then each
 * parent paragraph's, upwards. A paragraph whose nearest Pattern does not name
 * the field, or that sits in no Pattern, keeps the bundle display.
 *
 * Cacheability: the decision reads the CSS classes of the paragraph and its
 * parents, so each parent visited is added as a cache dependency of the
 * picture field. The Pattern files are code, refreshed by a cache rebuild.
 *
 * @phpstan-import-type PictureSlot from \Drupal\paragraphs_bundles\Service\Picture\PbPictureMapProviderInterface
 */
final class PbPictureBinder implements PbPictureBinderInterface {

  /**
   * The field that carries a section's Pattern class.
   */
  public const CLASS_FIELD = 'pb_display_css_classes';

  /**
   * The only formatter whose media items are re-drawn.
   */
  private const FORMATTER = 'entity_reference_entity_view';

  /**
   * Stops the walk up the parents; a deeper tree is not a real page.
   */
  private const MAX_DEPTH = 20;

  /**
   * The paragraph view modes a Pattern binds: the page and the editor preview.
   *
   * A site's own view mode (a summary, a search index) keeps its own choice.
   */
  private const VIEW_MODES = ['default', 'full', 'preview'];

  /**
   * The Pattern sets.
   *
   * @var list<\Drupal\paragraphs_bundles\Service\Picture\PbPictureMapProviderInterface>
   */
  private array $providers = [];

  /**
   * Slot keys of every provider, as keys, memoized for the request.
   *
   * @var array<string, true>|null
   */
  private ?array $slots = NULL;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityDisplayRepositoryInterface $entityDisplayRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function addProvider(PbPictureMapProviderInterface $provider): void {
    $this->providers[] = $provider;
    $this->slots = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function bind(array &$build, ParagraphInterface $paragraph, EntityViewDisplayInterface $display, string $view_mode): void {
    if (!in_array($view_mode, self::VIEW_MODES, TRUE)) {
      return;
    }
    $fields = $this->candidateFields($build, $paragraph, $display);
    if ($fields === []) {
      return;
    }

    $cacheability = new CacheableMetadata();
    $map = $this->nearestPictures($paragraph, $cacheability);

    foreach ($fields as $field) {
      $element = is_array($build[$field] ?? NULL) ? $build[$field] : [];
      $slot = $map[$paragraph->bundle() . '.' . $field] ?? NULL;
      if ($slot !== NULL && $slot['view_mode'] !== '') {
        $element = $this->redraw($element, $slot['view_mode']);
      }
      CacheableMetadata::createFromRenderArray($element)
        ->merge($cacheability)
        ->applyTo($element);
      $build[$field] = $element;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function imageStyle(ParagraphInterface $paragraph, string $field, CacheableMetadata $cacheability): ?string {
    $key = $paragraph->bundle() . '.' . $field;
    if (!isset($this->slots()[$key]) || !$paragraph->hasField($field)) {
      return NULL;
    }
    if ($paragraph->getFieldDefinition($field)?->getType() !== 'image') {
      return NULL;
    }
    $style = $this->nearestPictures($paragraph, $cacheability)[$key]['style'] ?? '';
    if ($style === '' || !$this->entityTypeManager->hasDefinition('image_style')) {
      return NULL;
    }
    $entity = $this->entityTypeManager->getStorage('image_style')->load($style);
    if ($entity === NULL) {
      // A missing style (its module not installed) keeps the original file
      // rather than a broken URL, until a style is added.
      $cacheability->addCacheTags(['config:image_style_list']);
      return NULL;
    }
    $cacheability->addCacheableDependency($entity);
    return $style;
  }

  /**
   * Lists the paragraph's media fields some Pattern names and the display drew.
   *
   * @param array<string, mixed> $build
   *   The field render arrays.
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph.
   * @param \Drupal\Core\Entity\Display\EntityViewDisplayInterface $display
   *   The view display.
   *
   * @return list<string>
   *   Field names.
   */
  private function candidateFields(array $build, ParagraphInterface $paragraph, EntityViewDisplayInterface $display): array {
    $slots = $this->slots();
    if ($slots === []) {
      return [];
    }
    $bundle = $paragraph->bundle();
    $fields = [];
    foreach ($display->getComponents() as $field => $component) {
      if (!isset($slots[$bundle . '.' . $field]) || !is_array($build[$field] ?? NULL)) {
        continue;
      }
      if (($component['type'] ?? NULL) !== self::FORMATTER || !$paragraph->hasField($field)) {
        continue;
      }
      $definition = $paragraph->getFieldDefinition($field);
      if ($definition !== NULL && $definition->getFieldStorageDefinition()->getSetting('target_type') === 'media') {
        $fields[] = $field;
      }
    }
    return $fields;
  }

  /**
   * Finds the pictures map of the paragraph's nearest Pattern.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects every parent paragraph the walk read.
   *
   * @return array<string, PictureSlot>
   *   The map; empty when the paragraph sits in no Pattern.
   */
  private function nearestPictures(ParagraphInterface $paragraph, CacheableMetadata $cacheability): array {
    $current = $paragraph;
    for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
      if ($depth > 0) {
        $cacheability->addCacheableDependency($current);
      }
      $map = $this->patternPictures($current);
      if ($map !== NULL) {
        return $map;
      }
      // The host being rendered first (core sets _referringItem on every
      // referenced entity it draws), so an unsaved Preview, a draft or an
      // older revision decides by the classes actually on screen. A paragraph
      // drawn on its own has none and falls back to the stored parent.
      $parent = isset($current->_referringItem) ? $current->_referringItem->getEntity() : $current->getParentEntity();
      if (!$parent instanceof ParagraphInterface) {
        return [];
      }
      $current = $parent;
    }
    return [];
  }

  /**
   * Returns the pictures map of the Pattern a paragraph's own classes name.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph.
   *
   * @return array<string, PictureSlot>|null
   *   The map, or NULL when its classes name no Pattern.
   */
  private function patternPictures(ParagraphInterface $paragraph): ?array {
    if (!$paragraph->hasField(self::CLASS_FIELD)) {
      return NULL;
    }
    $value = trim($paragraph->get(self::CLASS_FIELD)->getString());
    if ($value === '') {
      return NULL;
    }
    foreach (preg_split('/\s+/', $value) ?: [] as $class) {
      foreach ($this->providers as $provider) {
        $map = $provider->pictures($paragraph->bundle(), $class);
        if ($map !== NULL) {
          return $map;
        }
      }
    }
    return NULL;
  }

  /**
   * Re-draws each media item of a field in another view mode.
   *
   * The items are the ones the formatter already allowed, so media access is
   * unchanged; their access cacheability is kept on the new arrays.
   *
   * @param array<string|int, mixed> $element
   *   The field render array.
   * @param string $view_mode
   *   The media view mode.
   *
   * @return array<string|int, mixed>
   *   The field render array.
   */
  private function redraw(array $element, string $view_mode): array {
    $builder = $this->entityTypeManager->getViewBuilder('media');
    foreach (Element::children($element) as $delta) {
      $item = $element[$delta];
      if (!is_array($item) || !($item['#media'] ?? NULL) instanceof MediaInterface) {
        continue;
      }
      if (($item['#view_mode'] ?? NULL) === $view_mode) {
        continue;
      }
      $media = $item['#media'];
      // Without a display for this media type in that mode, core would fall
      // back to the media default display, worse than the bundle's choice.
      if (!array_key_exists($view_mode, $this->entityDisplayRepository->getViewModeOptionsByBundle('media', $media->bundle()))) {
        continue;
      }
      $langcode = $media->language()->getId();
      $new = $builder->view($media, $view_mode, $langcode);
      CacheableMetadata::createFromRenderArray($item)
        ->merge(CacheableMetadata::createFromRenderArray($new))
        ->applyTo($new);
      if (isset($item['#weight'])) {
        $new['#weight'] = $item['#weight'];
      }
      $element[$delta] = $new;
    }
    return $element;
  }

  /**
   * Returns every slot key of every provider.
   *
   * @return array<string, true>
   *   Slot keys as keys.
   */
  private function slots(): array {
    if ($this->slots === NULL) {
      $this->slots = [];
      foreach ($this->providers as $provider) {
        foreach ($provider->slots() as $slot) {
          $this->slots[$slot] = TRUE;
        }
      }
    }
    return $this->slots;
  }

}
