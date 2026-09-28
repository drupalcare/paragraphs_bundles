<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
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
   * @param \Drupal\paragraphs_bundles_ai\Service\PictureShapesInterface $shapes
   *   Says what size each picture field is shown at, so the placeholder and
   *   the note can say what to replace it with.
   */
  public function __construct(
    private readonly BundleProfilerInterface $profiler,
    private readonly FieldDrafterInterface $drafter,
    private readonly SectionWriterInterface $writer,
    private readonly TextFormatsInterface $formats,
    private readonly DefaultImageInterface $images,
    private readonly PictureShapesInterface $shapes,
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

        // A slide's Image or Hero is where most pictures actually are.
        // Guarded rather than `?? []`: this loop writes back by reference, and
        // a reference into a temporary array would drop every picture it set.
        if (!isset($plan['children'])) {
          continue;
        }

        foreach ($plan['children'] as &$innerPlans) {
          foreach ($innerPlans as &$innerPlan) {
            $innerProfile = $innerPlan['bundle'] === '' ? NULL : $this->profiler->profile($innerPlan['bundle']);

            if ($innerProfile === NULL) {
              continue;
            }

            foreach ($this->fillPictures($innerProfile, $innerPlan['values']) as $name => $label) {
              $pictures[$name] = $label;
            }
          }
        }

        unset($innerPlans, $innerPlan);
      }
    }

    // PHP keeps the last reference of a foreach alive afterwards, and the next
    // write to either name would land inside the array. Unset both.
    unset($plans, $plan);

    $parentValues += $styling;

    // BUILT WHOLE, CHECKED WHOLE, THEN SAVED — maintainer, 2026-09-28: a
    // section that cannot be saved again "will not save and they will think
    // the bundle is broken". Measured the same day: 22 sections written by
    // this tool failed the edit form's validation (empty required slots, links
    // with no address). Nothing is written now unless the whole section — and
    // every section inside it — passes the same validation the edit form runs.
    $noAddress = [];
    $section = $this->assemble($bundle, $parentValues, $plansBySlot, $noAddress);
    $unsaveable = $this->violations($section);

    if ($unsaveable !== []) {
      return CreationResult::refused($unsaveable);
    }

    $result = $this->writer->attach(
      $contentType,
      $field,
      $bundle,
      1,
      fn(): array => [$this->save($section)],
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

      foreach ($plans as $plan) {
        foreach ($plan['children'] ?? [] as $innerPlans) {
          $childCount += count($innerPlans);
        }
      }
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
      $notes[] = $this->t('No picture can be written, so a placeholder was put in @fields, drawn at the size each one is shown. Open the section and replace each with your own picture, at least that size.', [
        '@fields' => implode(', ', array_unique(array_values($pictures))),
      ]);
    }

    // OPTION 1 FOR LINKS — maintainer, 2026-09-28. A web address cannot be
    // written, so each required link is saved as "no link" (valid, and it
    // renders as its text) and the person is told to add the address.
    if ($noAddress !== []) {
      $notes[] = $this->formatPlural(
        count($noAddress),
        'No web address can be written, so 1 link was saved without one and shows as plain text. Open the section and add its address.',
        'No web address can be written, so @count links were saved without one and show as plain text. Open the section and add an address to each.',
      );
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
   *   Field machine name => label and size, for each field that received a
   *   picture: `"Image" (1700 x 600)`, or `"Image" (any size)`.
   */
  private function fillPictures(BundleProfile $profile, array &$values): array {
    $filled = [];

    foreach ($profile->fields as $name => $fact) {
      if (!$fact->isPicture() || !$fact->required || isset($values[$name])) {
        continue;
      }

      $shape = $this->shapes->shapeFor($profile->bundle, $name);
      $value = $this->images->valueFor($fact, $shape);

      // NULL is a real answer: no Media module, no image media type, or the
      // file could not be written. The section is still created, and the
      // "you must choose" note below still names the field.
      if ($value === NULL) {
        continue;
      }

      $values[$name] = $value;
      $filled[$name] = $shape === NULL
        ? (string) $this->t('"@label" (any size)', ['@label' => $fact->label])
        : (string) $this->t('"@label" (@width x @height)', [
          '@label' => $fact->label,
          '@width' => $shape[0],
          '@height' => $shape[1],
        ]);
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
      // whatever a model answered — so $value can be an array or an object,
      // and the allowed-values branch below casts it with `(string) $value`.
      // The guard that exists to catch exactly that case was ten lines further
      // down, so it could only run on values that had already survived the
      // cast. It was written; it was just in the wrong order.
      //
      // ⚠ CORRECTED 2026-09-24, and the correction is the point. This comment
      // first called that cast "a FATAL" for an array. Measured on PHP 8.5.10:
      // it is not. An array casts to the literal string "Array" with nothing
      // but a warning, and only an object without __toString() throws. So the
      // array arm is the QUIETER one and the worse one — "Array" is written
      // into somebody's section as though it were the copy they asked for.
      // is_scalar() is FALSE for both, so one guard still catches both.
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
   * @param bool $allowInner
   *   Whether a child may carry sections of its own. TRUE for the parent's
   *   children, FALSE one level down.
   *
   * @return array<string, list<array{bundle: string, values: array<string, mixed>, children?: array<string, list<array{bundle: string, values: array<string, mixed>}>>}>>
   *   Field machine name => the checked children for that field.
   */
  private function planSlots(
    BundleProfile $profile,
    array $children,
    ?string $childField,
    array &$problems,
    array &$notes,
    bool $allowInner = TRUE,
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

        $plans[] = $this->planChild($profile, $target, $child, $index, $problems, $notes, $allowInner);
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
        $out[$slot][] = $this->planChild($profile, $slot, $child, $position, $problems, $notes, $allowInner);
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
   * @param bool $allowInner
   *   Whether this child may carry sections of its own.
   *
   * @return array{bundle: string, values: array<string, mixed>, children?: array<string, list<array{bundle: string, values: array<string, mixed>}>>}
   *   What to create.
   */
  private function planChild(
    BundleProfile $parent,
    ?string $target,
    array $child,
    int $index,
    array &$problems,
    array &$notes,
    bool $allowInner = TRUE,
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

    $plan = ['bundle' => $bundle, 'values' => $checked];
    $inner = isset($child['children']) && is_array($child['children']) ? $child['children'] : [];

    if ($inner === []) {
      return $plan;
    }

    // ONE LEVEL, and no further. A slide holds a Simple; a Simple inside a
    // slide inside a slideshow holding more sections is the "one container
    // deep" rule in PB-NESTING-GUIDE.md broken, so it is refused, not built.
    if (!$allowInner) {
      $problems[] = $this->t('Child @position holds sections of its own, but it is already inside another section. Only one level inside a section is built.', [
        '@position' => $position,
      ]);

      return $plan;
    }

    $innerProblems = [];
    $bySlot = $this->planSlots($profile, $inner, NULL, $innerProblems, $notes, FALSE);

    foreach ($innerProblems as $problem) {
      $problems[] = $this->t('Child @position: @problem', [
        '@position' => $position,
        '@problem' => $problem,
      ]);
    }

    if ($bySlot !== []) {
      $plan['children'] = $bySlot;
    }

    return $plan;
  }

  /**
   * Builds the parent and its children, one level down, WITHOUT saving.
   *
   * The children are set as entity objects; entity_reference_revisions saves
   * a new referenced entity when its host is saved, so one save of the parent
   * writes the whole section — and nothing is written before it is checked.
   *
   * @param string $bundle
   *   Parent bundle.
   * @param array<string, mixed> $values
   *   Checked parent values.
   * @param array<string, list<array{bundle: string, values: array<string, mixed>, children?: array<string, list<array{bundle: string, values: array<string, mixed>}>>}>> $bySlot
   *   Checked children, keyed by the field each group belongs in, each with
   *   its own children one level down. Empty when the section holds nothing.
   * @param list<string> $noAddress
   *   Collects the labels of required links saved without an address.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   The unsaved section.
   */
  private function assemble(string $bundle, array $values, array $bySlot, array &$noAddress): Paragraph {
    $parent = $this->make($bundle, $values, $noAddress);

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

        $paragraph = $this->make($child['bundle'], $child['values'], $noAddress);

        foreach ($child['children'] ?? [] as $innerSlot => $innerChildren) {
          $innerItems = [];

          foreach ($innerChildren as $innerChild) {
            if ($innerChild['bundle'] !== '') {
              $innerItems[] = $this->make($innerChild['bundle'], $innerChild['values'], $noAddress);
            }
          }

          if ($innerItems !== []) {
            $paragraph->set($innerSlot, $innerItems);
          }
        }

        $items[] = $paragraph;
      }

      if ($items !== []) {
        $parent->set($slot, $items);
      }
    }

    return $parent;
  }

  /**
   * What would stop this section being saved again from the edit form.
   *
   * The section and every section inside it are validated as the edit form
   * validates them. A required slot left empty, or any other constraint, is a
   * refusal here rather than a page that later refuses to save.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   The unsaved section, or one inside it.
   *
   * @return list<string>
   *   Plain sentences, or an empty list when it can be saved.
   */
  private function violations(Paragraph $paragraph): array {
    $out = [];
    $section = (string) $paragraph->getParagraphType()->label();

    foreach ($paragraph->validate() as $violation) {
      $name = explode('.', $violation->getPropertyPath())[0];
      $definition = $paragraph->hasField($name) ? $paragraph->getFieldDefinition($name) : NULL;
      $label = $definition === NULL ? $name : (string) $definition->getLabel();

      // The common case said plainly: a place that must hold at least one
      // section was left empty by the model.
      $out[] = $definition?->getType() === 'entity_reference_revisions' && $paragraph->get($name)->isEmpty()
        ? (string) $this->t('"@section" needs at least one "@field" inside it, and none was written, so nothing was added. Try again, or say how many.', [
          '@section' => $section,
          '@field' => $label,
        ])
        : (string) $this->t('"@section" was not added, because it could not be saved: "@field" — @problem', [
          '@section' => $section,
          '@field' => $label,
          '@problem' => strip_tags((string) $violation->getMessage()),
        ]);
    }

    // referencedEntities() includes the unsaved children assemble() set.
    foreach ($paragraph->getFields() as $items) {
      if (!$items instanceof EntityReferenceFieldItemListInterface || $items->getFieldDefinition()->getType() !== 'entity_reference_revisions') {
        continue;
      }

      foreach ($items->referencedEntities() as $child) {
        if ($child instanceof Paragraph) {
          $out = array_merge($out, $this->violations($child));
        }
      }
    }

    return array_values(array_unique($out));
  }

  /**
   * Saves the checked section; its children are saved with it.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $section
   *   The section assemble() built and violations() passed.
   *
   * @return array{target_id: int|string|null, target_revision_id: int|string|null}
   *   The reference value for the host field.
   */
  private function save(Paragraph $section): array {
    $section->save();

    return [
      'target_id' => $section->id(),
      'target_revision_id' => $section->getRevisionId(),
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
   * @param list<string> $noAddress
   *   Collects the labels of required links saved without an address.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   The unsaved paragraph.
   */
  private function make(string $bundle, array $values, array &$noAddress = []): Paragraph {
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

    // A REQUIRED LINK the model could not address. "route:<nolink>" is a
    // valid value for a link field that takes internal links, renders as its
    // text, and lets the section be saved again from the edit form.
    foreach ($paragraph->getFieldDefinitions() as $name => $definition) {
      if ($definition->getType() === 'link' && $definition->isRequired() && $paragraph->get($name)->isEmpty()) {
        $paragraph->set($name, ['uri' => 'route:<nolink>']);
        $noAddress[] = (string) $definition->getLabel();
      }
    }

    return $paragraph;
  }

}
