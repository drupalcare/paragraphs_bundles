<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs_bundles_ai\Model\BundleProfile;
use Drupal\paragraphs_bundles_ai\Model\CreationResult;

/**
 * Default composer.
 *
 * THE ORDER OF WORK IS THE SAFETY MODEL, and it is the same one SectionWriter
 * uses: every value is checked against the real fields BEFORE a single
 * paragraph is saved. A composer that created the parent, then discovered the
 * third child names a field that does not exist, would leave a half-built
 * section on someone's page — worse than a refusal, because it looks like it
 * worked.
 *
 * SO NOTHING HERE WRITES DIRECTLY TO A NODE. It hands a builder to
 * SectionWriter::attach(), which owns the three rules — unpublished new pages,
 * a new revision on an existing one, appending rather than replacing — and
 * calls the builder only once they all pass.
 *
 * WHY STYLING IS REFUSED RATHER THAN IGNORED. The Display tab is 639 of this
 * module's 825 field instances, and setting colours without checking contrast
 * is how an assistant produces a section nobody can read. Until the contrast
 * checker exists, a styling field named here comes back as a refusal that says
 * so, instead of being silently dropped — which would have the model believing
 * it styled something.
 */
final class SectionComposer implements SectionComposerInterface {

  /**
   * Constructs the composer.
   *
   * @param \Drupal\paragraphs_bundles_ai\Service\BundleProfilerInterface $profiler
   *   Says what each bundle actually holds.
   * @param \Drupal\paragraphs_bundles_ai\Service\FieldDrafterInterface $drafter
   *   Fits text to the real character limits, and reports when it had to.
   * @param \Drupal\paragraphs_bundles_ai\Service\SectionWriterInterface $writer
   *   Owns every precondition and the three writing rules.
   */
  public function __construct(
    private readonly BundleProfilerInterface $profiler,
    private readonly FieldDrafterInterface $drafter,
    private readonly SectionWriterInterface $writer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function compose(
    ?string $contentType,
    ?string $field,
    string $bundle,
    array $values = [],
    array $children = [],
    ?string $childField = NULL,
    string $title = '',
    ?int $nodeId = NULL,
    array $styling = [],
  ): CreationResult {
    $bundle = trim($bundle);
    $profile = $this->profiler->profile($bundle);

    if ($profile === NULL) {
      return CreationResult::refused([
        sprintf(
          'There is no section "%s" on this site. Call pb_list_bundles and use a machine name from that list.',
          $bundle,
        ),
      ]);
    }

    $problems = [];
    $notes = [];
    $parentValues = $this->check($profile, $values, $problems, $notes);

    $target = NULL;

    if ($children !== []) {
      $target = $this->childField($profile, $childField, $problems);
    }

    $childPlans = [];

    foreach (array_values($children) as $index => $child) {
      $childPlans[] = $this->planChild($profile, $target, $child, $index, $problems, $notes);
    }

    if ($problems !== []) {
      return CreationResult::refused($problems);
    }

    // Preset values join the section's own values only here, at the last
    // moment, and never go through check() — that function's job is to judge
    // what a MODEL proposed. These were judged by a person when the preset was
    // written, and by StylePresets against this bundle's real fields.
    $parentValues += $styling;

    $result = $this->writer->attach(
      $contentType,
      $field,
      $bundle,
      1,
      fn(): array => [$this->build($bundle, $parentValues, (string) $target, $childPlans)],
      $title,
      $nodeId,
      FALSE,
      // These sections are not empty — they are the reason this service
      // exists — so the writer must not tell the caller to go and fill them.
      FALSE,
    );

    if (!$result->created) {
      return $result;
    }

    // What was filled, and what was not, said out loud. A composer that
    // reported only success would leave the model claiming a section is
    // finished when half its fields are empty.
    $filled = count($parentValues);
    $notes[] = $filled === 0
      ? 'No fields were filled on the section itself — it is empty.'
      : sprintf('Filled %d field(s) on the section: %s.', $filled, implode(', ', array_keys($parentValues)));

    if ($childPlans !== []) {
      $notes[] = sprintf('Created %d child section(s) in %s.', count($childPlans), (string) $target);
    }

    $notes[] = 'Nothing was published, and nothing that was already on the page was changed.';

    return CreationResult::written(
      (int) $result->nodeId,
      $result->revisionId,
      $result->isNewNode,
      $result->sections,
      array_merge($result->notes, $notes),
    );
  }

  /**
   * Checks values against the real fields, collecting problems and notes.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\BundleProfile $profile
   *   The bundle being filled.
   * @param array<string, mixed> $values
   *   Field name => value, as asked for.
   * @param list<string> $problems
   *   Refusals, appended to.
   * @param list<string> $notes
   *   Things the caller must be told, appended to.
   *
   * @return array<string, mixed>
   *   The values that may actually be written.
   */
  private function check(BundleProfile $profile, array $values, array &$problems, array &$notes): array {
    $out = [];

    foreach ($values as $name => $value) {
      $name = (string) $name;
      $fact = $profile->field($name);

      if ($fact === NULL) {
        $problems[] = sprintf(
          'The section %s has no field "%s". It has: %s.',
          $profile->bundle,
          $name,
          $profile->contentFields === [] ? 'no fillable fields' : implode(', ', $profile->contentFields),
        );

        continue;
      }

      if ($fact->holdsSections) {
        $problems[] = sprintf(
          'The field %s holds child sections, not a value. Pass them as children instead.',
          $name,
        );

        continue;
      }

      if ($fact->tab === 'display') {
        $problems[] = sprintf(
          'The field %s is a styling field, and styling is not supported yet — the contrast checker does not exist, so colours are not set from here.',
          $name,
        );

        continue;
      }

      if ($fact->allowedValues !== [] && !in_array((string) $value, $fact->allowedValues, TRUE)) {
        $problems[] = sprintf(
          'The field %s only accepts: %s. It was given "%s".',
          $name,
          implode(', ', $fact->allowedValues),
          is_scalar($value) ? (string) $value : gettype($value),
        );

        continue;
      }

      if (!is_scalar($value)) {
        $problems[] = sprintf('The value for %s must be text or a number.', $name);

        continue;
      }

      // Text goes through the drafter, which knows the real limit and says so
      // when it had to shorten. Truncating silently is the failure that class
      // exists to prevent.
      $draft = $this->drafter->fit($profile->bundle, $name, (string) $value);

      if ($draft->usable) {
        $out[$name] = $draft->text;

        foreach ($draft->notes as $note) {
          $notes[] = $name . ': ' . $note;
        }

        continue;
      }

      // Not a text field — a number, a boolean, a list key already checked
      // above. The drafter refuses those, and they are written as given.
      $out[$name] = $value;
    }

    return $out;
  }

  /**
   * Decides which field the children go in.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\BundleProfile $profile
   *   The parent bundle.
   * @param string|null $asked
   *   The field the caller named, if any.
   * @param list<string> $problems
   *   Refusals, appended to.
   *
   * @return string|null
   *   The field name, or NULL when it could not be decided.
   */
  private function childField(BundleProfile $profile, ?string $asked, array &$problems): ?string {
    $fields = array_keys($profile->childFields);

    if ($fields === []) {
      $problems[] = sprintf(
        'The section %s cannot hold other sections, so children cannot be added to it.',
        $profile->bundle,
      );

      return NULL;
    }

    if ($asked !== NULL && trim($asked) !== '') {
      $asked = trim($asked);

      if (!isset($profile->childFields[$asked])) {
        $problems[] = sprintf(
          '%s has no child field "%s". It has: %s.',
          $profile->bundle,
          $asked,
          implode(', ', $fields),
        );

        return NULL;
      }

      return $asked;
    }

    if (count($fields) > 1) {
      // two_columns has a left and a right; three_columns adds a middle; modal
      // has a body and a footer. "Put it in the left column" is a real
      // sentence, and picking one here would answer it at random.
      $problems[] = sprintf(
        '%s has %d fields that hold sections: %s. Say which one the children go in.',
        $profile->bundle,
        count($fields),
        implode(', ', $fields),
      );

      return NULL;
    }

    return $fields[0];
  }

  /**
   * Checks one child and returns what to build, or records why not.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\BundleProfile $parent
   *   The parent bundle.
   * @param string|null $target
   *   The child field chosen.
   * @param array<string, mixed> $child
   *   One child, as ['bundle' => string, 'values' => array].
   * @param int $index
   *   Position, for messages a person can follow.
   * @param list<string> $problems
   *   Refusals, appended to.
   * @param list<string> $notes
   *   Notes, appended to.
   *
   * @return array{bundle: string, values: array<string, mixed>}
   *   What to create.
   */
  private function planChild(
    BundleProfile $parent,
    ?string $target,
    array $child,
    int $index,
    array &$problems,
    array &$notes,
  ): array {
    $position = $index + 1;
    $bundle = isset($child['bundle']) && is_string($child['bundle']) ? trim($child['bundle']) : '';

    if ($bundle === '') {
      $problems[] = sprintf('Child %d does not say which section it is.', $position);

      return ['bundle' => '', 'values' => []];
    }

    $profile = $this->profiler->profile($bundle);

    if ($profile === NULL) {
      $problems[] = sprintf('Child %d asks for "%s", which is not a section on this site.', $position, $bundle);

      return ['bundle' => '', 'values' => []];
    }

    if ($target !== NULL) {
      $accepts = $parent->childFields[$target] ?? [];

      // An EMPTY list means any bundle is allowed — Drupal stores "all" as an
      // empty setting, and reading it as "none" would refuse every child on
      // the most permissive fields there are.
      if ($accepts !== [] && !in_array($bundle, $accepts, TRUE)) {
        $problems[] = sprintf(
          'Child %d is a %s, but %s only accepts: %s.',
          $position,
          $bundle,
          $target,
          implode(', ', $accepts),
        );

        return ['bundle' => '', 'values' => []];
      }
    }

    $values = isset($child['values']) && is_array($child['values']) ? $child['values'] : [];
    $childProblems = [];
    $checked = $this->check($profile, $values, $childProblems, $notes);

    foreach ($childProblems as $problem) {
      $problems[] = sprintf('Child %d: %s', $position, $problem);
    }

    return ['bundle' => $bundle, 'values' => $checked];
  }

  /**
   * Builds the parent and its children, saving as it goes.
   *
   * Called by SectionWriter only after every precondition has passed.
   *
   * @param string $bundle
   *   Parent bundle.
   * @param array<string, mixed> $values
   *   Checked parent values.
   * @param string $childField
   *   Field the children go in. Empty when there are none.
   * @param list<array{bundle: string, values: array<string, mixed>}> $children
   *   Checked children.
   *
   * @return array{target_id: int|string|null, target_revision_id: int|string|null}
   *   The reference value for the host field.
   */
  private function build(string $bundle, array $values, string $childField, array $children): array {
    $parent = Paragraph::create(['type' => $bundle] + $values);

    if ($children !== [] && $childField !== '') {
      $items = [];

      foreach ($children as $child) {
        $paragraph = Paragraph::create(['type' => $child['bundle']] + $child['values']);
        $paragraph->save();
        $items[] = [
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ];
      }

      $parent->set($childField, $items);
    }

    $parent->save();

    return [
      'target_id' => $parent->id(),
      'target_revision_id' => $parent->getRevisionId(),
    ];
  }

}
