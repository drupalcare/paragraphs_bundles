<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\paragraphs_bundles_ai\Model\TargetResolution;

/**
 * Decides where paragraph sections may be created, or what blocks that.
 *
 * THIS IS THE FEATURE, NOT A HELPER. Everything the assistant must not guess
 * lives here: which content type, which field when a type has several, whether
 * that field accepts the bundle asked for, whether the user is allowed. A
 * system prompt asking a model to check those things fails intermittently; a
 * service that cannot return a target without them forces the conversation.
 *
 * It is also what makes the behaviour testable. "The bot felt smart" cannot be
 * asserted; "this input produces exactly this sentence" can.
 *
 * @see \Drupal\paragraphs_bundles_ai\Model\TargetResolution
 * @see docs/PB-AI-BRIEF.md §5
 */
interface TargetResolverInterface {

  /**
   * Resolves a target, or reports every reason it cannot be resolved.
   *
   * Never throws for ordinary missing input: an absent content type is a
   * question, not an exception.
   *
   * @param string|null $contentType
   *   Node bundle machine name, or NULL when the caller did not say.
   * @param string|null $field
   *   Paragraph reference field name, or NULL when the caller did not say.
   * @param string|null $bundle
   *   The paragraph bundle to be created, or NULL when the caller did not say.
   * @param int $count
   *   How many items are to be created. 1 when unstated.
   * @param string $operation
   *   Either 'create' for a new node, or 'update' when editing an existing one.
   *
   * @return \Drupal\paragraphs_bundles_ai\Model\TargetResolution
   *   A usable target, or the questions blocking one.
   */
  public function resolve(
    ?string $contentType,
    ?string $field,
    ?string $bundle,
    int $count = 1,
    string $operation = 'create',
  ): TargetResolution;

  /**
   * Content types that have at least one paragraph reference field.
   *
   * Used to answer "you did not say which content type" with the list that is
   * actually useful, rather than every type on the site.
   *
   * @return array<string, string>
   *   Machine name => human label.
   */
  public function contentTypesWithParagraphFields(): array;

}
