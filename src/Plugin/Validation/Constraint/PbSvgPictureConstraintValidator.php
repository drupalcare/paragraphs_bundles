<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Plugin\Validation\Constraint;

use Drupal\Core\Field\FieldConfigInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_bundles\Hook\PbSvgHook;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates {@see PbSvgPictureConstraint}.
 */
final class PbSvgPictureConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$value instanceof ParagraphInterface) {
      return;
    }
    assert($constraint instanceof PbSvgPictureConstraint);
    foreach ($value->getFieldDefinitions() as $name => $definition) {
      if (!PbSvgHook::isSvgField($definition) || !$definition instanceof FieldConfigInterface) {
        continue;
      }
      if (!$value->get($name)->isEmpty()) {
        return;
      }
      // The bundle module declares which of its fields the SVG stands in for.
      $pictures = $definition->getThirdPartySetting('paragraphs_bundles', 'svg_picture_fields', []);
      $present = array_values(array_filter(is_array($pictures) ? $pictures : [], static fn ($field): bool => is_string($field) && $value->hasField($field)));
      if ($present === []) {
        return;
      }
      $labels = [];
      foreach ($present as $field) {
        if (!$value->get($field)->isEmpty()) {
          return;
        }
        $labels[] = (string) $value->getFieldDefinition($field)?->getLabel();
      }
      // Reported on the first picture field, which is in every shipped form.
      $this->context->buildViolation($constraint->message, ['@fields' => implode(' / ', array_filter($labels))])
        ->atPath($present[0])
        ->addViolation();
      return;
    }
  }

}
