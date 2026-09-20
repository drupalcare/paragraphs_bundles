<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

/**
 * The best-practice brief for a section.
 *
 * ONE FILE HOLDS ALL OF IT, and that is the maintainer's instruction of
 * 2026-09-18: *"we must have one source of truth for everything. so the prompt
 * and best practices they should be in one file easy for me to update, or
 * tweak."* `config/install/paragraphs_bundles_ai.briefs.yml` is that file. A
 * site may override it like any other config; nothing else in this module
 * carries the text.
 *
 * FOUR PARTS, AND THEY ARE SHOWN IN THIS ORDER:
 * - `category` — the group it belongs to, in the maintainer's own grouping: by
 *   what a person is trying to BUILD. An earlier version grouped by nesting
 *   mechanics and was rejected outright — nobody opens that dropdown thinking
 *   "I need one with a child slot".
 * - `good_for` — one line saying when to reach for this section.
 * - `practices` — the advice, ending with the required fields, each marked
 *   (Content) or (Display) so a person knows which tab to open.
 * - `prompt` — an example prompt. **Written by the maintainer, not generated:**
 *   *"before the draft we must agree on words… we need a very good example and
 *   I will write it."*
 *
 * TWO READERS, AND THEY GET DIFFERENT PARTS.
 * - `practices` goes to the panel AND may go to the model. It says what good
 *   looks like for this section, which is what both of them need.
 * - `prompt` goes to the panel ONLY. It is an example for a person to edit, and
 *   a model handed it writes about whatever the example mentions — the bakery
 *   in the example rather than the business in front of them.
 *
 * SIX BUNDLES HAVE NO BRIEF ON PURPOSE — block_content, block, node_reference,
 * views, webform and contact_form. The maintainer: *"Ai can not creat them, you
 * must reference created content from the system in a field."* Their absence is
 * how the dropdown is filtered, so a caller must treat a missing brief as
 * meaningful rather than as a gap to fill.
 */
interface BriefsInterface {

  /**
   * The brief for one bundle.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   *
   * @return array{category: string, good_for: string, practices: string, prompt: string}|null
   *   The brief with every key present, or NULL when the file has no entry for
   *   this bundle — which is ordinary for a bundle a site added itself, and
   *   deliberate for the six a model cannot create.
   */
  public function forBundle(string $bundle): ?array;

  /**
   * The briefs for several bundles, keyed by machine name.
   *
   * Bundles with no entry are left out rather than returned empty, so a caller
   * can tell "no brief written" from "brief written and blank".
   *
   * @param list<string> $bundles
   *   Bundle machine names.
   *
   * @return array<string, array{category: string, good_for: string, practices: string, prompt: string}>
   *   Machine name => brief.
   */
  public function forBundles(array $bundles): array;

  /**
   * Just the practices, for the model.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   *
   * @return string
   *   The practice lines, or an empty string when none are written.
   */
  public function practicesFor(string $bundle): string;

  /**
   * What may go inside each OPEN slot on a bundle.
   *
   * A field that accepts ANY bundle is a technical fact, not a sensible one.
   * The maintainer, 2026-09-19: *"we can really say any bundle can go inside,
   * but in fact not really any bundle because it does not make sense. so we
   * must use logic and think how it will show and what actually the page
   * need."* Drupal stores "accepts everything" as an empty setting, so without
   * this the assistant has no way to tell "anything is allowed" from "nobody
   * has said", and it answered both by building an empty shell.
   *
   * ONLY OPEN SLOTS ARE HERE. Where the field names the bundles it takes, the
   * field decides and nothing is written down — *"if any of the parent has a
   * child bundle, then we must use"*. So an empty return means either the
   * bundle holds nothing, or what it holds was never in question.
   *
   * THE FIRST NAME IS THE DEFAULT, and the rest are what a person may ask for
   * instead. Order is the maintainer's, not alphabetical.
   *
   * @param string $bundle
   *   Paragraph bundle machine name.
   *
   * @return array<string, list<string>>
   *   Child field machine name => paragraph bundle machine names, best first.
   */
  public function nestingFor(string $bundle): array;

}
