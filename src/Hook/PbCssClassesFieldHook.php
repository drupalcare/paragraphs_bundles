<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Hook;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\paragraphs_bundles\Service\Display\PbDisplayClassResolver;

/**
 * Attaches the CSS-classes validator wherever that field exists.
 *
 * Done by field NAME rather than by a list of bundles: the field ships on 31
 * parent bundles today, and a list here would be a second place to update
 * every time a bundle is added - the exact drift this module keeps one
 * registry to avoid.
 */
final class PbCssClassesFieldHook {

  /**
   * Implements hook_entity_bundle_field_info_alter().
   *
   * @param array<string, \Drupal\Core\Field\FieldDefinitionInterface> $fields
   *   Field definitions for the bundle, keyed by field name.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type being altered.
   * @param string $bundle
   *   The bundle being altered.
   */
  #[Hook('entity_bundle_field_info_alter')]
  public function entityBundleFieldInfoAlter(array &$fields, $entity_type, string $bundle): void {
    if ($entity_type->id() !== 'paragraph') {
      return;
    }
    $field = $fields[PbDisplayClassResolver::CUSTOM_CLASS_FIELD] ?? NULL;
    if (!$field instanceof FieldDefinitionInterface) {
      return;
    }
    $field->addConstraint('PbCssClasses');
  }

}
