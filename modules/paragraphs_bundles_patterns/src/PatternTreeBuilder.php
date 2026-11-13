<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_patterns;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Builds a Pattern's sample section as unsaved paragraphs.
 *
 * The section carries the Pattern's settings in the normal display fields and
 * its class in the CSS classes field, so the editor can change anything
 * afterwards and nothing is held back at display time.
 *
 * @phpstan-import-type PatternDefinition from \Drupal\paragraphs_bundles_patterns\PatternRepository
 */
final class PatternTreeBuilder {

  /**
   * The field that carries the Pattern's class.
   */
  public const CLASS_FIELD = 'pb_display_css_classes';

  private const TEXT_TYPES = ['text', 'text_long', 'text_with_summary'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly PatternSampleMedia $sampleMedia,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Builds the section.
   *
   * @param PatternDefinition $pattern
   *   The Pattern.
   * @param \Drupal\Core\Entity\ContentEntityInterface $host
   *   The entity the section goes into; gives the language.
   *
   * @return array{paragraph: \Drupal\paragraphs\ParagraphInterface, messages: list<\Drupal\Core\StringTranslation\TranslatableMarkup>}
   *   The unsaved section, and anything the editor should know about it.
   *
   * @throws \InvalidArgumentException
   *   When a paragraph type of the sample does not exist.
   */
  public function build(array $pattern, ContentEntityInterface $host): array {
    $this->sampleMedia->reset();
    $messages = [];
    $root = $this->node($pattern['sample'] + ['bundle' => $pattern['bundle']], $host->language()->getId(), $messages);
    foreach ($pattern['settings'] as $field => $value) {
      if ($root->hasField($field)) {
        $root->set($field, $value);
      }
    }
    if ($root->hasField(self::CLASS_FIELD)) {
      $root->set(self::CLASS_FIELD, 'pb-pattern ' . $pattern['class']);
    }
    return ['paragraph' => $root, 'messages' => array_values(array_unique($messages))];
  }

  /**
   * Builds one paragraph and everything below it.
   *
   * @param array<string, mixed> $node
   *   The sample: bundle, then field values.
   * @param string $langcode
   *   The language.
   * @param list<\Drupal\Core\StringTranslation\TranslatableMarkup> $messages
   *   Collects what the editor should know.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The unsaved paragraph.
   */
  private function node(array $node, string $langcode, array &$messages): ParagraphInterface {
    $bundle = is_string($node['bundle'] ?? NULL) ? $node['bundle'] : '';
    if ($bundle === '' || $this->entityTypeManager->getStorage('paragraphs_type')->load($bundle) === NULL) {
      throw new \InvalidArgumentException(sprintf('The paragraph type "%s" does not exist on this site.', $bundle));
    }
    $paragraph = $this->entityTypeManager->getStorage('paragraph')->create(['type' => $bundle, 'langcode' => $langcode]);
    assert($paragraph instanceof ParagraphInterface);
    foreach ($node as $field => $value) {
      if ($field === 'bundle' || !is_string($field) || !$paragraph->hasField($field)) {
        continue;
      }
      $definition = $paragraph->getFieldDefinition($field);
      if ($definition === NULL) {
        continue;
      }
      $resolved = $this->value($definition, $value, $langcode, $messages);
      if ($resolved !== NULL) {
        $paragraph->set($field, $resolved);
      }
    }
    return $paragraph;
  }

  /**
   * Turns one sample value into a field value.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $definition
   *   The field.
   * @param mixed $value
   *   The sample value.
   * @param string $langcode
   *   The language.
   * @param list<\Drupal\Core\StringTranslation\TranslatableMarkup> $messages
   *   Collects what the editor should know.
   *
   * @return mixed
   *   The field value, or NULL to leave the field as it is.
   */
  private function value(FieldDefinitionInterface $definition, mixed $value, string $langcode, array &$messages): mixed {
    $type = $definition->getType();
    if ($type === 'entity_reference_revisions' && is_array($value)) {
      $children = [];
      foreach ($value as $child) {
        if (is_array($child)) {
          $children[] = ['entity' => $this->node($child, $langcode, $messages)];
        }
      }
      return $children;
    }
    if (is_array($value) && is_string($value['picture'] ?? NULL)) {
      return $this->sampleMedia->fieldValue($definition, $value['picture'], $messages);
    }
    if (in_array($type, self::TEXT_TYPES, TRUE) && is_string($value)) {
      return $this->formattedText($definition, $value);
    }
    return $value;
  }

  /**
   * Gives sample text a format this editor may use.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $definition
   *   The field.
   * @param string $html
   *   The sample HTML.
   *
   * @return array{value: string, format: string}
   *   The field value.
   */
  private function formattedText(FieldDefinitionInterface $definition, string $html): array {
    $allowed = array_filter((array) $definition->getSetting('allowed_formats'));
    $fallback = NULL;
    $formats = $this->entityTypeManager->getStorage('filter_format')->loadByProperties(['status' => TRUE]);
    uasort($formats, static fn ($a, $b): int => $a->get('weight') <=> $b->get('weight'));
    foreach ($formats as $id => $format) {
      if (($allowed !== [] && !in_array($id, $allowed, TRUE)) || !$format->access('use', $this->currentUser)) {
        continue;
      }
      if ($format->get('fallback') !== TRUE) {
        return ['value' => $html, 'format' => (string) $id];
      }
      $fallback = (string) $id;
    }
    // Only the plain text format is left: it prints tags as words, so the
    // sample goes in without them.
    return ['value' => trim(strip_tags($html)), 'format' => $fallback ?? 'plain_text'];
  }

}
