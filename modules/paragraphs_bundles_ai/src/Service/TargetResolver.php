<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\paragraphs_bundles_ai\Model\TargetResolution;

/**
 * Default target resolver.
 *
 * {@inheritdoc}
 */
final class TargetResolver implements TargetResolverInterface {

  /**
   * Constructs the resolver.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Loads node types and paragraph types.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Reads field definitions per bundle.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   Checked for node access, so the tool cannot exceed what the person
   *   operating it may do by hand.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function resolve(
    ?string $contentType,
    ?string $field,
    ?string $bundle,
    int $count = 1,
    string $operation = 'create',
  ): TargetResolution {
    $questions = [];
    $types = $this->contentTypesWithParagraphFields();

    // 1 · A content type was named, and it exists.
    if ($contentType === NULL || trim($contentType) === '') {
      $questions[] = $types === []
        ? 'No content type on this site has a paragraph field, so there is nowhere to add sections.'
        : sprintf(
          'Which content type? These have paragraph fields: %s.',
          $this->humanList($types),
        );
      // Without a type nothing else can be checked, so stop here rather than
      // pile on questions that may be irrelevant once it is known.
      return TargetResolution::blocked($questions);
    }

    $contentType = trim($contentType);

    if (!isset($types[$contentType])) {
      $known = $this->entityTypeManager->getStorage('node_type')->load($contentType);
      $questions[] = $known === NULL
        ? sprintf(
          '"%s" is not a content type on this site. These have paragraph fields: %s.',
          $contentType,
          $types === [] ? 'none' : $this->humanList($types),
        )
        // 2 · It exists but has no paragraph field. A different sentence,
        // because the fix is different: this one is a site-building problem,
        // not a typo.
        : sprintf(
          'The content type "%s" has no paragraph field, so sections cannot be added to it. These do: %s.',
          $contentType,
          $types === [] ? 'none' : $this->humanList($types),
        );

      return TargetResolution::blocked($questions);
    }

    // 7 · The user may create, or edit, this content type. Checked before the
    // field questions: telling someone which field to pick and then refusing
    // on permission wastes their time.
    $access = $this->entityTypeManager
      ->getAccessControlHandler('node')
      ->createAccess($contentType, $this->currentUser);

    if ($operation === 'create' && !$access) {
      return TargetResolution::blocked([
        sprintf('You do not have permission to create %s content.', $contentType),
      ]);
    }

    $fields = $this->paragraphFields($contentType);

    // 3 · If there is more than one paragraph field, which one.
    if ($field === NULL || trim($field) === '') {
      if (count($fields) === 1) {
        $field = array_key_first($fields);
      }
      else {
        $questions[] = sprintf(
          'Which field? %s has %d paragraph fields: %s.',
          $contentType,
          count($fields),
          $this->humanList(array_map(
            static fn(FieldDefinitionInterface $d): string => (string) $d->getLabel(),
            $fields,
          )),
        );
      }
    }
    else {
      $field = trim($field);
      if (!isset($fields[$field])) {
        $questions[] = sprintf(
          '"%s" is not a paragraph field on %s. Available: %s.',
          $field,
          $contentType,
          $this->humanList(array_map(
            static fn(FieldDefinitionInterface $d): string => (string) $d->getLabel(),
            $fields,
          )),
        );
      }
    }

    // Everything below needs a known field.
    if ($field === NULL || !isset($fields[$field])) {
      return TargetResolution::blocked($questions);
    }

    $definition = $fields[$field];

    // 6 · A bundle was named, and it exists.
    //
    // The empty case is a question, NOT a skip. Every other precondition can
    // be waived when its input is absent because the site supplies a sensible
    // answer — one paragraph field, one content type. There is no default
    // section, so reporting "ready" without one tells the assistant to go
    // ahead without ever establishing what to build, which is the only thing
    // the person actually asked for.
    if ($bundle === NULL || trim($bundle) === '') {
      $allowed = $this->allowedBundles($definition);
      $questions[] = sprintf(
        'Which section should I create? The field %s accepts: %s.',
        $definition->getLabel(),
        $allowed === [] ? 'nothing' : $this->humanList($allowed),
      );
    }
    else {
      $bundle = trim($bundle);
      $exists = $this->entityTypeManager->getStorage('paragraphs_type')->load($bundle);

      if ($exists === NULL) {
        $questions[] = sprintf(
          'There is no paragraph type "%s" on this site. Its module may not be enabled.',
          $bundle,
        );
      }
      // 4 · That field allows this bundle.
      elseif (!$this->fieldAllowsBundle($definition, $bundle)) {
        $allowed = $this->allowedBundles($definition);
        $questions[] = sprintf(
          'The field %s does not accept "%s". It accepts: %s.',
          $definition->getLabel(),
          $bundle,
          $allowed === [] ? 'nothing' : $this->humanList($allowed),
        );
      }
    }

    // 5 · Cardinality permits the number asked for.
    $cardinality = $definition->getFieldStorageDefinition()->getCardinality();

    if ($cardinality !== FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
      && $count > $cardinality) {
      $questions[] = sprintf(
        'The field %s holds at most %d item%s, but %d were asked for.',
        $definition->getLabel(),
        $cardinality,
        $cardinality === 1 ? '' : 's',
        $count,
      );
    }

    // $bundle is a non-empty trimmed string on every path that reaches here
    // with no questions: an absent one is itself a question, above.
    return $questions === []
      ? TargetResolution::target($contentType, $field, (string) $bundle)
      : TargetResolution::blocked($questions);
  }

  /**
   * {@inheritdoc}
   */
  public function contentTypesWithParagraphFields(): array {
    $out = [];

    foreach ($this->entityTypeManager->getStorage('node_type')->loadMultiple() as $id => $type) {
      if ($this->paragraphFields((string) $id) !== []) {
        $out[(string) $id] = (string) $type->label();
      }
    }

    return $out;
  }

  /**
   * The paragraph reference fields on a node bundle.
   *
   * @param string $contentType
   *   Node bundle machine name.
   *
   * @return array<string, \Drupal\Core\Field\FieldDefinitionInterface>
   *   Field name => definition.
   */
  private function paragraphFields(string $contentType): array {
    $out = [];

    foreach ($this->entityFieldManager->getFieldDefinitions('node', $contentType) as $name => $definition) {
      // Both halves matter. `entity_reference_revisions` is also used for
      // things that are not paragraphs, so the target type has to be checked
      // rather than inferred from the field type.
      if ($definition->getType() !== 'entity_reference_revisions') {
        continue;
      }
      if (($definition->getSettings()['target_type'] ?? NULL) !== 'paragraph') {
        continue;
      }
      $out[(string) $name] = $definition;
    }

    return $out;
  }

  /**
   * Whether a field accepts a paragraph bundle.
   *
   * THE EMPTY-MEANS-ALL TRAP. Drupal stores "any bundle is allowed" as an
   * EMPTY `target_bundles`, not as a list of every bundle. A naive
   * `in_array($bundle, $target_bundles)` therefore rejects everything on a
   * field that actually accepts everything — and measured on sts.local
   * 2026-09-04, all three paragraph fields on that site are exactly that case,
   * so the naive version would have failed on every real input while passing a
   * fixture that set the list explicitly.
   *
   * `negate` inverts the list into an exclusion, which is rare but real.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $definition
   *   The field.
   * @param string $bundle
   *   Paragraph bundle machine name.
   *
   * @return bool
   *   TRUE when the field accepts it.
   */
  private function fieldAllowsBundle(FieldDefinitionInterface $definition, string $bundle): bool {
    $settings = $this->handlerSettings($definition);
    $targets = $settings['target_bundles'] ?? [];

    if (!is_array($targets) || $targets === []) {
      return TRUE;
    }

    $listed = array_key_exists($bundle, $targets) || in_array($bundle, $targets, TRUE);

    return !empty($settings['negate']) ? !$listed : $listed;
  }

  /**
   * A field's handler settings, as a real array.
   *
   * `getSettings()` is `array<string, mixed>`, so every nested read off it is
   * `mixed`. Narrowing once here keeps the callers readable and satisfies
   * PHPStan at level 10, which drupal.org CI runs — level 9 lets the blind
   * indexing through.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $definition
   *   The field.
   *
   * @return array<mixed>
   *   The handler settings, or an empty array when the field has none. The
   *   keys are not asserted: this comes straight out of `getSettings()`, and
   *   claiming `array<string, mixed>` would assert something not checked.
   */
  private function handlerSettings(FieldDefinitionInterface $definition): array {
    $settings = $definition->getSettings()['handler_settings'] ?? [];

    return is_array($settings) ? $settings : [];
  }

  /**
   * The bundles a field accepts, as labels where they can be resolved.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $definition
   *   The field.
   *
   * @return list<string>
   *   Human-readable bundle names.
   */
  private function allowedBundles(FieldDefinitionInterface $definition): array {
    $settings = $this->handlerSettings($definition);
    $targets = $settings['target_bundles'] ?? [];

    if (!is_array($targets)) {
      $targets = [];
    }

    if ($targets === [] || !empty($settings['negate'])) {
      // With no list, or with an exclusion list, enumerating "allowed" would
      // mean listing every paragraph type on the site — long, and not what the
      // caller needs. Name the shape of the rule instead.
      return $targets === []
        ? ['any paragraph type']
        : ['any paragraph type except ' . implode(', ', array_keys($targets))];
    }

    $storage = $this->entityTypeManager->getStorage('paragraphs_type');
    $out = [];

    foreach (array_keys($targets) as $id) {
      $type = $storage->load((string) $id);
      $out[] = $type === NULL ? (string) $id : (string) $type->label();
    }

    return $out;
  }

  /**
   * Joins names into a readable list.
   *
   * @param array<string|int, string> $items
   *   The names.
   *
   * @return string
   *   A comma-separated list with "and" before the last.
   */
  private function humanList(array $items): string {
    $values = array_values($items);
    $last = array_pop($values);

    return $values === [] ? (string) $last : implode(', ', $values) . ' and ' . $last;
  }

}
