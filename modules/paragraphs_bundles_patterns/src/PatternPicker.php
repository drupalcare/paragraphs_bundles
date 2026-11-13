<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles_patterns;

use Drupal\Component\Utility\Html;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Draws the PB Patterns picker. The same layout as the APEX picker.
 *
 * Every Pattern is a card in one grid and one radio group. The script adds
 * the search and one chip per bundle; without it the cards still work.
 *
 * @phpstan-import-type PatternCard from \Drupal\paragraphs_bundles_patterns\PatternInserter
 */
final class PatternPicker {

  use StringTranslationTrait;

  /**
   * The picker's key in the node form.
   */
  public const KEY = 'pb_patterns_insert';

  /**
   * The HTML id around the picker, for the Ajax redraw.
   */
  public const WRAPPER_ID = 'pb-patterns-insert-wrapper';

  public function __construct(
    private readonly PatternRepository $repository,
    private readonly ModuleExtensionList $moduleExtensionList,
    private readonly FileUrlGeneratorInterface $fileUrlGenerator,
    #[Autowire(param: 'app.root')]
    private readonly string $appRoot,
  ) {}

  /**
   * Builds the picker.
   *
   * @param array<string, PatternCard> $cards
   *   The cards.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The render array.
   */
  public function build(array $cards, FormStateInterface $form_state): array {
    $choiceParents = [self::KEY, 'pattern'];
    $added = $this->addedSentence($form_state, $cards);
    return [
      '#type' => 'details',
      '#title' => $this->summary(),
      '#open' => $added !== NULL,
      '#prefix' => '<div id="' . self::WRAPPER_ID . '">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['pb-patterns-insert']],
      '#attached' => ['library' => ['paragraphs_bundles_patterns/insert']],
      '#cache' => ['contexts' => ['user.permissions']],
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#attributes' => ['class' => ['pb-patterns-insert__intro']],
        '#value' => $this->t('Pick a layout. It is added to the end with sample words and pictures; change them before you save.'),
      ],
      'actions' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['pb-patterns-insert__actions']],
        'status' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => [
            'class' => ['pb-patterns-insert__status'],
            'role' => 'status',
          ],
          'sentence' => ['#markup' => $added ?? ''],
        ],
        'insert' => [
          '#type' => 'submit',
          '#value' => $this->t('Insert pattern'),
          '#name' => self::KEY . '_submit',
          '#attributes' => ['class' => ['button--primary', 'pb-patterns-insert__submit']],
          '#validate' => ['paragraphs_bundles_patterns.insert_form:validate'],
          '#submit' => ['paragraphs_bundles_patterns.insert_form:submit'],
          '#limit_validation_errors' => [$choiceParents],
          '#ajax' => [
            'callback' => 'paragraphs_bundles_patterns.insert_form:ajax',
            'wrapper' => self::WRAPPER_ID,
          ],
          '#pb_patterns_choice' => $choiceParents,
        ],
      ],
      'families' => $this->grid($cards, $choiceParents),
    ];
  }

  /**
   * The summary: the logo, then the name.
   *
   * @return array<string, mixed>
   *   A render array; the details element prints it in its summary.
   */
  private function summary(): array {
    return [
      'logo' => [
        '#type' => 'html_tag',
        '#tag' => 'img',
        '#attributes' => [
          'src' => $this->fileUrlGenerator->generateString($this->moduleExtensionList->getPath('paragraphs_bundles') . '/images/logo.svg'),
          'alt' => '',
          'class' => ['pb-patterns-insert__logo'],
        ],
      ],
      'name' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#attributes' => ['class' => ['pb-patterns-insert__name']],
        '#value' => $this->t('PB Patterns'),
      ],
    ];
  }

  /**
   * Builds the grid of cards.
   *
   * @param array<string, PatternCard> $cards
   *   The cards.
   * @param list<string> $choiceParents
   *   The #parents every radio shares.
   *
   * @return array<string, mixed>
   *   The fieldset holding the grid.
   */
  private function grid(array $cards, array $choiceParents): array {
    $info = $this->repository->bundleInfo();
    $build = [
      '#type' => 'fieldset',
      '#title' => $this->t('Patterns'),
      '#title_display' => 'invisible',
      '#attributes' => ['class' => ['pb-patterns-insert__list']],
      'grid' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['pb-patterns-insert__grid']],
      ],
    ];
    foreach ($this->sorted($cards, $info) as $key => $card) {
      $pattern = $card['pattern'];
      $bundle = $pattern['bundle'];
      $radioId = Html::getId('pb-patterns-insert-' . $key);
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      $bundleName = $this->t($info[$bundle]['label'] ?? $bundle);
      $off = $card['field'] === NULL;
      $build['grid'][$key] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => array_merge(['pb-patterns-insert__option'], $off ? ['pb-patterns-insert__option--off'] : []),
          'data-family' => $bundle,
        ],
        'tag' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => [
            'id' => $radioId . '--family',
            'class' => ['pb-patterns-insert__tag'],
          ],
          '#value' => $bundleName,
        ],
        'figure' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#attributes' => [
            'class' => ['pb-patterns-insert__figure'],
            'aria-hidden' => 'true',
          ],
          'picture' => $this->figure($key),
        ],
        'head' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['pb-patterns-insert__head']],
          'radio' => [
            '#type' => 'radio',
            '#theme' => 'pb_patterns_radio',
            '#theme_wrappers' => [],
            '#id' => $radioId,
            '#return_value' => $key,
            '#parents' => $choiceParents,
            '#default_value' => NULL,
            '#disabled' => $off,
            '#attributes' => [
              'class' => ['pb-patterns-insert__radio'],
              'aria-describedby' => $radioId . '--family ' . $radioId . '--description',
            ],
          ],
          'label' => [
            '#type' => 'html_tag',
            '#tag' => 'label',
            '#attributes' => [
              'for' => $radioId,
              'class' => ['pb-patterns-insert__title'],
            ],
            // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
            '#value' => $this->t($pattern['label']),
          ],
        ],
        'description' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#attributes' => [
            'id' => $radioId . '--description',
            'class' => ['pb-patterns-insert__description'],
          ],
          '#value' => $this->description($card),
        ],
        'badge' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => [
            'class' => ['pb-patterns-insert__badge'],
            'aria-hidden' => 'true',
          ],
          '#value' => $this->t('Selected'),
        ],
      ];
    }
    return $build;
  }

  /**
   * Orders the cards by bundle name, keeping each bundle's own order.
   *
   * @param array<string, PatternCard> $cards
   *   The cards.
   * @param array<string, array{label: string, module: string, module_label: string}> $info
   *   The bundle names.
   *
   * @return array<string, PatternCard>
   *   The sorted cards.
   */
  private function sorted(array $cards, array $info): array {
    $position = array_flip(array_keys($cards));
    uksort($cards, static function (string $a, string $b) use ($cards, $info, $position): int {
      $left = $info[$cards[$a]['pattern']['bundle']]['label'] ?? $a;
      $right = $info[$cards[$b]['pattern']['bundle']]['label'] ?? $b;
      return [$left, $position[$a]] <=> [$right, $position[$b]];
    });
    return $cards;
  }

  /**
   * Returns a card's words: its description, or how to turn it on.
   *
   * @param PatternCard $card
   *   The card.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The words.
   */
  private function description(array $card): TranslatableMarkup {
    if ($card['missing'] !== []) {
      return $this->t('Turn on @modules under Extend to use this pattern.', ['@modules' => implode(', ', $card['missing'])]);
    }
    if ($card['field'] === NULL) {
      return $this->t("This page's field does not allow @bundle, or you may not add it.", ['@bundle' => $card['pattern']['bundle']]);
    }
    // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
    return $this->t($card['pattern']['description']);
  }

  /**
   * Returns the card's generated thumbnail.
   *
   * @param string $key
   *   The bundle.name key.
   *
   * @return array<string, mixed>
   *   A render array; empty until the thumbnail exists.
   */
  private function figure(string $key): array {
    $path = $this->moduleExtensionList->getPath('paragraphs_bundles_patterns') . '/images/thumbs/' . $key . '.webp';
    if (!is_file($this->appRoot . '/' . $path)) {
      return [];
    }
    return [
      '#type' => 'html_tag',
      '#tag' => 'img',
      '#attributes' => [
        'src' => $this->fileUrlGenerator->generateString($path),
        'alt' => '',
        'loading' => 'lazy',
        'decoding' => 'async',
      ],
    ];
  }

  /**
   * Returns the "Added" sentence after an insert in this request.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<string, PatternCard> $cards
   *   The cards.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The sentence, or NULL when nothing was just inserted.
   */
  private function addedSentence(FormStateInterface $form_state, array $cards): ?TranslatableMarkup {
    $inserted = $form_state->get(PatternInsertForm::INSERTED_KEY);
    $key = is_array($inserted) ? ($inserted['pattern'] ?? NULL) : NULL;
    if (!is_string($key) || !isset($cards[$key])) {
      return NULL;
    }
    return $this->t('Added %name at the end. Change its sample words before you save.', [
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      '%name' => $this->t($cards[$key]['pattern']['label']),
    ]);
  }

}
