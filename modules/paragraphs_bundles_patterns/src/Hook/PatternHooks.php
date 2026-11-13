<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_patterns\Hook;

use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_bundles_patterns\PatternInserter;
use Drupal\paragraphs_bundles_patterns\PatternPicker;
use Drupal\paragraphs_bundles_patterns\PatternRepository;
use Drupal\paragraphs_bundles_patterns\PatternTreeBuilder;

/**
 * Draws the picker on the node form and loads each Pattern's look.
 */
final class PatternHooks {

  public function __construct(
    private readonly PatternRepository $repository,
    private readonly PatternInserter $inserter,
    private readonly PatternPicker $picker,
    private readonly AccountInterface $currentUser,
  ) {}

  /**
   * Implements hook_library_info_build().
   *
   * @return array<string, array<string, mixed>>
   *   One library per Pattern.
   */
  #[Hook('library_info_build')]
  public function libraryInfoBuild(): array {
    $libraries = [];
    foreach ($this->repository->all() as $bundle => $patterns) {
      foreach ($patterns as $name => $pattern) {
        $library = [
          'version' => 'VERSION',
          'dependencies' => ['paragraphs_bundles_patterns/base'],
        ];
        foreach ($pattern['css'] as $file) {
          $library['css']['theme'][$file] = [];
        }
        foreach ($pattern['js'] as $file) {
          $library['js'][$file] = [];
          $library['dependencies'] = ['paragraphs_bundles_patterns/base', 'core/drupal', 'core/once'];
        }
        $libraries['pattern.' . $bundle . '.' . $name] = $library;
      }
    }
    return $libraries;
  }

  /**
   * Implements hook_theme().
   *
   * @return array<string, array<string, string>>
   *   The radio's theme hook.
   */
  #[Hook('theme')]
  public function theme(): array {
    return ['pb_patterns_radio' => ['render element' => 'element']];
  }

  /**
   * Gives the picker's radio its attributes.
   *
   * @param array<string, mixed> $variables
   *   The template variables.
   */
  #[Hook('preprocess_pb_patterns_radio')]
  public function preprocessRadio(array &$variables): void {
    $element = is_array($variables['element'] ?? NULL) ? $variables['element'] : [];
    $variables['attributes'] = $element['#attributes'] ?? [];
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for node forms.
   *
   * Puts the picker right under the Title.
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  #[Hook('form_node_form_alter')]
  public function nodeFormAlter(array &$form, FormStateInterface $form_state): void {
    if (!$this->currentUser->hasPermission(PatternInserter::PERMISSION)) {
      return;
    }
    $object = $form_state->getFormObject();
    if (!$object instanceof ContentEntityFormInterface) {
      return;
    }
    $node = $object->getEntity();
    if (!$node instanceof NodeInterface) {
      return;
    }
    $display = $object->getFormDisplay($form_state);
    $fields = $this->inserter->fields($node, $display, $form, $this->currentUser);
    if ($fields === []) {
      return;
    }
    $cards = $this->inserter->cards($fields, $this->currentUser);
    if (!$this->inserter->hasInsertable($cards)) {
      return;
    }
    $form[PatternPicker::KEY] = $this->picker->build($cards, $form_state);
    // Just under the Title; the APEX picker, when present, sits between them.
    $form[PatternPicker::KEY]['#weight'] = (float) ($display->getComponent('title')['weight'] ?? -100) + 0.2;
    $title = is_array($form['title'] ?? NULL) ? $form['title'] : [];
    if (isset($title['#group'])) {
      $form[PatternPicker::KEY]['#group'] = $title['#group'];
    }
  }

  /**
   * Implements hook_preprocess_paragraph().
   *
   * The class in the CSS classes field is printed by the parent module; this
   * only loads the look it names. A paragraph without one returns at once.
   *
   * @param array<string, mixed> $variables
   *   The template variables.
   */
  #[Hook('preprocess_paragraph')]
  public function preprocessParagraph(array &$variables): void {
    $paragraph = $variables['paragraph'] ?? NULL;
    if (!$paragraph instanceof ParagraphInterface || !$paragraph->hasField(PatternTreeBuilder::CLASS_FIELD)) {
      return;
    }
    $value = $paragraph->get(PatternTreeBuilder::CLASS_FIELD)->getString();
    if (!str_contains($value, 'pb-pattern--')) {
      return;
    }
    $libraries = [];
    foreach (preg_split('/\s+/', trim($value)) ?: [] as $class) {
      $pattern = $this->repository->byClass($paragraph->bundle(), $class);
      if ($pattern !== NULL) {
        $libraries[] = $pattern['library'];
      }
    }
    if ($libraries !== []) {
      $attached = is_array($variables['#attached'] ?? NULL) ? $variables['#attached'] : [];
      $existing = is_array($attached['library'] ?? NULL) ? $attached['library'] : [];
      $attached['library'] = array_merge($existing, $libraries);
      $variables['#attached'] = $attached;
    }
  }

}
