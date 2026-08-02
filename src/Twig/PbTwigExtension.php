<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Twig;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\paragraphs_bundles\Service\Css\PbColorNormalizerInterface;
use Drupal\paragraphs_bundles\Service\Display\PbDisplayClassResolverInterface;
use Drupal\paragraphs_bundles\Service\Field\PbImageFieldUrlResolverInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * PB Twig filters — entity field scalars and decode_entities (v1 contract).
 *
 * Use `pb_field_value` instead of `attribute(paragraph, 'field').value`
 * in templates. Dynamic field access via Twig's attribute() can return a
 * field value array instead of a scalar, which breaks trim() and string
 * concatenation in PHP 8.3+.
 */
final class PbTwigExtension extends AbstractExtension {

  public function __construct(
    private readonly PbImageFieldUrlResolverInterface $imageFieldUrls,
    private readonly PbColorNormalizerInterface $colorNormalizer,
    private readonly PbDisplayClassResolverInterface $displayClasses,
  ) {}

  /**
   * {@inheritdoc}
   *
   * @return list<\Twig\TwigFilter>
   *   Filter definitions.
   */
  public function getFilters(): array {
    return [
      new TwigFilter('decode_entities', $this->decodeEntities(...)),
      new TwigFilter('pb_field_value', $this->fieldValue(...)),
      new TwigFilter('pb_image_field_url', $this->imageFieldUrl(...)),
      new TwigFilter('pb_class', $this->className(...)),
      new TwigFilter('pb_css_value', $this->cssValue(...)),
      new TwigFilter('pb_section_bodies', $this->sectionBodies(...)),
    ];
  }

  /**
   * Renders the nested body paragraphs of a section, in the section's language.
   *
   * The tabs, accordion and slideshow bundles each nest a body field inside a
   * section paragraph, and each rendered its bodies by cloning the section's
   * own render array and swapping `#paragraph` for the body entity. That clone
   * carries the section's `#cache` keys, so every body of a section would
   * otherwise share one render-cache entry. The templates dealt with that by
   * emptying `#cache` outright — which also discarded the tags and contexts,
   * leaving a cached page with no way to learn that a body paragraph changed.
   *
   * This keeps the render-cache entry out of the way by dropping only the
   * cache *keys*, and carries the body entity's own cacheability up instead of
   * throwing it away.
   *
   * @param mixed $section
   *   Render array for one section paragraph, as produced by the field
   *   formatter (`content.pb_content_*_section` children).
   * @param string $field
   *   Machine name of the body field on the section paragraph.
   *
   * @return array<int|string, mixed>
   *   Render array of the rendered bodies, or empty when there are none.
   */
  public function sectionBodies(mixed $section, string $field): array {
    if (!is_array($section)) {
      return [];
    }
    $entity = $section['#paragraph'] ?? NULL;
    if (!$entity instanceof FieldableEntityInterface || !$entity->hasField($field)) {
      return [];
    }

    $langcode = $entity->language()->getId();
    $build = [];
    foreach ($entity->get($field) as $delta => $item) {
      $body = $item->entity ?? NULL;
      if (!$body instanceof EntityInterface) {
        continue;
      }
      if ($body instanceof TranslatableInterface && $body->hasTranslation($langcode)) {
        $body = $body->getTranslation($langcode);
      }

      $child = $section;
      $child['#paragraph'] = $body;
      // Without this the body renders from — and pollutes — the section's
      // render-cache entry, which is shared by every body of that section.
      unset($child['#cache']['keys'], $child['#cache']['bin']);

      $cacheability = CacheableMetadata::createFromRenderArray($child);
      $cacheability->addCacheableDependency($body);
      $cacheability->applyTo($child);

      $build[$delta] = $child;
    }

    return $build;
  }

  /**
   * Strips characters that could break out of a CSS declaration.
   *
   * For values interpolated into a `style` attribute from Twig. Twig's HTML
   * autoescape gives no protection there: the HTML parser decodes entities
   * before the CSS parser sees the value, so `(`, `)`, `;` and `:` all
   * survive escaping and can terminate a declaration.
   *
   * Delegates to the same implementation the PHP CSS builders use, so there
   * is one definition of "what is stripped" across PHP and Twig.
   *
   * @param mixed $value
   *   Raw value from a field or template variable.
   *
   * @return string
   *   Value safe to interpolate into a style attribute.
   */
  public function cssValue(mixed $value): string {
    // Allowlist what may be coerced, rather than excluding known-bad types.
    // A blanket (string) cast fatals on an object with no __toString(), and a
    // template can pass anything. Render arrays, booleans and NULL have no
    // meaningful CSS spelling, so emit nothing rather than a coerced artefact
    // — the same fail-closed rule the colour path uses.
    if (is_string($value)) {
      $string = $value;
    }
    elseif (is_int($value) || is_float($value) || $value instanceof \Stringable) {
      $string = (string) $value;
    }
    else {
      return '';
    }

    return $this->colorNormalizer->sanitizeCssValue($string);
  }

  /**
   * Maps a stored display value to its BEM class; unmapped values pass through.
   *
   * @param mixed $value
   *   Stored field value (v1 class token).
   *
   * @return string
   *   BEM class, or the original value when unmapped.
   */
  public function className(mixed $value): string {
    return $this->displayClasses->className($this->scalarToString($value));
  }

  /**
   * Decode HTML entities in a string.
   *
   * @param mixed $string
   *   Source string.
   *
   * @return string
   *   Decoded string, or empty when input is not a string.
   */
  public function decodeEntities(mixed $string): string {
    if (!is_string($string)) {
      return '';
    }
    return html_entity_decode($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  }

  /**
   * Returns a scalar field value from a fieldable entity.
   *
   * @param mixed $entity
   *   Entity (typically a paragraph).
   * @param string $fieldName
   *   Field machine name.
   * @param mixed $default
   *   Value when the field is missing or empty.
   *
   * @return string
   *   Scalar field value as string.
   */
  public function fieldValue(mixed $entity, string $fieldName, mixed $default = ''): string {
    if (!$entity instanceof FieldableEntityInterface) {
      return $this->scalarToString($default);
    }
    if (!$entity->hasField($fieldName)) {
      return $this->scalarToString($default);
    }
    $items = $entity->get($fieldName);
    if ($items->isEmpty()) {
      return $this->scalarToString($default);
    }
    return $this->normalizeScalar($items->value, $default);
  }

  /**
   * Returns an absolute URL for a file-backed image field.
   *
   * @param mixed $entity
   *   Entity (typically a paragraph).
   * @param string $fieldName
   *   Image or media reference field machine name.
   *
   * @return string
   *   Absolute URL, or empty when missing.
   */
  public function imageFieldUrl(mixed $entity, string $fieldName): string {
    if (!$entity instanceof FieldableEntityInterface) {
      return '';
    }
    return $this->imageFieldUrls->absoluteUrl($entity, $fieldName);
  }

  /**
   * Coerces a field value to string, unwrapping nested item arrays.
   *
   * @param mixed $value
   *   Raw field value.
   * @param mixed $default
   *   Fallback when value is not scalar.
   *
   * @return string
   *   Normalized string.
   */
  private function normalizeScalar(mixed $value, mixed $default = ''): string {
    if ($value === NULL) {
      return $this->scalarToString($default);
    }
    if (is_bool($value)) {
      return $value ? '1' : '0';
    }
    if (is_scalar($value)) {
      return (string) $value;
    }
    if (is_array($value)) {
      if (array_key_exists('value', $value)) {
        return $this->normalizeScalar($value['value'], $default);
      }
      $first = reset($value);
      if (is_array($first) && array_key_exists('value', $first)) {
        return $this->normalizeScalar($first['value'], $default);
      }
    }
    return $this->scalarToString($default);
  }

  /**
   * Converts a default argument to string.
   *
   * @param mixed $value
   *   Default value from Twig.
   *
   * @return string
   *   String representation.
   */
  private function scalarToString(mixed $value): string {
    if ($value === NULL) {
      return '';
    }
    if (is_bool($value)) {
      return $value ? '1' : '0';
    }
    if (is_scalar($value)) {
      return (string) $value;
    }
    return '';
  }

}
