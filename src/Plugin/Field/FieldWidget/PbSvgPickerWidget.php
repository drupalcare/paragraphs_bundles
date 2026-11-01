<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsButtonsWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\paragraphs_bundles\Service\Svg\PbSvgLibrary;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Visual picker for the pb_*_svg_* fields: a searchable grid of previews.
 *
 * Built on core's radio buttons, so keyboard use, screen readers, validation
 * and the "None" choice come from core; the radios are visually hidden and the
 * previews are their labels. Without JavaScript it is a plain list of radios.
 * The fields always use this widget (PbSvgHook::entityFormDisplayAlter).
 */
#[FieldWidget(
  id: 'pb_svg_picker',
  label: new TranslatableMarkup('SVG picker'),
  field_types: ['list_string'],
  multiple_values: TRUE,
)]
final class PbSvgPickerWidget extends OptionsButtonsWidget {

  /**
   * The SVG library.
   */
  protected PbSvgLibrary $svgLibrary;

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @param array<string, mixed> $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin id.
   * @param mixed $plugin_definition
   *   The plugin definition.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $library = $container->get('paragraphs_bundles.svg_library');
    assert($library instanceof PbSvgLibrary);
    $instance->svgLibrary = $library;
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getFieldStorageDefinition()->getSetting('allowed_values_function') === PbSvgLibrary::ALLOWED_VALUES_FUNCTION;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Field\FieldItemListInterface<\Drupal\Core\Field\FieldItemInterface> $items
   *   The field items.
   * @param int $delta
   *   The item delta.
   * @param array<string, mixed> $element
   *   The form element.
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<mixed>
   *   The form element.
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $items_by_key = $this->svgLibrary->items();

    $options = [];
    $groups = [];
    $element_options = is_array($element['#options'] ?? NULL) ? $element['#options'] : [];
    foreach ($element_options as $key => $label) {
      $key = (string) $key;
      if (!isset($items_by_key[$key])) {
        // "_none" and anything core adds keep their own label.
        $options[$key] = $label;
        continue;
      }
      $item = $items_by_key[$key];
      // Only the preview shows; the readable name is the tooltip and is read
      // by screen readers.
      $name = Html::escape(self::humanName($item['name']));
      $options[$key] = Markup::create('<span class="pb-svg-picker__tile" title="' . $name . '"><img src="' . Html::escape($item['url']) . '" alt="" loading="lazy" width="32" height="32"><span class="pb-svg-picker__name visually-hidden">' . $name . '</span></span>');
      $groups[$key] = [
        'theme' => $item['theme_label'],
        'search' => mb_strtolower($item['theme_label'] . ' ' . $item['group'] . ' ' . $item['name'] . ' ' . self::humanName($item['name'])),
      ];
    }
    $element['#options'] = $options;
    $element['#pb_svg_groups'] = $groups;
    $element = NestedArray::mergeDeep($element, [
      // After build, not #process: setting #process would replace core's radios
      // process callback and no radio would be built.
      '#after_build' => [[self::class, 'processPicker']],
      '#attributes' => [
        'class' => ['pb-svg-picker'],
        'data-pb-svg-picker' => '',
      ],
      '#attached' => [
        'library' => ['paragraphs_bundles/svg-picker'],
        'drupalSettings' => [
          'pbSvgPicker' => [
            'strings' => [
              'search' => (string) $this->t('Search icons'),
              'clear' => (string) $this->t('Clear search'),
              'none' => (string) $this->t('No SVG'),
              'choose' => (string) $this->t('Choose an SVG'),
              'count' => (string) $this->t('@count icons'),
              'countFiltered' => (string) $this->t('@shown of @count icons'),
              'empty' => (string) $this->t('No icons match your search.'),
            ],
          ],
        ],
      ],
    ]);
    if ($items_by_key === []) {
      $element['#description'] = $this->t('No SVG files found. A developer adds them in the front-end theme (or a base theme) under <code>@folder</code>.', ['@folder' => PbSvgLibrary::FOLDER]);
    }
    return $element;
  }

  /**
   * A file name made readable: "sports_car-2" becomes "Sports car 2".
   */
  public static function humanName(string $name): string {
    $words = trim((string) preg_replace('/[\s_\-.]+/', ' ', $name));
    return $words === '' ? $name : mb_strtoupper(mb_substr($words, 0, 1)) . mb_substr($words, 1);
  }

  /**
   * Adds the theme group and search text to each radio, after core built them.
   *
   * @param array<string, mixed> $element
   *   The radios element, with one child per option.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The element.
   */
  public static function processPicker(array $element, FormStateInterface $form_state): array {
    $groups = is_array($element['#pb_svg_groups'] ?? NULL) ? $element['#pb_svg_groups'] : [];
    foreach ($groups as $key => $group) {
      $child = $element[$key] ?? NULL;
      if (!is_array($child) || !is_array($group)) {
        continue;
      }
      $wrapper = is_array($child['#wrapper_attributes'] ?? NULL) ? $child['#wrapper_attributes'] : [];
      $classes = is_array($wrapper['class'] ?? NULL) ? $wrapper['class'] : [];
      $classes[] = 'pb-svg-picker__item';
      $wrapper['class'] = $classes;
      $wrapper['data-pb-svg-theme'] = is_string($group['theme'] ?? NULL) ? $group['theme'] : '';
      $wrapper['data-pb-svg-search'] = is_string($group['search'] ?? NULL) ? $group['search'] : '';
      $child['#wrapper_attributes'] = $wrapper;
      $element[$key] = $child;
    }
    return $element;
  }

}
