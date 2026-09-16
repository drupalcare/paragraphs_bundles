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
  ) {}

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
