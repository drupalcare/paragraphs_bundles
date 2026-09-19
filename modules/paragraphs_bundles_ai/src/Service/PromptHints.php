<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\paragraphs_bundles_ai\Model\BundleProfile;
use Drupal\paragraphs_bundles_ai\Model\FieldFact;

/**
 * Default hint builder.
 *
 * THE SENTENCE HAS THREE CLAUSES, and every one comes from measured field data:
 * the heading and how to steer it, the prose fields by their own labels, and
 * what to say about the children. A bundle missing any of the three simply does
 * not get that clause.
 *
 * THE HEADING CLAUSE CARRIES THE HABIT THAT MATTERS MOST. Measured across 29
 * bundles with a title, saying *"as an h2, centred"* in the same sentence set
 * the heading level and the alignment correctly every time, because those are
 * real list fields and the model is handed their real keys. That is the single
 * most useful thing a person can be taught here, and a placeholder is where
 * they will actually read it.
 *
 * ALIGNMENT AND HEADING LEVEL ARE NOT LISTED AS FIELDS, though both are
 * technically writable. Nobody describes "Title tag" in prose; they say "as an
 * h2". Listing them would make the sentence longer and less useful at once.
 */
final class PromptHints implements PromptHintsInterface {

  use StringTranslationTrait;

  /**
   * Field types a person fills with actual prose.
   *
   * NOT `list_string`, which `FieldFact::isWords()` includes. That method
   * answers "can this be filled by writing", which is the right question for
   * the drafter and the wrong one here: a list field is a choice, and a
   * placeholder that told somebody to write a sentence for "Title alignment"
   * would be instructing them to do something the field cannot hold.
   */
  private const PROSE = ['string', 'string_long', 'text', 'text_long'];

  /**
   * Constructs the hint builder.
   *
   * @param \Drupal\paragraphs_bundles_ai\Service\BundleProfilerInterface $profiler
   *   The one owner of what a bundle is made of.
   */
  public function __construct(
    private readonly BundleProfilerInterface $profiler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function forBundles(array $bundles): array {
    $out = [];

    foreach ($bundles as $bundle) {
      $out[(string) $bundle] = $this->forBundle((string) $bundle);
    }

    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function forBundle(string $bundle): string {
    $profile = $this->profiler->profile(trim($bundle));

    if ($profile === NULL) {
      return '';
    }

    $clauses = [];

    if ($profile->titleField !== NULL) {
      // FOUR WHOLE SENTENCES RATHER THAN A STEM PLUS TWO SUFFIXES, and that is
      // the translation fix rather than a style preference. The old version
      // built this by appending ' as an h2' and ', centred' to a stem, so a
      // translator was handed three fragments and no way to reorder them —
      // and in a language that puts the qualifier first, no correct answer
      // exists. Each combination is now one string a translator can rewrite
      // whole. There are only four because there are only two switches.
      $h2 = $this->offers($profile, 'h2');
      $centred = $this->offers($profile, 'center');

      // Only offered when the bundle really has those choices. Telling somebody
      // to say "as an h2" on a section with no heading-level field would be
      // advice that silently does nothing.
      if ($h2 && $centred) {
        $clauses[] = (string) $this->t('Heading "…" as an h2, centred');
      }
      elseif ($h2) {
        $clauses[] = (string) $this->t('Heading "…" as an h2');
      }
      elseif ($centred) {
        $clauses[] = (string) $this->t('Heading "…", centred');
      }
      else {
        $clauses[] = (string) $this->t('Heading "…"');
      }
    }

    $settable = $this->settable($profile);

    if ($settable !== []) {
      // "Say …" when there is no heading clause in front of it, so a section
      // with only choices reads as a sentence rather than starting "then".
      $clauses[] = $clauses === []
        ? (string) $this->t('Say @fields', ['@fields' => $this->join($settable)])
        : (string) $this->t('then @fields', ['@fields' => $this->join($settable)]);
    }

    $child = $profile->soleChildBundle();

    if ($child !== NULL) {
      $kid = $this->profiler->profile($child);
      $clauses[] = $kid === NULL
        ? (string) $this->t('then say what each one inside it should be')
        : (string) $this->t('then say what goes in each @section', ['@section' => $kid->label]);
    }

    // THREE OF THE 33 SHIPPED PARENTS HAVE NO WRITABLE FIELD AT ALL — measured
    // 2026-09-17. An empty placeholder on those would read as a bug; saying so
    // is the honest answer and stops somebody typing into a box that will
    // discard what they wrote.
    if ($clauses === []) {
      return (string) $this->t('@section takes no words — add it, then fill it in yourself.', [
        '@section' => $profile->label,
      ]);
    }

    return implode(', ', $clauses) . '.';
  }

  /**
   * Every field a person can fill by saying something, by label.
   *
   * PROSE **AND** CHOICES, and the choices were missing until 2026-09-17. A
   * list field is filled by saying the value in plain English — measured and
   * recorded in AI-PROMPTS.md: *"make the large panels 250 and the small ones
   * 150"* set both carousel heights, *"use the outlined icon style"* set the
   * Material Symbols Style, and *"shown as teasers"* resolved to
   * `vm_teaser_text`. Leaving them out hid the one habit that works on those
   * sections — and on 3d_carousel, whose ONLY settable fields are two lists,
   * it produced the outright false *"3D Carousel takes no words"*.
   *
   * Measured across the 33 parents: 3 bundles have such a field —
   * 3d_carousel (2), icon (1) and node_reference (1).
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\BundleProfile $profile
   *   The bundle.
   *
   * @return list<string>
   *   Field labels, the title excluded because it has its own clause.
   */
  private function settable(BundleProfile $profile): array {
    $out = [];

    foreach ($profile->fields as $name => $fact) {
      if ($fact->tab !== 'content' || $fact->holdsSections) {
        continue;
      }

      if ((string) $name === (string) $profile->titleField) {
        continue;
      }

      // "the @label" as one string, not 'the ' glued to a label: the article
      // is not detachable in most languages, and several have none at all.
      if (in_array($fact->type, self::PROSE, TRUE)) {
        $out[] = (string) $this->t('the @label', ['@label' => $fact->label]);

        continue;
      }

      if ($fact->allowedValues !== [] && !$this->isSteering($fact)) {
        $out[] = (string) $this->t('the @label', ['@label' => $fact->label]);
      }
    }

    return $out;
  }

  /**
   * Whether a list field is one the heading clause already covers.
   *
   * The heading level and the alignment are offered as *"as an h2, centred"*,
   * which is how a person actually says them. Naming them again as fields
   * would say the same thing twice, in worse words.
   *
   * MATCHED ON THE REAL KEYS, not on the field name — a site may rename
   * `pb_content_title_tag`, but it cannot put `h2` in a field that does not
   * offer it. Measured across the 33 parents: exactly the two steering fields
   * match, and none of the three genuine choice fields does.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\FieldFact $fact
   *   The field.
   *
   * @return bool
   *   TRUE when the heading clause already speaks for it.
   */
  private function isSteering(FieldFact $fact): bool {
    return in_array('h2', $fact->allowedValues, TRUE)
      || in_array('center', $fact->allowedValues, TRUE);
  }

  /**
   * Joins labels the way a person would say them.
   *
   * @param list<string> $parts
   *   The labels.
   *
   * @return string
   *   "the Body", or "the Body and the Link Title", or "a, b and c".
   */
  private function join(array $parts): string {
    if (count($parts) === 1) {
      return $parts[0];
    }

    $last = array_pop($parts);

    // One string for the join, so a translator owns the conjunction and the
    // spacing around it. Glueing ' and ' between two halves gives them neither.
    return (string) $this->t('@list and @last', [
      '@list' => implode(', ', $parts),
      '@last' => $last,
    ]);
  }

  /**
   * Whether some content field on this bundle offers a given key.
   *
   * READ FROM THE REAL ALLOWED VALUES, never from a field name. A site may
   * rename `pb_content_title_tag`; it cannot rename the `h2` inside it without
   * changing what the field accepts, which is the thing being asserted.
   *
   * @param \Drupal\paragraphs_bundles_ai\Model\BundleProfile $profile
   *   The bundle.
   * @param string $key
   *   The allowed value to look for.
   *
   * @return bool
   *   TRUE when some content field accepts it.
   */
  private function offers(BundleProfile $profile, string $key): bool {
    foreach ($profile->fields as $fact) {
      if ($fact->tab === 'content' && in_array($key, $fact->allowedValues, TRUE)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
