<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_patterns;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\paragraphs\Plugin\EntityReferenceSelection\ParagraphSelection;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;

/**
 * Decides what the picker offers on a form, and builds a chosen Pattern.
 *
 * @phpstan-import-type PatternDefinition from \Drupal\paragraphs_bundles_patterns\PatternRepository
 * @phpstan-type PatternCard array{key: string, pattern: PatternDefinition, field: string|null, missing: list<string>}
 */
final class PatternInserter {

  /**
   * The permission that shows the picker.
   */
  public const PERMISSION = 'insert pb patterns';

  public function __construct(
    private readonly PatternRepository $repository,
    private readonly PatternTreeBuilder $treeBuilder,
    private readonly SelectionPluginManagerInterface $selectionManager,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Lists the Paragraphs fields on the form this editor may add to.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $host
   *   The edited entity.
   * @param \Drupal\Core\Entity\Display\EntityFormDisplayInterface $display
   *   The form display.
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The editor.
   *
   * @return array<string, \Drupal\Core\Field\FieldDefinitionInterface>
   *   Field definitions keyed by name, in form order.
   */
  public function fields(ContentEntityInterface $host, EntityFormDisplayInterface $display, array $form, AccountInterface $account): array {
    $fields = [];
    foreach (array_keys($display->getComponents()) as $name) {
      if (!$host->hasField($name) || !$display->getRenderer($name) instanceof ParagraphsWidget) {
        continue;
      }
      // Paragraphs builds add_more only when the field can take another item.
      if (NestedArray::keyExists($form, [$name, 'widget', 'add_more']) && $host->get($name)->access('edit', $account)) {
        $definition = $host->getFieldDefinition($name);
        if ($definition !== NULL) {
          $fields[$name] = $definition;
        }
      }
    }
    return $fields;
  }

  /**
   * Returns every Pattern with whether it can be inserted here.
   *
   * @param array<string, \Drupal\Core\Field\FieldDefinitionInterface> $fields
   *   The Paragraphs fields on the form.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The editor.
   *
   * @return array<string, PatternCard>
   *   Cards keyed by bundle.name. A card with a field name can be inserted.
   */
  public function cards(array $fields, AccountInterface $account): array {
    $allowed = array_map($this->allowedRootTypes(...), $fields);
    $access = $this->entityTypeManager->getAccessControlHandler('paragraph');
    $cards = [];
    foreach ($this->repository->all() as $bundle => $patterns) {
      $bundle = (string) $bundle;
      $target = NULL;
      foreach ($allowed as $name => $types) {
        if (in_array($bundle, $types, TRUE)) {
          $target = (string) $name;
          break;
        }
      }
      foreach ($patterns as $name => $pattern) {
        $missing = $this->missingModules($pattern);
        $mayCreate = $missing === [] && $access->createAccess($bundle, $account);
        $cards[$bundle . '.' . $name] = [
          'key' => $bundle . '.' . $name,
          'pattern' => $pattern,
          'field' => $mayCreate ? $target : NULL,
          'missing' => $missing,
        ];
      }
    }
    return $cards;
  }

  /**
   * Whether a page's fields can take at least one Pattern.
   *
   * @param array<string, PatternCard> $cards
   *   The cards.
   *
   * @return bool
   *   TRUE when the picker has something to offer.
   */
  public function hasInsertable(array $cards): bool {
    foreach ($cards as $card) {
      if ($card['field'] !== NULL) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Builds a Pattern's section for a field.
   *
   * @param PatternDefinition $pattern
   *   The Pattern.
   * @param \Drupal\Core\Entity\ContentEntityInterface $host
   *   The edited entity.
   * @param string $fieldName
   *   The Paragraphs field.
   *
   * @return array{paragraph: \Drupal\paragraphs\ParagraphInterface, messages: list<\Drupal\Core\StringTranslation\TranslatableMarkup>}
   *   The unsaved section and any notes for the editor.
   */
  public function build(array $pattern, ContentEntityInterface $host, string $fieldName): array {
    $built = $this->treeBuilder->build($pattern, $host);
    // As the widget does for a paragraph it creates.
    $built['paragraph']->setParentEntity($host, $fieldName);
    return $built;
  }

  /**
   * Lists the modules to turn on before a Pattern can be used.
   *
   * @param PatternDefinition $pattern
   *   The Pattern.
   *
   * @return list<string>
   *   Module names as people see them; empty when all are on.
   */
  private function missingModules(array $pattern): array {
    $storage = $this->entityTypeManager->getStorage('paragraphs_type');
    $info = $this->repository->bundleInfo();
    $missing = [];
    foreach ($this->bundlesOf($pattern['sample'] + ['bundle' => $pattern['bundle']]) as $bundle) {
      if ($storage->load($bundle) === NULL) {
        $missing[] = $info[$bundle]['module_label'] ?? $bundle;
      }
    }
    return array_values(array_unique($missing));
  }

  /**
   * Lists every paragraph type a sample uses.
   *
   * @param array<string, mixed> $node
   *   The sample.
   *
   * @return list<string>
   *   Paragraph type IDs.
   */
  private function bundlesOf(array $node): array {
    $bundles = is_string($node['bundle'] ?? NULL) ? [$node['bundle']] : [];
    foreach ($node as $value) {
      if (!is_array($value)) {
        continue;
      }
      foreach ($value as $child) {
        if (is_array($child) && isset($child['bundle'])) {
          array_push($bundles, ...$this->bundlesOf($child));
        }
      }
    }
    return $bundles;
  }

  /**
   * Lists the paragraph types a field allows at its top level.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *   A Paragraphs field.
   *
   * @return list<string>
   *   Paragraph type IDs, read the way the Paragraphs widget reads them.
   */
  private function allowedRootTypes(FieldDefinitionInterface $field): array {
    $handler = $this->selectionManager->getSelectionHandler($field);
    if ($handler instanceof ParagraphSelection) {
      return array_map('strval', array_keys($handler->getSortedAllowedTypes()));
    }
    return [];
  }

}
