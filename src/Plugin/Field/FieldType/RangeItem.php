<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Field type for an opacity slider 0..100.
 *
 * Plugin id `paragraphs_bundles_range`.
 */
#[FieldType(
  id: 'paragraphs_bundles_range',
  label: new TranslatableMarkup('BG Opacity Range'),
  description: new TranslatableMarkup('Background Opacity Range.'),
  default_widget: 'range_number_widget',
  default_formatter: 'range_number_formatter',
  module: 'paragraphs_bundles',
)]
final class RangeItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   *
   * @return array<string, mixed>
   *   Field-storage schema.
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
   */
  public function isEmpty(): bool {
    $value = $this->get('value')->getValue();
    return $value === NULL || $value === '';
  }

  /**
   * {@inheritdoc}
   *
   * @return array<string, \Drupal\Core\TypedData\DataDefinitionInterface>
   *   Property definitions.
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'value' => DataDefinition::create('integer')->setLabel(new TranslatableMarkup('Opacity')),
    ];
  }

}
