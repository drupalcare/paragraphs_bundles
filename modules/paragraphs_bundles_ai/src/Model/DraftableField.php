<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Model;

/**
 * One field on a paragraph bundle that a person could write text into.
 *
 * WHY THIS EXISTS AS A TYPE. After `create_sections` the model has empty
 * sections and no idea what they hold — a slideshow section's fields are named
 * by whoever built the site, and no amount of prompting makes a machine guess
 * `pb_slideshow_caption`. Everything drafted before this point was drafted
 * blind: right-sounding text aimed at a field name that may not exist, at a
 * length the field will not take.
 */
final readonly class DraftableField {

  /**
   * Constructs a field description.
   *
   * @param string $name
   *   Machine name — the value a later call has to use verbatim.
   * @param string $label
   *   Human label, as the site builder named it.
   * @param string $type
   *   Field type machine name.
   * @param int|null $maxLength
   *   Hard character limit, or NULL when the field has none. Drupal keeps this
   *   in the STORAGE settings, so it is the same for every bundle using the
   *   field — which is exactly why a draft fitted for one section fits the
   *   same field elsewhere.
   * @param bool $allowsHtml
   *   TRUE for formatted text, FALSE for a plain string. Drafting markup into
   *   a plain field does not fail; it just shows the tags to the visitor.
   * @param bool $required
   *   TRUE when the field must be filled for the section to save.
   */
  public function __construct(
    public string $name,
    public string $label,
    public string $type,
    public ?int $maxLength,
    public bool $allowsHtml,
    public bool $required,
  ) {}

  /**
   * One line describing this field to whoever must draft for it.
   *
   * @return string
   *   A sentence naming the machine name, the limit and the format.
   */
  public function line(): string {
    $parts = [];
    $parts[] = $this->allowsHtml ? 'formatted text' : 'plain text';

    if ($this->maxLength !== NULL) {
      $parts[] = sprintf('at most %d characters', $this->maxLength);
    }

    if ($this->required) {
      $parts[] = 'required';
    }

    return sprintf('%s (%s) — %s', $this->name, $this->label, implode(', ', $parts));
  }

}
