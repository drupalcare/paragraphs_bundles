<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Field type for RGB / HEX colour storage.
 *
 * Plugin id `paragraphs_bundles_rgb`. Identical to v1 in storage shape
 * and semantics; modernised to the Drupal 11 `#[FieldType]` attribute.
 */
#[FieldType(
  id: 'paragraphs_bundles_rgb',
  label: new TranslatableMarkup('Color Picker'),
  description: new TranslatableMarkup('Insert RGB Color in a a text field.'),
  default_widget: 'color_text_widget',
  default_formatter: 'color_text_formatter_hex',
  module: 'paragraphs_bundles',
)]
final class ColorItem extends FieldItemBase {

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
          'type' => 'text',
          'size' => 'tiny',
          'not null' => FALSE,
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
      'value' => DataDefinition::create('string')->setLabel(new TranslatableMarkup('Hex value')),
    ];
  }

  /**
   * {@inheritdoc}
   *
   * Constrains the stored value to a well-formed colour at the FIELD layer.
   *
   * The `color_text_widget` also validates on form submit, but a widget's
   * `#element_validate` only runs for form submissions. Config import,
   * migration, REST/JSON:API and direct entity API writes all bypass it,
   * and the resulting value is emitted into generated CSS. Validating here
   * makes the invariant hold on every write path.
   *
   * Empty values are permitted — these fields are optional; Symfony's Regex
   * constraint skips NULL and the empty string.
   *
   * @return array<int, \Symfony\Component\Validator\Constraint>
   *   Validation constraints for this field item.
   */
  public function getConstraints(): array {
    $constraints = parent::getConstraints();
    $manager = $this->getTypedDataManager()->getValidationConstraintManager();

    $constraints[] = $manager->create('ComplexData', [
      'value' => [
        'Regex' => [
          'pattern' => '/^(?:#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})|\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3})$/',
          'message' => new TranslatableMarkup('%value is not a valid color. Use a hex value such as #1a2b3c or an "r, g, b" triplet.', ['%value' => '{{ value }}']),
        ],
      ],
    ]);

    return $constraints;
  }

}
