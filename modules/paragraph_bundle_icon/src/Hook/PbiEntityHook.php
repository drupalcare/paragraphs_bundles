<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_icon\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Rebuilds the icon subset when the set of icons in use changes.
 *
 * The subset URL is baked into a library definition, and library definitions
 * are cached. Without this, an author adding an icon would get a font that
 * does not contain it — the icon would silently render as nothing until some
 * unrelated cache clear happened to rebuild the registry.
 *
 * Only the icon NAME and STYLE affect the subset. Colour, size and the four
 * axis fields all reach the page through the generated per-paragraph CSS and
 * change nothing about which glyphs the font must carry, so re-saving a
 * paragraph after changing its colour costs no invalidation. Same reasoning as
 * PbEntityHook, which compares the CSS filename before and after.
 */
final class PbiEntityHook {

  /**
   * The bundle whose icons form the subset.
   */
  private const string BUNDLE = 'icon_bundle';

  /**
   * Fields that change which glyphs the subset must contain.
   */
  private const string NAME_FIELD = 'pb_content_gms_icon_name';
  private const string STYLE_FIELD = 'pb_content_gms_style';

  /**
   * Implements hook_paragraph_insert().
   */
  #[Hook('paragraph_insert')]
  public function paragraphInsert(EntityInterface $entity): void {
    if ($this->isIcon($entity)) {
      Cache::invalidateTags(['library_info']);
    }
  }

  /**
   * Implements hook_paragraph_update().
   */
  #[Hook('paragraph_update')]
  public function paragraphUpdate(EntityInterface $entity): void {
    if (!$this->isIcon($entity)) {
      return;
    }

    $original = $entity->original ?? NULL;
    if ($original instanceof ParagraphInterface
      && $this->fieldValue($entity, self::NAME_FIELD) === $this->fieldValue($original, self::NAME_FIELD)
      && $this->fieldValue($entity, self::STYLE_FIELD) === $this->fieldValue($original, self::STYLE_FIELD)) {
      // Neither the icon nor its style moved, so the subset is unchanged.
      return;
    }

    Cache::invalidateTags(['library_info']);
  }

  /**
   * Implements hook_paragraph_delete().
   */
  #[Hook('paragraph_delete')]
  public function paragraphDelete(EntityInterface $entity): void {
    if ($this->isIcon($entity)) {
      Cache::invalidateTags(['library_info']);
    }
  }

  /**
   * Is this an icon paragraph?
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity from a hook callback.
   *
   * @return bool
   *   TRUE for an `icon_bundle` paragraph.
   *
   * @phpstan-assert-if-true \Drupal\paragraphs\ParagraphInterface $entity
   */
  private function isIcon(EntityInterface $entity): bool {
    return $entity instanceof ParagraphInterface && $entity->bundle() === self::BUNDLE;
  }

  /**
   * One field's value as a string, or '' when absent or empty.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph to read.
   * @param string $field
   *   Field machine name.
   *
   * @return string
   *   Trimmed value, or ''.
   */
  private function fieldValue(ParagraphInterface $paragraph, string $field): string {
    if (!$paragraph->hasField($field) || $paragraph->get($field)->isEmpty()) {
      return '';
    }

    // isEmpty() above makes first() non-NULL in practice, but the two calls
    // are separate reads and the return type permits NULL. Calling getString()
    // on NULL here would fatal on every save of an icon paragraph.
    $item = $paragraph->get($field)->first();

    return $item === NULL ? '' : trim((string) $item->getString());
  }

}
