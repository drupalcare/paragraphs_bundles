<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs_bundles_ai\Model\CreationResult;

/**
 * Creates paragraph sections, on a new draft or as a new revision.
 *
 * THE THREE RULES THIS ENFORCES, and none of them is configurable:
 *
 * 1. A new page is created UNPUBLISHED. Nobody should discover a machine's
 *    draft on a live site because a conversation went sideways.
 * 2. An existing page is edited as a NEW REVISION, and the revision id comes
 *    back. An edit that cannot be pointed at cannot be undone.
 * 3. Existing sections are APPENDED TO, never replaced, unless the caller
 *    explicitly said replace. Reaching for someone's existing work is how a
 *    tool does damage that looks like a feature.
 *
 * IT RE-CHECKS THE TARGET ITSELF. `TargetResolver` is called here as well as
 * by `pb_resolve_target`, and that duplication is deliberate: a model can skip
 * the first tool, and a writer that trusted it would write into a field that
 * rejects the bundle, or one the user may not touch. The redundancy IS the
 * safety model.
 */
final class SectionWriter implements SectionWriterInterface {

  /**
   * Constructs the writer.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Loads and saves nodes and paragraphs.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Owns what is created, and is checked for edit access.
   * @param \Drupal\paragraphs_bundles_ai\Service\TargetResolverInterface $resolver
   *   Re-runs every precondition; see the class docblock.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly TargetResolverInterface $resolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function write(
    ?string $contentType,
    ?string $field,
    string $bundle,
    int $count = 1,
    string $title = '',
    ?int $nodeId = NULL,
    bool $replace = FALSE,
  ): CreationResult {
    $count = max(1, $count);

    return $this->attach(
      $contentType,
      $field,
      $bundle,
      $count,
      fn(): array => $this->createEmpty($bundle, $count),
      $title,
      $nodeId,
      $replace,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function attach(
    ?string $contentType,
    ?string $field,
    string $bundle,
    int $count,
    callable $build,
    string $title = '',
    ?int $nodeId = NULL,
    bool $replace = FALSE,
    bool $empty = TRUE,
  ): CreationResult {
    $count = max(1, $count);
    $existing = NULL;

    // An existing node fixes the content type: taking one from the caller as
    // well would let the two disagree, and the node is the authority.
    if ($nodeId !== NULL) {
      $existing = $this->entityTypeManager->getStorage('node')->load($nodeId);

      if (!$existing instanceof NodeInterface) {
        return CreationResult::refused([
          sprintf('There is no node %d on this site.', $nodeId),
        ]);
      }

      if (!$existing->access('update', $this->currentUser)) {
        return CreationResult::refused([
          sprintf('You do not have permission to edit node %d.', $nodeId),
        ]);
      }

      $contentType = $existing->bundle();
    }

    $target = $this->resolver->resolve(
      $contentType,
      $field,
      $bundle,
      $count,
      $existing === NULL ? 'create' : 'update',
    );

    if (!$target->resolved) {
      return CreationResult::refused($target->questions);
    }

    $fieldName = (string) $target->field;
    $notes = [];

    // The resolver compares the number asked for against cardinality, which is
    // the right check for a NEW page and the wrong one for an existing page: a
    // field of 2 already holding 2 has room for none, however small the
    // request. Nothing downstream catches it either — cardinality is enforced
    // by the widget and by validate(), and neither runs on a programmatic
    // save(), so the surplus rows are simply stored and the declared limit
    // quietly stops meaning anything.
    //
    // Replace is exempt: it clears the field first, so a full field can still
    // be replaced, and refusing that would block the one operation that fixes
    // a full field.
    if ($existing !== NULL && !$replace) {
      $limit = $existing->get($fieldName)
        ->getFieldDefinition()
        ->getFieldStorageDefinition()
        ->getCardinality();

      if ($limit !== FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED) {
        $room = $limit - $existing->get($fieldName)->count();

        if ($count > $room) {
          return CreationResult::refused([
            $room <= 0
              ? sprintf(
                'The field %s is full — it holds %d and that is its limit. Replace the sections, or remove one first.',
                $fieldName,
                $limit,
            )
              : sprintf(
                'The field %s holds at most %d, so there is room for %d more, not %d.',
                $fieldName,
                $limit,
                $room,
                $count,
            ),
          ]);
        }
      }
    }

    // A new page needs a title. Refusing is better than inventing one: the
    // title is the thing a person will look for in a content list.
    if ($existing === NULL && trim($title) === '') {
      return CreationResult::refused([
        'A title is required to create a new page. Ask the person what to call it.',
      ]);
    }

    // Built only now, after every precondition has passed. Creating the
    // paragraphs earlier and refusing afterwards would leave them saved,
    // parentless and invisible to the person who asked.
    $paragraphs = $build();

    // Rule 3, stated as code: append unless told otherwise, and say which
    // happened. `replace` is never inferred from context.
    if ($existing !== NULL) {
      // The field item list, not getValue(): TypedDataInterface::getValue()
      // is `mixed`, and appendItem() says what is meant here anyway.
      $items = $existing->get($fieldName);
      $before = $items->count();

      if ($replace) {
        $existing->set($fieldName, $paragraphs);
        $notes[] = sprintf(
          'Replaced %d existing section(s) — the previous revision still has them.',
          $before,
        );
      }
      else {
        foreach ($paragraphs as $item) {
          $items->appendItem($item);
        }
        if ($before > 0) {
          $notes[] = sprintf('Added after the %d section(s) already there.', $before);
        }
      }

      // Rule 2. A new revision, always, whatever the content type's default
      // says — the default is a site preference, and this is a safety rule.
      $existing->setNewRevision(TRUE);
      $existing->setRevisionLogMessage(sprintf(
        'Added %d %s section(s) via Paragraphs Bundles AI.',
        $count,
        $bundle,
      ));
      $existing->setRevisionUserId((int) $this->currentUser->id());
      $existing->save();

      if ($empty) {
        $notes[] = 'The new sections are empty — fill them in on the page.';
      }

      return CreationResult::written(
        (int) $existing->id(),
        (int) $existing->getRevisionId(),
        FALSE,
        $count,
        $notes,
      );
    }

    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => $target->contentType,
      'title' => trim($title),
      // Rule 1. Not configurable, deliberately.
      'status' => 0,
      'uid' => $this->currentUser->id(),
      $fieldName => $paragraphs,
    ]);
    $node->save();

    // Read back rather than assume. `status` is a request, not a guarantee:
    // with content_moderation the workflow's default state recomputes it on
    // save, so a site could publish this page despite the 0 above. Rule 1 is
    // still the intent — but a note that says UNPUBLISHED without checking
    // would be the tool lying about the one thing it promised.
    $notes[] = $node->isPublished()
      ? 'CAREFUL: this site published the page on save, despite being asked not to — check the content type\'s moderation workflow.'
      : 'The page is UNPUBLISHED — nobody can see it but you.';
    if ($empty) {
      $notes[] = 'The new sections are empty — fill them in on the page.';
    }

    return CreationResult::written((int) $node->id(), NULL, TRUE, $count, $notes);
  }

  /**
   * Creates empty paragraphs of one bundle.
   *
   * @param string $bundle
   *   Paragraph bundle.
   * @param int $count
   *   How many.
   *
   * @return list<array{target_id: int|string|null, target_revision_id: int|string|null}>
   *   Reference values for the host field.
   */
  private function createEmpty(string $bundle, int $count): array {
    $paragraphs = [];

    for ($i = 0; $i < $count; $i++) {
      $paragraph = Paragraph::create(['type' => $bundle]);
      $paragraph->save();
      $paragraphs[] = [
        'target_id' => $paragraph->id(),
        'target_revision_id' => $paragraph->getRevisionId(),
      ];
    }

    return $paragraphs;
  }

}
