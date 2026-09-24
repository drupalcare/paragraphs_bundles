<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Default reader of the shipped briefs.
 *
 * IT READS CONFIG, WHICH IS THE WHOLE POINT. The text lives in
 * `config/install/paragraphs_bundles_ai.briefs.yml`, so a site can override any
 * of it the way it overrides anything else — and, because the schema types
 * every string as `label` or `text`, `locale` collects it for translation.
 * Going through the config factory rather than reading the YAML by hand is what
 * makes both of those work: the factory applies language overrides, so a French
 * site gets the French practices without this class knowing a language exists.
 *
 * WHY NOT THE PARAGRAPHS TYPE DESCRIPTION, which is what the panel used until
 * 2026-09-19 and what the maintainer reported as a bug — choosing Carousel
 * showed *"Slides that move one at a time, across or down, with arrows or
 * dots."* instead of the brief. The description is one sentence saying what a
 * section IS; it shows in the paragraphs-type admin list, and editing 26 of
 * them means 26 admin screens. A brief is a category, a line about when to
 * reach for it, ten lines of advice and an example prompt. They are different
 * texts with different readers, and collapsing them cost a day.
 */
final class Briefs implements BriefsInterface {

  /**
   * The config object holding every brief.
   */
  private const CONFIG = 'paragraphs_bundles_ai.briefs';

  /**
   * Constructs the reader.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Reads the briefs, with any site override and any translation applied.
   */
  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function forBundle(string $bundle): ?array {
    $all = $this->all();
    $one = $all[trim($bundle)] ?? NULL;

    return is_array($one) ? $this->shape($one) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function forBundles(array $bundles): array {
    $all = $this->all();
    $out = [];

    foreach ($bundles as $bundle) {
      $key = trim((string) $bundle);

      // Left out rather than returned empty, so a caller can tell "no brief
      // written" from "brief written and blank". Six bundles have no brief on
      // purpose — the ones a model cannot create.
      if (isset($all[$key]) && is_array($all[$key])) {
        $out[$key] = $this->shape($all[$key]);
      }
    }

    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function practicesFor(string $bundle): string {
    return $this->forBundle($bundle)['practices'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function nestingFor(string $bundle): array {
    $nesting = $this->configFactory->get(self::CONFIG)->get('nesting');
    $slots = is_array($nesting) ? ($nesting[trim($bundle)] ?? NULL) : NULL;

    if (!is_array($slots)) {
      return [];
    }

    $out = [];

    foreach ($slots as $field => $bundles) {
      if (!is_array($bundles)) {
        continue;
      }

      $clean = [];

      foreach ($bundles as $child) {
        $child = trim((string) $child);

        if ($child !== '') {
          $clean[] = $child;
        }
      }

      // A slot listed with an empty list says nothing, and returning it would
      // read to a caller as "this slot is settled, put nothing in it".
      if ($clean !== []) {
        $out[(string) $field] = $clean;
      }
    }

    return $out;
  }

  /**
   * Every brief, as config has them.
   *
   * @return array<string, mixed>
   *   Bundle machine name => brief, or an empty array when the config is
   *   missing entirely, which is what a site that deleted it looks like.
   */
  private function all(): array {
    $briefs = $this->configFactory->get(self::CONFIG)->get('briefs');

    return is_array($briefs) ? $briefs : [];
  }

  /**
   * One brief with every key present.
   *
   * A caller should never have to ask whether a key exists. A brief with no
   * prompt yet — which is all of them until the maintainer writes them — comes
   * back with an empty string rather than a missing key.
   *
   * @param array<string, mixed> $brief
   *   The brief as config has it.
   *
   * @return array{category: string, good_for: string, practices: string, prompts: list<array{name: string, text: string}>}
   *   The brief, complete. `prompts` is a LIST, never a single string: the file
   *   accepts both `prompt:` and `prompts:` and prompts() collapses them into
   *   one shape so no caller has to ask which the maintainer used. This
   *   docblock said `prompt: string` and was the only place still describing
   *   the pre-collapse shape — BriefsInterface already declared the list, so
   *   the mismatch surfaced as three PHPStan errors that were all this one
   *   line.
   */
  private function shape(array $brief): array {
    return [
      'category' => self::text($brief['category'] ?? NULL),
      'good_for' => self::text($brief['good_for'] ?? NULL),
      'practices' => self::text($brief['practices'] ?? NULL),
      'prompts' => $this->prompts($brief),
    ];
  }

  /**
   * One config value as trimmed text, whatever the YAML made of it.
   *
   * A brief arrives as `array<string, mixed>` because it comes from config, so
   * every read is `mixed`. A bare `(string)` cast on that is fine until the day
   * a maintainer writes a list where a sentence was expected: casting an array
   * to string is a fatal, and casting an object without __toString() is too.
   * Anything that is not scalar becomes '' here, which is the same answer a
   * missing key already gives.
   *
   * @param mixed $value
   *   The raw config value.
   *
   * @return string
   *   Trimmed text, or '' when there is nothing usable.
   */
  private static function text(mixed $value): string {
    return is_scalar($value) ? trim((string) $value) : '';
  }

  /**
   * The example prompts, however they were written.
   *
   * TWO FORMS COLLAPSE INTO ONE LIST HERE, so that no caller has to ask which
   * one the maintainer used. `prompt:` is a single example; `prompts:` is a
   * named set for a section that reads differently depending on what goes
   * inside it — a slideshow of text is not a slideshow of images. A bundle may
   * write either, and a bundle that writes neither gets an empty list rather
   * than a blank entry, so "none written yet" stays distinguishable from
   * "written and empty".
   *
   * THE ORDER IS THE FILE'S ORDER. Whatever the maintainer put first is what
   * the panel offers first, because that is the one decision this service must
   * not take away from the text.
   *
   * @param array<string, mixed> $brief
   *   The brief as config has it.
   *
   * @return list<array{name: string, text: string}>
   *   Each prompt with the label to show above it. A single `prompt:` has no
   *   name of its own and carries an empty one.
   */
  private function prompts(array $brief): array {
    $out = [];

    $single = self::text($brief['prompt'] ?? NULL);

    if ($single !== '') {
      $out[] = ['name' => '', 'text' => $single];
    }

    $named = $brief['prompts'] ?? NULL;

    if (!is_array($named)) {
      return $out;
    }

    foreach ($named as $name => $text) {
      $text = trim((string) $text);

      if ($text !== '') {
        $out[] = ['name' => trim((string) $name), 'text' => $text];
      }
    }

    return $out;
  }

}
