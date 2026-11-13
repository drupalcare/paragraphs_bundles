<?php

declare(strict_types=1);

namespace Drupal\paragraph_bundle_video\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Blocks saving a video the bundle cannot display.
 */
#[Constraint(
  id: 'PbVideoRequirements',
  label: new TranslatableMarkup('Video requirements', [], ['context' => 'Validation'])
)]
final class PbVideoRequirementsConstraint extends SymfonyConstraint {
}
