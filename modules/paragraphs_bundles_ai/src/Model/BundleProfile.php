<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Model;

/**
 * What one paragraph bundle is made of, in the terms the assistant needs.
 *
 * WHY THIS EXISTS AS A TYPE. Every tool in this module asks the same four
 * questions of a bundle — which field is its title, which fields hold child
 * sections, which fields the person types into, and which fields control how it
 * looks — and every one of them would otherwise answer by guessing at a name.
 * Measured across the 39 shipped bundles: NINE different field names mean "the
 * title" and three bundles have none, and TWENTY-FIVE different field names
 * hold children across 21 bundles. A tool written against `pb_content_title`
 * works on 32 bundles and fails silently on the rest.
 *
 * THE CONTENT / DISPLAY SPLIT IS THE SITE'S, NOT OURS. PB puts every field in
 * one of two tabs on the edit form, and which tab a field is in is the only
 * honest answer to "is this content or styling" — a site builder may regroup
 * them, and then the site is right and any list we hard-coded is wrong.
 */
final readonly class BundleProfile {

  /**
   * Constructs a profile.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   * @param string $label
   *   Human label, as the site names it.
   * @param bool $hasDisplayTab
   *   FALSE for the section bundles that are styled through their parent —
   *   measured: 4 of the 39 shipped bundles, plus any bundle a site adds
   *   without PB's field groups.
   * @param string|null $titleField
   *   The field a person would call the title, or NULL when the bundle has
   *   none. Three shipped bundles genuinely have none.
   * @param list<string> $contentFields
   *   Machine names of the fields on the Content tab, sorted.
   * @param list<string> $displayFields
   *   Machine names of the fields on the Display tab, sorted.
   * @param array<string, list<string>> $childFields
   *   Field name => the paragraph bundles it accepts. An empty list means the
   *   field accepts ANY bundle — Drupal stores "all allowed" as an empty
   *   setting, not as a list of everything.
   * @param list<string> $unclassified
   *   Fields the form display puts in no group and whose name follows neither
   *   convention. Never empty in silence: a caller shows this rather than
   *   pretending the bundle is fully understood.
   * @param array<string, \Drupal\paragraphs_bundles_ai\Model\FieldFact> $fields
   *   Every non-base field on the bundle, keyed by machine name, with what it
   *   accepts. The lists above say WHERE a field lives; this says what may be
   *   put in it, which is the half a caller needs before writing anything.
   * @param string $description
   *   What this section IS, in the site builder's words — PB ships one for
   *   nearly every bundle. Everything else here describes a bundle's SHAPE; a
   *   shape does not tell a model that a parallax is a banner that scrolls
   *   behind its text, so it filled parallax children with descriptions of the
   *   layout instead of content. Measured 2026-09-17: nothing in this module
   *   read a description at all.
   */
  public function __construct(
    public string $bundle,
    public string $label,
    public bool $hasDisplayTab,
    public ?string $titleField,
    public array $contentFields,
    public array $displayFields,
    public array $childFields,
    public array $unclassified,
    public array $fields = [],
    public string $description = '',
  ) {}

  /**
   * The fields a person must fill in themselves for this section to work.
   *
   * A views section needs a view; a webform section needs a webform; an image
   * section needs an image. None of those can be written, and a model that
   * guessed one would produce a section that saves cleanly and shows nothing.
   *
   * So the section is still created — the words are worth having — and this is
   * what the person is told to finish. Measured on a test site: 4 of the 32
   * sections have exactly one such field, and icon_bundle has six.
   *
   * @return array<string, \Drupal\paragraphs_bundles_ai\Model\FieldFact>
   *   Field machine name => facts, for the fields that need a human.
   */
  public function needsByHand(): array {
    $out = [];

    foreach ($this->fields as $name => $fact) {
      if ($fact->tab === 'content' && $fact->needsChoosing()) {
        $out[$name] = $fact;
      }
    }

    return $out;
  }

  /**
   * One field's facts, when the bundle has it.
   *
   * @param string $name
   *   Field machine name.
   *
   * @return \Drupal\paragraphs_bundles_ai\Model\FieldFact|null
   *   The facts, or NULL when this bundle has no such field.
   */
  public function field(string $name): ?FieldFact {
    return $this->fields[$name] ?? NULL;
  }

  /**
   * Whether this bundle can hold child sections.
   *
   * @return bool
   *   TRUE when at least one field references paragraphs.
   */
  public function nests(): bool {
    return $this->childFields !== [];
  }

  /**
   * The one bundle this section's children must be, when there is exactly one.
   *
   * ONE OWNER FOR THIS QUESTION, and it was two. The drafter answered it
   * privately while the form asked a different one — `nests()`, which is only
   * "has a field that holds sections". They disagree on 12 of the 18 nesting
   * bundles PB ships: the form offered "How many", the drafter quietly
   * returned no children, and the section was created empty and reported as a
   * success.
   *
   * NULL is an honest answer in two distinct cases, and a caller must say
   * which rather than treating both as "no children":
   * - more than one field holds sections — two_columns has a left and a right,
   *   so "put three cards in it" has not said where;
   * - the single field accepts ANY bundle, which Drupal stores as an EMPTY
   *   list, so the site itself has never said what kind of child belongs
   *   there. This is the module's own empty-means-all trap, and it accounts
   *   for 6 of the 12.
   *
   * @return string|null
   *   The child bundle, or NULL when it is not a single obvious one.
   */
  public function soleChildBundle(): ?string {
    // ARRAY_VALUES, NOT RESET: reset() moves the array's internal pointer,
    // which PHP counts as modifying the property, and this class is readonly.
    $childFields = array_values($this->childFields);

    if (count($childFields) !== 1) {
      return NULL;
    }

    $accepts = array_values($childFields[0]);

    return count($accepts) === 1 ? (string) $accepts[0] : NULL;
  }

  /**
   * Whether children can actually be written into this section.
   *
   * The question "How many inside it?" is worth asking only when the answer
   * can be acted on. `nests()` is not that question.
   *
   * @return bool
   *   TRUE when the assistant can both place a child and know what it is.
   */
  public function canDraftChildren(): bool {
    return $this->soleChildBundle() !== NULL;
  }

  /**
   * One line describing the bundle to whoever is building with it.
   *
   * @return string
   *   A sentence naming the label, the title field and what it can hold.
   */
  public function line(): string {
    $parts = [];
    $parts[] = sprintf('%d content field%s', count($this->contentFields), count($this->contentFields) === 1 ? '' : 's');

    $parts[] = $this->hasDisplayTab
      ? sprintf('%d display field%s', count($this->displayFields), count($this->displayFields) === 1 ? '' : 's')
      : 'styled through its parent';

    $parts[] = $this->titleField === NULL
      ? 'no title field'
      : sprintf('title in %s', $this->titleField);

    if ($this->nests()) {
      $parts[] = sprintf('holds sections in %s', implode(' and ', array_keys($this->childFields)));
    }

    return sprintf('%s (%s) — %s.', $this->label, $this->bundle, implode(', ', $parts));
  }

}
