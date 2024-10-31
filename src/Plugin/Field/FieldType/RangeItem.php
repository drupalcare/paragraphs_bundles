<?php

namespace Drupal\paragraphs_bundles\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'solo_color_rgb' field type.
 *
 * @FieldType(
 *   id = "paragraphs_bundles_range",
 *   label = @Translation("BG Opacity Range"),
 *   module = "paragraphs_bundles",
 *   description = @Translation("Background Opacity Range."),
 *   default_widget = "range_number_widget",
 *   default_formatter = "range_number_formatter"
 * )
 */
class RangeItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'value' => [
          'type' => 'int',
          'size' => 'normal',
          'not null' => TRUE,
          'settings' => [
            'min' => 0,
            'max' => 100,
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function isEmpty(): bool {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties['value'] = DataDefinition::create('integer')
      ->setLabel(t('Opacity'));

    return $properties;
  }

}
