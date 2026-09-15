<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\paragraphs_bundles\Service\Display\PbDisplayClassResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Warns about CSS classes that will be dropped, without blocking the save.
 *
 * Deliberately a WARNING in wording but a real violation in mechanism: the
 * form will not submit until the author fixes or removes the offending
 * token. That is the right trade for this field - a silently ignored class
 * is a bug report six months later, and the fix is one keystroke away while
 * the author is still looking at the box.
 *
 * The accept/reject decision is not reimplemented here. It comes from
 * PbDisplayClassResolver, which is also what renders, so a token this
 * validator accepts is exactly a token that gets emitted.
 */
final class PbCssClassesConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(
    private readonly PbDisplayClassResolverInterface $displayClasses,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get(PbDisplayClassResolverInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof PbCssClassesConstraint) {
      return;
    }
    // The constraint is attached to the field item list, so `$value` is the
    // list. An empty one is the default state of this field and is fine.
    if ($value === NULL) {
      return;
    }
    $raw = NULL;
    if (is_object($value) && method_exists($value, 'getValue')) {
      $items = $value->getValue();
      $raw = is_array($items) && isset($items[0]['value']) ? $items[0]['value'] : NULL;
    }
    elseif (is_string($value)) {
      $raw = $value;
    }
    if (!is_string($raw) || trim($raw) === '') {
      return;
    }

    $filtered = $this->displayClasses->filterClassTokens($raw);

    if ($filtered['rejected'] !== []) {
      $this->context->addViolation($constraint->rejectedMessage, [
        '%tokens' => implode(' ', $filtered['rejected']),
      ]);
    }
    if ($filtered['over_cap'] !== []) {
      $this->context->addViolation($constraint->overCapMessage, [
        '%max' => count($filtered['accepted']),
        '%tokens' => implode(' ', $filtered['over_cap']),
      ]);
    }
  }

}
