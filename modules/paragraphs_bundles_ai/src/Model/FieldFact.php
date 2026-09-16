<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Model;

/**
 * One field on a section, described the way a caller has to use it.
 *
 * WHY MORE THAN A NAME. A machine name alone tells a model nothing about what
 * belongs in the field: `pb_content_title_tag` takes one of h1…h6 and
 * `pb_content_title` takes a sentence, and the difference is invisible from the
 * name. A model that guesses either writes something the field rejects or
 * something a visitor reads as nonsense — and the save succeeds both times.
 *
 * ALLOWED VALUES ARE THE HIGH-VALUE PART. A list field accepts nothing else,
 * so handing over the real keys turns a guess into a choice.
 */
final readonly class FieldFact {

  /**
   * Constructs a field fact.
   *
   * @param string $name
   *   Machine name — what a later call must use verbatim.
   * @param string $label
   *   Human label, as the site named it. This is what to say to a person.
   * @param string $type
   *   Field type machine name.
   * @param string $tab
   *   Which tab of the section's edit form this field lives on, read from the
   *   form display: 'content', 'display', or 'unknown'.
   * @param bool $required
   *   TRUE when the section will not save without it.
   * @param int|null $maxLength
   *   Hard character limit, or NULL when the field has none.
   * @param list<string> $allowedValues
   *   The only accepted keys, for a list field. Empty for everything else.
   * @param list<string> $accepts
   *   For a field that holds child sections: the bundles it takes. An EMPTY
   *   list means any bundle — Drupal stores "all allowed" as an empty setting,
   *   never as a list of everything.
   * @param bool $holdsSections
   *   TRUE when this field holds child sections rather than a value.
   */
  public function __construct(
    public string $name,
    public string $label,
    public string $type,
    public string $tab,
    public bool $required,
    public ?int $maxLength,
    public array $allowedValues,
    public array $accepts,
    public bool $holdsSections,
  ) {}

  /**
   * One line describing this field to whoever must fill it.
   *
   * @return string
   *   A sentence naming the machine name, the label and what it accepts.
   */
  public function line(): string {
    $parts = [$this->type];

    if ($this->holdsSections) {
      $parts = ['holds sections'];
      $parts[] = $this->accepts === []
        ? 'any kind'
        : 'only ' . implode(', ', $this->accepts);
    }

    if ($this->allowedValues !== []) {
      $parts[] = 'one of: ' . implode(', ', $this->allowedValues);
    }

    if ($this->maxLength !== NULL) {
      $parts[] = sprintf('at most %d characters', $this->maxLength);
    }

    if ($this->required) {
      $parts[] = 'required';
    }

    return sprintf('%s (%s) — %s', $this->name, $this->label, implode(', ', $parts));
  }

}
