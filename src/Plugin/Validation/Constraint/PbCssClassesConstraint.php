<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Tells an author which of their CSS classes will not be used, and why.
 *
 * Without this the field fails silently: a token carrying `<`, `&` or a quote
 * is dropped at render time, and the author sees a class they typed simply
 * not working, with nothing anywhere to say so.
 *
 * Attribute only, no annotation: this module requires `^11.3 || ^12`, and
 * attribute-based constraint discovery has been available since 10.2 - there
 * is no supported major here that still needs the docblock.
 */
#[Constraint(
  id: 'PbCssClasses',
  label: new TranslatableMarkup('Paragraphs Bundles CSS classes', [], ['context' => 'Validation']),
)]
final class PbCssClassesConstraint extends SymfonyConstraint {

  /**
   * Shown when one or more tokens contain a character that is not allowed.
   */
  public string $rejectedMessage = 'These classes cannot be used, because they contain characters that are not allowed in a class attribute: %tokens. Remove or correct them and save again — everything else you typed is fine. A class may use letters, numbers and - _ : / . [ ] ( ) % # , ! @ * + ~ $ but not < > & = \\ ` " \' { } or ;.';

  /**
   * Shown when valid tokens are dropped for exceeding the per-paragraph cap.
   */
  public string $overCapMessage = 'A paragraph can carry at most %max classes. Remove some to save: %tokens.';

}
