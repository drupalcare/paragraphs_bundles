<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * A paragraph with an SVG artwork field needs an image or an SVG.
 *
 * The picture fields an SVG can replace are optional; this keeps "a picture
 * is required" for forms, JSON:API, REST and any save that validates.
 */
#[Constraint(
  id: 'PbSvgPicture',
  label: new TranslatableMarkup('Image or SVG required', [], ['context' => 'Validation']),
  type: 'entity:paragraph',
)]
final class PbSvgPictureConstraint extends SymfonyConstraint {

  /**
   * The message, with the picture field labels.
   */
  public string $message = 'Add @fields, or choose an SVG.';

}
