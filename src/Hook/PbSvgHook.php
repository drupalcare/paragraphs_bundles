<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Hook;

use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_bundles\Service\Svg\PbSvgLibrary;

/**
 * SVG artwork fields (pb_*_svg_*): template variable and fixed widget.
 *
 * A field is one of them when its storage names
 * PbSvgLibrary::ALLOWED_VALUES_FUNCTION, so no bundle list is kept here and no
 * bundle module needs code of its own.
 */
final class PbSvgHook {

  public function __construct(
    private readonly PbSvgLibrary $svgLibrary,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Sets `pb_svg` (a render array) when the paragraph has an SVG chosen.
   *
   * Called by PbPreprocessHook::preprocessParagraph(): a module may implement
   * preprocess_paragraph only once. The template decides the order: an image
   * always wins over the SVG.
   *
   * @param array<string, mixed> $variables
   *   Preprocess variables (passed by reference).
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph being rendered.
   */
  public function addSvgVariable(array &$variables, ParagraphInterface $paragraph): void {
    foreach ($paragraph->getFieldDefinitions() as $name => $definition) {
      if (!self::isSvgField($definition)) {
        continue;
      }
      $value = $paragraph->get($name)->getString();
      // A theme change or a cache clear must re-render, whether or not a
      // value is set now.
      $cache = is_array($variables['#cache'] ?? NULL) ? $variables['#cache'] : [];
      $tags = is_array($cache['tags'] ?? NULL) ? $cache['tags'] : [];
      $tags[] = 'config:system.theme';
      $cache['tags'] = $tags;
      $variables['#cache'] = $cache;
      if ($value !== '') {
        $variables['pb_svg'] = $this->svgLibrary->build($value, self::variant($name));
      }
      return;
    }
  }

  /**
   * Implements hook_entity_type_alter().
   *
   * "An image or an SVG": the picture fields an SVG replaces are optional, so
   * the requirement moves to an entity constraint, checked on every save path.
   *
   * @param array<string, \Drupal\Core\Entity\EntityTypeInterface> $entity_types
   *   Entity type definitions, keyed by id.
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    $paragraph = $entity_types['paragraph'] ?? NULL;
    if ($paragraph instanceof EntityTypeInterface) {
      $paragraph->addConstraint('PbSvgPicture');
    }
  }

  /**
   * Implements hook_entity_form_display_alter().
   *
   * The SVG fields always use the picker: it is the only widget that limits
   * the choice to the theme's files with previews, so another widget picked in
   * Manage form display is replaced here.
   *
   * @param \Drupal\Core\Entity\Display\EntityFormDisplayInterface $form_display
   *   The form display.
   * @param array<string, mixed> $context
   *   Context: entity_type, bundle, form_mode.
   */
  #[Hook('entity_form_display_alter')]
  public function entityFormDisplayAlter(EntityFormDisplayInterface $form_display, array $context): void {
    if ($form_display->getTargetEntityTypeId() !== 'paragraph') {
      return;
    }
    foreach ($this->entityFieldManager->getFieldDefinitions('paragraph', $form_display->getTargetBundle()) as $name => $definition) {
      if (!self::isSvgField($definition)) {
        continue;
      }
      $component = $form_display->getComponent($name);
      if (is_array($component) && ($component['type'] ?? '') !== 'pb_svg_picker') {
        $component['type'] = 'pb_svg_picker';
        $component['settings'] = [];
        $form_display->setComponent($name, $component);
      }
    }
  }

  /**
   * TRUE for a pb_*_svg_* field.
   */
  public static function isSvgField(FieldDefinitionInterface $definition): bool {
    return $definition->getType() === 'list_string'
      && $definition->getFieldStorageDefinition()->getSetting('allowed_values_function') === PbSvgLibrary::ALLOWED_VALUES_FUNCTION;
  }

  /**
   * How a field's SVG is shown: from its name, set by the bundle module.
   */
  private static function variant(string $field_name): string {
    if (str_ends_with($field_name, '_svg_icon')) {
      return 'icon';
    }
    if (str_ends_with($field_name, '_svg_background')) {
      return 'background';
    }
    return 'art';
  }

}
