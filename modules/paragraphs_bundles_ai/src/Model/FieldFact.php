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
   * The field types a person types words into.
   *
   * ONE LIST, ONE HOME. This was written out separately in the drafter, the
   * composer and the editor, which is three chances to disagree about whether
   * a field can be filled by writing. It is a property of the FIELD, so it
   * lives with the field's other facts.
   */
  public const WORDS = ['string', 'string_long', 'text', 'text_long', 'list_string'];

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
   *   For any REFERENCE field: the bundles it takes. An EMPTY list means any
   *   bundle — Drupal stores "all allowed" as an empty setting, never as a
   *   list of everything.
   *
   *   WIDENED 2026-09-17 from "child sections only" to every reference, rather
   *   than adding a second list beside it. The two would have held the same
   *   fact for a section-holding field and drifted the first time one was
   *   improved. `holdsSections` still says which kind of reference this is.
   * @param bool $holdsSections
   *   TRUE when this field holds child sections rather than a value.
   * @param string $description
   *   The field's own help text, as the site builder wrote it. Empty for most
   *   fields. This is the only thing that says what BELONGS in a field rather
   *   than what shape it takes, so it is what a model needs most and what it
   *   was never given: until 2026-09-17 a property was described to the model
   *   by its label alone, so `pb_content_title` arrived as "Title".
   * @param string $targetType
   *   For a reference field: the ENTITY TYPE it points at — 'media', 'node',
   *   'block_content', 'webform'. Empty for everything else.
   *
   *   WHY THE TYPE AND NOT JUST THE FIELD TYPE. Eight of PB's bundles have a
   *   required `pb_content_image`, and it is an `entity_reference`, exactly
   *   like the required `pb_content_webform`, `pb_content_views` and
   *   `pb_content_drupal_block`. Measured 2026-09-17: 8 required image
   *   references and 11 required non-image ones, indistinguishable by field
   *   type alone. Anything that fills an empty picture has to tell those
   *   apart, and without this it would put a picture in a webform field.
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
    public string $description = '',
    public string $targetType = '',
  ) {}

  /**
   * Whether this field holds a picture, of either shape PB uses.
   *
   * TWO SHAPES, AND A SITE HAS ONLY ONE OF THEM. PB's own bundles reference a
   * media item (`entity_reference` to media), which needs the Media module.
   * This module's `ai_image_bundle` uses a plain `image` field, for sites
   * without it. Both are "a picture", and a caller that handled only one would
   * work on half the sites.
   *
   * @return bool
   *   TRUE for a plain image field or a reference to a media item.
   */
  public function isPicture(): bool {
    return !$this->holdsSections
      && ($this->type === 'image' || $this->targetType === 'media');
  }

  /**
   * Whether this field is filled by writing words into it.
   *
   * @return bool
   *   TRUE for text and for a list of keys; FALSE for a reference, an image,
   *   a number, a colour or a link — the things a model must never invent,
   *   because a wrong one looks exactly like a right one.
   */
  public function isWords(): bool {
    return !$this->holdsSections && in_array($this->type, self::WORDS, TRUE);
  }

  /**
   * Whether a person has to fill this in themselves before it works.
   *
   * @return bool
   *   TRUE for a required field that cannot be filled by writing.
   */
  public function needsChoosing(): bool {
    return $this->required && !$this->isWords() && !$this->holdsSections;
  }

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
