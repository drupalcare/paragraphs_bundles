<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;
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

  use StringTranslationTrait;

  /**
   * Constructs the composer.
   *
   * @param \Drupal\paragraphs_bundles_ai\Service\BundleProfilerInterface $profiler
   *   Says what each bundle actually holds.
   * @param \Drupal\paragraphs_bundles_ai\Service\FieldDrafterInterface $drafter
   *   Fits text to the real character limits, and reports when it had to.
   * @param \Drupal\paragraphs_bundles_ai\Service\SectionWriterInterface $writer
   *   Owns every precondition and the three writing rules.
   * @param \Drupal\paragraphs_bundles_ai\Service\TextFormatsInterface $formats
   *   Gives a formatted field a format instead of leaving it NULL.
   * @param \Drupal\paragraphs_bundles_ai\Service\DefaultImageInterface $images
   *   Supplies a picture for a required image field, which no model can fill.
   */
  public function __construct(
    private readonly BundleProfilerInterface $profiler,
    private readonly FieldDrafterInterface $drafter,
    private readonly SectionWriterInterface $writer,
    private readonly TextFormatsInterface $formats,
    private readonly DefaultImageInterface $images,
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
        $this->t('There is no section "@section" on this site. Call pb_list_bundles and use a machine name from that list.', [
          '@section' => $bundle,
        ]),
      ]);
    }

    $problems = [];
    $notes = [];
    $parentValues = $this->check($profile, $values, $problems, $notes);

    // WHICH SLOT EACH CHILD GOES IN. A section with one place to put them
    // takes a plain list, which is what every caller sent before this; one
    // with several takes a map keyed by the field, because "a Simple in each
    // column" names two different places and a list cannot.
    $plansBySlot = $this->planSlots($profile, $children, $childField, $problems, $notes);

    if ($problems !== []) {
      return CreationResult::refused($problems);
    }

    // Preset values join the section's own values only here, at the last
    // moment, and never go through check() — that function's job is to judge
    // what a MODEL proposed. These were judged by a person when the preset was
    // written, and by StylePresets against this bundle's real fields.
    //
    // WHAT THE MODEL WROTE IS CAPTURED FIRST. The report below used to count
    // after this merge, so it named the preset's colour keys as fields the
    // model had filled — crediting it with a border colour it never saw.
    $written = array_keys($parentValues);

    // AFTER $written ON PURPOSE, for the same reason the preset values are.
    // A supplied picture is not something the model wrote, and counting it
    // there would credit the model with choosing an image — the exact false
    // credit the line above was written to prevent.
    $pictures = $this->fillPictures($profile, $parentValues);

    // BY REFERENCE, because fillPictures() fills the values in place. Taking
    // a copy here would fill the copy and build the child without its picture.
    foreach ($plansBySlot as &$plans) {
      foreach ($plans as &$plan) {
        if ($plan['bundle'] === '') {
          continue;
        }

        $childProfile = $this->profiler->profile($plan['bundle']);

        if ($childProfile === NULL) {
          continue;
        }

        $filled = $this->fillPictures($childProfile, $plan['values']);

        foreach ($filled as $name => $label) {
          $pictures[$name] = $label;
        }
      }
    }

    // PHP keeps the last reference of a foreach alive afterwards, and the next
    // write to either name would land inside the array. Unset both.
    unset($plans, $plan);

    $parentValues += $styling;

    $result = $this->writer->attach(
      $contentType,
      $field,
      $bundle,
      1,
      fn(): array => [$this->build($bundle, $parentValues, $plansBySlot)],
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
    $notes[] = $written === []
      ? $this->t('No fields were filled on the section itself — it is empty.')
      : $this->formatPlural(
        count($written),
        'Filled 1 field on the section: @fields.',
        'Filled @count fields on the section: @fields.',
        ['@fields' => implode(', ', $written)],
      );

    // Said separately, because a person applying a named look did that
    // themselves and should see it confirmed as their doing, not the model's.
    if ($styling !== []) {
      $notes[] = $this->formatPlural(
        count($styling),
        'Applied the chosen look to 1 styling field.',
        'Applied the chosen look to @count styling fields.',
      );
    }

    $childCount = 0;

    foreach ($plansBySlot as $plans) {
      $childCount += count($plans);
    }

    if ($childCount > 0) {
      $notes[] = $this->formatPlural(
        $childCount,
        'Created 1 child section in @fields.',
        'Created @count child sections in @fields.',
        ['@fields' => implode(', ', array_keys($plansBySlot))],
      );
    }

    // WHAT THE PERSON STILL HAS TO DO, said plainly and by LABEL. A views
    // section without a view renders nothing at all, and a tool that reported
    // only success would leave somebody looking at an empty space wondering
    // what went wrong. Saying it here costs one sentence and saves that.
    if ($pictures !== []) {
      $notes[] = $this->t('No picture can be written, so the placeholder that ships with this module was put in @fields. Open the section and replace it with your own.', [
        '@fields' => implode(' and ', array_map(
          static fn(string $label): string => '"' . $label . '"',
          array_values($pictures),
        )),
      ]);
    }

    $byHand = [];

    foreach ($profile->needsByHand() as $name => $fact) {
      // A field that just received the placeholder is filled. Telling the
      // person it still needs choosing would be the report contradicting the
      // section they are looking at.
      if (isset($pictures[$name])) {
        continue;
      }

      $byHand[] = '"' . $fact->label . '"';
    }

    $warnings = [];

    if ($byHand !== []) {
      // formatPlural, not two sentences glued together: the old version built
      // "a value like that" / "value like those" as a SEPARATE fragment and
      // dropped it into the middle of the sentence, which no translator can
      // agree with a noun they cannot see.
      $warnings[] = $this->formatPlural(
        count($byHand),
        'This section still needs you to choose @fields — a value like that cannot be written, so open the page and set it. Until then the section will not show anything.',
        'This section still needs you to choose @fields — values like those cannot be written, so open the page and set them. Until then the section will not show anything.',
        ['@fields' => implode(' and ', $byHand)],
      );
    }

    $notes[] = $this->t('Nothing was published, and nothing that was already on the page was changed.');

    return CreationResult::written(
      (int) $result->nodeId,
      $result->revisionId,
      $result->isNewNode,
      $result->sections,
      array_merge($result->notes, $notes),
      array_merge($result->warnings, $warnings),
    );
  }

  /**
   * Puts the shipped picture in every required image field left empty.
   *
   * ONLY REQUIRED ONES, AND ONLY EMPTY ONES. An optional image a person did
   * not ask for should stay empty — filling it would put a stand-in on a page
   * nobody wanted one on. A field the caller already supplied is left exactly
   * as it came.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\BundleProfile $profile
   *   The bundle being filled.
   * @param array<string, mixed> $values
   *   Checked values, added to in place.
   *
   * @return array<string, string>
   *   Field machine name => label, for each field that received a picture.
   */
  private function fillPictures(BundleProfile $profile, array &$values): array {
    $filled = [];

    foreach ($profile->fields as $name => $fact) {
      if (!$fact->isPicture() || !$fact->required || isset($values[$name])) {
        continue;
      }

      $value = $this->images->valueFor($fact);

      // NULL is a real answer: no Media module, no image media type, or the
      // file could not be written. The section is still created, and the
      // "you must choose" note below still names the field.
      if ($value === NULL) {
        continue;
      }

      $values[$name] = $value;
      $filled[$name] = $fact->label;
    }

    return $filled;
  }

  /**
   * Checks values against the real fields, collecting problems and notes.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\BundleProfile $profile
   *   The bundle being filled.
   * @param array<string, mixed> $values
   *   Field name => value, as asked for.
   * @param list<string|\Stringable> $problems
   *   Refusals, appended to.
   * @param list<string|\Stringable> $notes
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
        $problems[] = $this->t('The section @section has no field "@field". It has: @fields.', [
          '@section' => $profile->bundle,
          '@field' => $name,
          '@fields' => $profile->contentFields === []
            ? $this->t('no fillable fields')
            : implode(', ', $profile->contentFields),
        ]);

        continue;
      }

      if ($fact->holdsSections) {
        $problems[] = $this->t('The field @field holds child sections, not a value. Pass them as children instead.', [
          '@field' => $name,
        ]);

        continue;
      }

      if ($fact->tab === 'display') {
        $problems[] = $this->t('The field @field is a styling field, and styling is not supported yet — the contrast checker does not exist, so colours are not set from here.', [
          '@field' => $name,
        ]);

        continue;
      }

      // 🚨 THE SCALAR GUARD MOVED ABOVE THE ALLOWED-VALUES CHECK, AND THAT IS A
      // FIX RATHER THAN A TIDY. $values is array<string, mixed> — it carries
      // whatever a model answered — so $value can be an array. The
      // allowed-values branch below casts it with `(string) $value`, and
      // casting an array to string is a FATAL. The guard that exists to catch
      // exactly that case was ten lines further down, so it could only run on
      // values that had already survived the cast. It was written; it was just
      // in the wrong order.
      if (!is_scalar($value)) {
        $problems[] = $this->t('The value for @field must be text or a number.', ['@field' => $name]);

        continue;
      }

      if ($fact->allowedValues !== [] && !in_array((string) $value, $fact->allowedValues, TRUE)) {
        $problems[] = $this->t('The field @field only accepts: @values. It was given "@given".', [
          '@field' => $name,
          '@values' => implode(', ', $fact->allowedValues),
          // Scalar by the guard above, so the gettype() arm is unreachable
          // today. Kept: it costs nothing and keeps the sentence true if the
          // ordering is ever disturbed again.
          '@given' => is_scalar($value) ? (string) $value : gettype($value),
        ]);

        continue;
      }

      // Text goes through the drafter, which knows the real limit and says so
      // when it had to shorten. Truncating silently is the failure that class
      // exists to prevent.
      $draft = $this->drafter->fit($profile->bundle, $name, (string) $value);

      if ($draft->usable) {
        $out[$name] = $draft->text;

        foreach ($draft->notes as $note) {
          $notes[] = $this->t('@field: @note', ['@field' => $name, '@note' => $note]);
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
   * @param list<string|\Stringable> $problems
   *   Refusals, appended to.
   *
   * @return string|null
   *   The field name, or NULL when it could not be decided.
   */
  private function childField(BundleProfile $profile, ?string $asked, array &$problems): ?string {
    $fields = array_keys($profile->childFields);

    if ($fields === []) {
      $problems[] = $this->t('The section @section cannot hold other sections, so children cannot be added to it.', [
        '@section' => $profile->bundle,
      ]);

      return NULL;
    }

    if ($asked !== NULL && trim($asked) !== '') {
      $asked = trim($asked);

      if (!isset($profile->childFields[$asked])) {
        $problems[] = $this->t('@section has no child field "@field". It has: @fields.', [
          '@section' => $profile->bundle,
          '@field' => $asked,
          '@fields' => implode(', ', $fields),
        ]);

        return NULL;
      }

      return $asked;
    }

    if (count($fields) > 1) {
      // two_columns has a left and a right; three_columns adds a middle; modal
      // has a body and a footer. "Put it in the left column" is a real
      // sentence, and picking one here would answer it at random.
      $problems[] = $this->t('@section has @count fields that hold sections: @fields. Say which one the children go in.', [
        '@section' => $profile->bundle,
        '@count' => count($fields),
        '@fields' => implode(', ', $fields),
      ]);

      return NULL;
    }

    return $fields[0];
  }

  /**
   * Works out which slot each child goes in, and checks every one.
   *
   * TWO SHAPES, ONE ANSWER. A list means "all of them in one field", which is
   * every caller that existed before a section could fill several slots. A map
   * keyed by field name says where each group goes, because "a Simple in each
   * column" names two different places.
   *
   * A SLOT IS AN ORDINARY PARAGRAPH FIELD. It takes as many sections as it is
   * given rather than one, so three Simples down the left column is a normal
   * thing for an editor to ask for.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\BundleProfile $profile
   *   The parent bundle.
   * @param list<array<string, mixed>>|array<string, list<array<string, mixed>>> $children
   *   The children, in either shape.
   * @param string|null $childField
   *   The field a LIST goes in. Ignored for the keyed shape, whose keys said.
   * @param list<string|\Stringable> $problems
   *   Refusals, appended to.
   * @param list<string|\Stringable> $notes
   *   Notes, appended to.
   *
   * @return array<string, list<array{bundle: string, values: array<string, mixed>}>>
   *   Field machine name => the checked children for that field.
   */
  private function planSlots(
    BundleProfile $profile,
    array $children,
    ?string $childField,
    array &$problems,
    array &$notes,
  ): array {
    if ($children === []) {
      return [];
    }

    // array_is_list() IS the shape test: a keyed map has field names for keys,
    // a list has 0, 1, 2. Nothing else separates them.
    if (array_is_list($children)) {
      $target = $this->childField($profile, $childField, $problems);

      if ($target === NULL) {
        return [];
      }

      $plans = [];

      foreach ($children as $index => $child) {
        // is_array(), NOT an (array) cast. The cast turns a scalar into a
        // list, which hands planChild() an array<int, mixed> it is not typed
        // for — and, worse, a "child" with no 'bundle' key, which only fails
        // further downstream where the message no longer names the cause.
        if (!is_array($child)) {
          continue;
        }

        $plans[] = $this->planChild($profile, $target, $child, $index, $problems, $notes);
      }

      return [$target => $plans];
    }

    $out = [];
    $position = 0;

    foreach ($children as $slot => $list) {
      $slot = (string) $slot;

      if (!isset($profile->childFields[$slot])) {
        $problems[] = $this->t('@section has no child field "@field". It has: @fields.', [
          '@section' => $profile->bundle,
          '@field' => $slot,
          '@fields' => implode(', ', array_keys($profile->childFields)),
        ]);

        continue;
      }

      foreach ((array) $list as $child) {
        // Same reasoning as the list path above: a non-array child is not a
        // child, and casting one would invent an array with no 'bundle' key.
        if (!is_array($child)) {
          continue;
        }

        // Numbered across every slot, so "Child 3" is the third one asked for
        // rather than the third in whichever column it happened to land in.
        $out[$slot][] = $this->planChild($profile, $slot, $child, $position, $problems, $notes);
        $position++;
      }
    }

    return $out;
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
   * @param list<string|\Stringable> $problems
   *   Refusals, appended to.
   * @param list<string|\Stringable> $notes
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
      $problems[] = $this->t('Child @position does not say which section it is.', ['@position' => $position]);

      return ['bundle' => '', 'values' => []];
    }

    $profile = $this->profiler->profile($bundle);

    if ($profile === NULL) {
      $problems[] = $this->t('Child @position asks for "@section", which is not a section on this site.', [
        '@position' => $position,
        '@section' => $bundle,
      ]);

      return ['bundle' => '', 'values' => []];
    }

    if ($target !== NULL) {
      $accepts = $parent->childFields[$target] ?? [];

      // An EMPTY list means any bundle is allowed — Drupal stores "all" as an
      // empty setting, and reading it as "none" would refuse every child on
      // the most permissive fields there are.
      if ($accepts !== [] && !in_array($bundle, $accepts, TRUE)) {
        $problems[] = $this->t('Child @position is a @section, but @field only accepts: @values.', [
          '@position' => $position,
          '@section' => $bundle,
          '@field' => $target,
          '@values' => implode(', ', $accepts),
        ]);

        return ['bundle' => '', 'values' => []];
      }
    }

    $values = isset($child['values']) && is_array($child['values']) ? $child['values'] : [];
    $childProblems = [];
    $checked = $this->check($profile, $values, $childProblems, $notes);

    foreach ($childProblems as $problem) {
      $problems[] = $this->t('Child @position: @problem', [
        '@position' => $position,
        '@problem' => $problem,
      ]);
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
   * @param array<string, list<array{bundle: string, values: array<string, mixed>}>> $bySlot
   *   Checked children, keyed by the field each group belongs in. Empty when
   *   the section holds nothing.
   *
   * @return array{target_id: int|string|null, target_revision_id: int|string|null}
   *   The reference value for the host field.
   */
  private function build(string $bundle, array $values, array $bySlot): array {
    $parent = $this->make($bundle, $values);

    // ONE FIELD AT A TIME. A flat list arrives here as a single-entry map, so
    // there is one path underneath rather than two that can drift apart.
    foreach ($bySlot as $slot => $children) {
      $items = [];

      foreach ($children as $child) {
        // A child that failed its checks is already a refusal; it must not
        // also become an empty paragraph on the page.
        if ($child['bundle'] === '') {
          continue;
        }

        $paragraph = $this->make($child['bundle'], $child['values']);
        $paragraph->save();
        $items[] = [
          'target_id' => $paragraph->id(),
          'target_revision_id' => $paragraph->getRevisionId(),
        ];
      }

      if ($items !== []) {
        $parent->set($slot, $items);
      }
    }

    $parent->save();

    return [
      'target_id' => $parent->id(),
      'target_revision_id' => $parent->getRevisionId(),
    ];
  }

  /**
   * One paragraph, filled field by field rather than by array union.
   *
   * `Paragraph::create(['type' => $b] + $values)` looks equivalent and is not:
   * a formatted field given a bare string is stored with `format` NULL, and a
   * NULL format renders through the site's fallback — so the body appears as
   * escaped source. Setting each field through the format helper gives it one.
   *
   * @param string $bundle
   *   Paragraph bundle.
   * @param array<string, mixed> $values
   *   Checked values.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   The unsaved paragraph.
   */
  private function make(string $bundle, array $values): Paragraph {
    $paragraph = Paragraph::create(['type' => $bundle]);

    foreach ($values as $name => $value) {
      $name = (string) $name;

      // check() already refused unknown fields, so this only guards the
      // preset values merged in afterwards.
      if (!$paragraph->hasField($name)) {
        continue;
      }

      $paragraph->set($name, is_string($value)
        ? $this->formats->valueFor($paragraph->get($name), $value)
        : $value);
    }

    return $paragraph;
  }

}
