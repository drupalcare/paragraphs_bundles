<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\paragraphs_bundles_ai\Model\ChildSlot;

/**
 * Default slot resolver: the field's own restriction, then the briefs.
 *
 * WHY THIS EXISTS. The `nesting` list in the briefs was written on 2026-09-19
 * to say what belongs in a slot that accepts any section, and nothing read it:
 * `Briefs::nestingFor()` had no caller. Measured 2026-09-27 on sts.local, the
 * model was given only the slide TITLE for a slideshow and only the heading
 * for two columns, a carousel or a grid, so both shipped example prompts
 * described content the tool dropped.
 */
final class ChildSlots implements ChildSlotsInterface {

  /**
   * Constructs the resolver.
   *
   * @param \Drupal\paragraphs_bundles_ai\Service\BundleProfilerInterface $profiler
   *   The one owner of what a bundle is made of.
   * @param \Drupal\paragraphs_bundles_ai\Service\BriefsInterface $briefs
   *   The one owner of what belongs in an open slot.
   */
  public function __construct(
    private readonly BundleProfilerInterface $profiler,
    private readonly BriefsInterface $briefs,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function slotsFor(string $bundle): array {
    $profile = $this->profiler->profile($bundle);

    if ($profile === NULL) {
      return [];
    }

    $nesting = $this->briefs->nestingFor($bundle);
    $slots = [];

    foreach ($profile->childFields as $field => $accepts) {
      $field = (string) $field;
      $accepts = array_values($accepts);

      // THE FIELD DECIDES FIRST. A slot that names one bundle is settled, and
      // the briefs say so themselves: "if any of the parent has a child
      // bundle, then we must use".
      if (count($accepts) === 1) {
        $bundles = $accepts;
      }
      elseif (isset($nesting[$field])) {
        // The briefs' order, narrowed to what the field allows. An EMPTY
        // accepts list means any bundle — Drupal's own "all" — so it narrows
        // nothing.
        $bundles = $accepts === []
          ? $nesting[$field]
          : array_values(array_intersect($nesting[$field], $accepts));

        // A nesting list that the field refuses entirely is a config mistake;
        // the field's own list is still a real answer.
        if ($bundles === []) {
          $bundles = $accepts;
        }
      }
      else {
        $bundles = $accepts;
      }

      // Only bundles this site actually has. A brief can name a sub-module
      // that is not enabled here.
      $bundles = array_values(array_filter(
        $bundles,
        fn(string $child): bool => $this->profiler->profile($child) !== NULL,
      ));

      if ($bundles !== []) {
        $slots[] = new ChildSlot($field, $bundles);
      }
    }

    return $slots;
  }

  /**
   * {@inheritdoc}
   */
  public function countable(string $bundle): bool {
    return count($this->slotsFor($bundle)) === 1;
  }

}
