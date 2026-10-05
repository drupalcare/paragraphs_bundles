<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_ai\Service;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_bundles\Service\Bundle\BundleSluggerInterface;
use Drupal\paragraphs_bundles\Service\Css\PbDynamicCssBuilder;

/**
 * Default catalogue.
 *
 * THE DOM ID IS NOT INVENTED HERE. PB already renders every section with
 * `pb-{slug}-{id}`, built by `PbDynamicCssBuilder::outerElementId()` from the
 * slug that `BundleSlugger` produces, and its own dynamic CSS targets that
 * same id. Both are called here rather than reproduced, so the label in the
 * dropdown, the note that appears on hover and the stylesheet can never drift
 * apart — they are one string with one owner.
 */
final class SectionCatalog implements SectionCatalogInterface {

  /**
   * Constructs the catalogue.
   *
   * @param \Drupal\paragraphs_bundles_ai\Service\BundleProfilerInterface $profiler
   *   Says which bundles are parents and which field is a section's title.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Reads the node field's settings.
   * @param \Drupal\paragraphs_bundles\Service\Bundle\BundleSluggerInterface $slugger
   *   Turns a bundle name into the slug PB uses in the DOM id.
   */
  public function __construct(
    private readonly BundleProfilerInterface $profiler,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly BundleSluggerInterface $slugger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function addable(NodeInterface $node, string $field): array {
    $definition = $this->fieldDefinition($node, $field);
    $out = [];

    foreach ($this->profiler->parents() as $id => $profile) {
      if ($definition !== NULL && !$this->fieldAccepts($definition, $id)) {
        continue;
      }

      $out[$id] = $profile->label;
    }

    asort($out);

    return $out;
  }

  /**
   * {@inheritdoc}
   */
  public function onPage(NodeInterface $node, string $field): array {
    if (!$node->hasField($field)) {
      return [];
    }

    $out = [];
    $position = 0;

    foreach ($node->get($field) as $item) {
      $paragraph = $item->entity;

      if (!$paragraph instanceof ParagraphInterface) {
        continue;
      }

      $position++;
      $profile = $this->profiler->profile($paragraph->bundle());

      $out[] = [
        'id' => (int) $paragraph->id(),
        'bundle' => $paragraph->bundle(),
        'label' => $profile === NULL ? $paragraph->bundle() : $profile->label,
        'title' => $this->titleOf($paragraph, $profile?->titleField),
        'dom_id' => PbDynamicCssBuilder::outerElementId(
          $this->slugger->slugify($paragraph->bundle()),
          (int) $paragraph->id(),
        ),
        'position' => $position,
      ];
    }

    return $out;
  }

  /**
   * A section's title, when it has one and it is filled.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The section.
   * @param string|null $titleField
   *   The field its bundle uses as a title, or NULL when it has none. Three
   *   shipped bundles genuinely have none.
   *
   * @return string
   *   The title, trimmed to something a dropdown can show, or an empty string.
   */
  private function titleOf(ParagraphInterface $paragraph, ?string $titleField): string {
    if ($titleField === NULL || !$paragraph->hasField($titleField)) {
      return '';
    }

    $value = trim((string) $paragraph->get($titleField)->getString());

    return mb_strlen($value) > 60 ? mb_substr($value, 0, 57) . '…' : $value;
  }

  /**
   * The node's field definition, when it has that field.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   * @param string $field
   *   Field machine name.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface|null
   *   The definition, or NULL.
   */
  private function fieldDefinition(NodeInterface $node, string $field): ?FieldDefinitionInterface {
    $definitions = $this->entityFieldManager->getFieldDefinitions('node', $node->bundle());

    return $definitions[$field] ?? NULL;
  }

  /**
   * Whether a node field accepts a paragraph bundle.
   *
   * THE EMPTY-MEANS-ALL TRAP, for the third time in this module: Drupal stores
   * "any bundle is allowed" as an EMPTY `target_bundles`, never as a list of
   * everything. Reading it as "nothing allowed" empties the dropdown on the
   * most permissive fields there are — which is every paragraph field on
   * a test site.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $definition
   *   The field.
   * @param string $bundle
   *   Paragraph bundle machine name.
   *
   * @return bool
   *   TRUE when the field accepts it.
   */
  private function fieldAccepts(FieldDefinitionInterface $definition, string $bundle): bool {
    $settings = $definition->getSettings()['handler_settings'] ?? [];

    if (!is_array($settings)) {
      return TRUE;
    }

    $targets = $settings['target_bundles'] ?? [];

    if (!is_array($targets) || $targets === []) {
      return TRUE;
    }

    $listed = array_key_exists($bundle, $targets) || in_array($bundle, $targets, TRUE);

    return !empty($settings['negate']) ? !$listed : $listed;
  }

}
