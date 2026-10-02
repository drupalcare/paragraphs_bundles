<?php

declare(strict_types=1);

namespace Drupal\paragraphs_bundles\Form;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Drupal\paragraphs_bundles\Service\ListIndex\ListIndexerInterface;
use Drupal\paragraphs_bundles\Service\ListIndex\ListIndexQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists paragraphs, filters, sorts and searches them, and deletes the selected.
 *
 * The page is answered from the Paragraphs List index table, one keyset page at
 * a time, so it costs about the same on a thousand paragraphs as on a million.
 * Edit, bulk delete and save-back-to-list behave as they did when this lived
 * in pb_import.
 */
final class ParagraphsListForm extends FormBase {

  /**
   * Columns a person may hide, keyed by column id; the PID is always shown.
   *
   * The id `title` is avoided on purpose: Tableselect reads an option's
   * `title` cell as a checkbox label and breaks on a Link in it.
   *
   * The values are the sort key of the column, or NULL when it cannot be
   * sorted (the parent and the edit link are computed).
   */
  private const COLUMNS = [
    'type' => 'type',
    'nid' => 'nid',
    'node_title' => 'node_title',
    'label' => 'label',
    'parent' => NULL,
    'edit' => NULL,
  ];

  /**
   * Constructs a new ParagraphsListForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\paragraphs_bundles\Service\ListIndex\ListIndexQuery $listQuery
   *   The index query service.
   * @param \Drupal\paragraphs_bundles\Service\ListIndex\ListIndexerInterface $indexer
   *   The indexer, asked whether the index table exists.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user, whose update access decides each row's controls.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ListIndexQuery $listQuery,
    protected ListIndexerInterface $indexer,
    protected AccountProxyInterface $currentUser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('paragraphs_bundles.list_index_query'),
      $container->get(ListIndexerInterface::class),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'paragraphs_bundles_paragraphs_list_form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array<string, mixed>
   *   The form array.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $criteria = $this->listQuery->normalize($this->getRequest()->query->all());

    $form['#attributes'] = ['class' => ['pb-paragraphs-list']];
    $form['#attached'] = [
    'library' => [
      'paragraphs_bundles/paragraphs-list',
    ]
];
    $form['#cache'] = [
      'contexts' => ['url.query_args', 'user.permissions'],
      'tags' => [ListIndexerInterface::CACHE_TAG, 'paragraph_list'],
    ];

    $available = $this->indexer->isAvailable();
    $page = $available
      ? $this->listQuery->fetchPage($criteria)
      : ['rows' => [], 'next' => NULL, 'prev' => NULL];
    $total = $available ? $this->listQuery->count($criteria) : 0;

    if (!$available) {
      $form['unavailable'] = [
        '#markup' => $this->t('The Paragraphs List index table does not exist yet. Run the database updates (update.php or drush updatedb).'),
        '#prefix' => '<div class="messages messages--warning" role="alert">',
        '#suffix' => '</div>',
      ];
    }

    // Row 1: the summary on the left; the Columns checkboxes are added to this
    // bar on the right by JavaScript (they do nothing without it).
    $form['bar'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['pb-paragraphs-list__bar']],
    ];
    $form['bar']['summary'] = [
      '#markup' => $this->t('Displaying @count out of @total paragraphs.', [
        '@count' => count($page['rows']),
        '@total' => $total,
      ]),
      '#prefix' => '<div class="paragraph-summary">',
      '#suffix' => '</div>',
    ];

    $form['filter'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['pb-paragraphs-list__filters']],
    ];

    $form['filter']['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Filter by type'),
      '#options' => $this->getParagraphTypes(),
      '#default_value' => $criteria['type'] === '' ? NULL : $criteria['type'],
    ];

    $form['filter']['title_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Content title match'),
      '#options' => [
        'starts' => $this->t('Starts with (fast)'),
        'contains' => $this->t('Contains (slower on large sites)'),
      ],
      '#default_value' => $criteria['title_mode'],
    ];

    $form['filter']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Content title'),
      '#default_value' => $criteria['title'],
      '#maxlength' => 255,
      '#size' => 30,
    ];

    $form['filter']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label contains'),
      '#description' => $this->t('Slower on large sites.'),
      // Above the input, so every input sits at the bottom of its field and the
      // row bottom-aligns on the inputs.
      '#description_display' => 'before',
      '#default_value' => $criteria['label'],
      '#maxlength' => 255,
      '#size' => 30,
    ];

    // A plain container, not '#type' => 'actions': Claro's sticky-actions
    // behaviour grabs every [data-drupal-selector="edit-actions"] and moves its
    // buttons out of the filter grid.
    $form['filter']['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['pb-paragraphs-list__filter-actions']],
    ];

    $form['filter']['actions']['filter'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
      '#submit' => ['::filterForm'],
    ];

    $form['filter']['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset'),
      '#submit' => ['::resetForm'],
    ];

    $cacheability = CacheableMetadata::createFromRenderArray($form);

    // The scroll region is a real container so Drupal escapes the aria-label.
    // It adds no #tree level: the table's value is still `paragraphs_table`.
    $form['scroll'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['pb-paragraphs-list__scroll'],
        'role' => 'region',
        'tabindex' => '0',
        'aria-label' => (string) $this->t('Paragraphs table, scrolls sideways on narrow screens'),
      ],
    ];
    $form['scroll']['paragraphs_table'] = [
      '#type' => 'tableselect',
      '#header' => $this->buildHeader($criteria),
      '#options' => $this->buildRows($page['rows'], $cacheability),
      '#empty' => $this->t('No paragraphs found.'),
      '#attributes' => [
        'id' => 'paragraphs-table',
        'class' => ['pb-paragraphs-list__table'],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete Selected'),
      '#button_type' => 'danger',
      '#attributes' => ['data-pb-paragraphs-list-delete' => TRUE],
    ];

    $form['pager'] = $this->buildPager($criteria, $page);

    // What each row's access answer depended on.
    $cacheability->applyTo($form);

    return $form;
  }

  /**
   * Builds the table header, with sort links on the indexed columns.
   *
   * @param array{type: string, title: string, title_mode: string, label: string, sort: string, dir: string, after: string, before: string} $criteria
   *   The normalised criteria.
   *
   * @return array<string, array<string, mixed>>
   *   The header cells keyed by column id.
   */
  private function buildHeader(array $criteria): array {
    $labels = [
      'type' => $this->t('Type'),
      'nid' => $this->t('NID'),
      'node_title' => $this->t('Content title'),
      'label' => $this->t('Label'),
      'parent' => $this->t('Parent'),
      'edit' => $this->t('Edit'),
    ];

    $header = [
      'id' => $this->sortableCell('id', 'pid', $this->t('PID'), $criteria),
    ];
    foreach (self::COLUMNS as $column => $sort_key) {
      $header[$column] = $this->sortableCell($column, $sort_key, $labels[$column], $criteria);
    }
    return $header;
  }

  /**
   * Builds one header cell; a link when the column is sortable.
   *
   * @param string $column
   *   The column id.
   * @param string|null $sort_key
   *   The sort key, or NULL when the column cannot be sorted.
   * @param mixed $label
   *   The visible heading.
   * @param array{type: string, title: string, title_mode: string, label: string, sort: string, dir: string, after: string, before: string} $criteria
   *   The normalised criteria.
   *
   * @return array<string, mixed>
   *   The header cell.
   */
  private function sortableCell(string $column, ?string $sort_key, mixed $label, array $criteria): array {
    $cell = [
      'class' => ['pb-col', 'pb-col--' . $column],
    ];
    // The PID and Edit have no checkbox: they are always shown.
    if ($column !== 'id' && $column !== 'edit') {
      $cell['data-pb-col'] = $column;
      $cell['data-pb-col-label'] = is_scalar($label) || $label instanceof \Stringable ? (string) $label : '';
    }
    if ($sort_key === NULL) {
      $cell['data'] = $label;
      return $cell;
    }

    $active = $criteria['sort'] === $sort_key;
    $next_dir = $active && $criteria['dir'] === 'asc' ? 'desc' : 'asc';
    // A new sort starts from the first page, so the cursor is dropped.
    $query = $this->criteriaQuery($criteria);
    $query['sort'] = $sort_key;
    $query['dir'] = $next_dir;
    $title = ['label' => ['#plain_text' => $cell['data-pb-col-label'] ?? 'PID']];
    if ($active) {
      $cell['aria-sort'] = $criteria['dir'] === 'asc' ? 'ascending' : 'descending';
      $title['hint'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => ' ' . ($criteria['dir'] === 'asc' ? $this->t('(sorted ascending)') : $this->t('(sorted descending)')),
        '#attributes' => ['class' => ['visually-hidden']],
      ];
    }
    $cell['data'] = [
      '#type' => 'link',
      '#title' => $title,
      '#url' => Url::fromRoute('paragraphs_bundles.paragraphs_list', [], ['query' => $query]),
      '#attributes' => ['class' => ['pb-paragraphs-list__sort']],
    ];
    return $cell;
  }

  /**
   * Builds the table rows for one page of index rows.
   *
   * Loads the page's paragraphs in one call and the page's node titles in one
   * query: two lookups for the page, never a walk per row.
   *
   * @param list<array{pid: int, bundle: string, root_type: string, root_id: int, depth: int, langcode: string, label: string, root_title: string}> $index_rows
   *   Index rows.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects the cacheability of every access check made.
   *
   * @return array<int|string, array<string, mixed>>
   *   The table rows, keyed by paragraph id.
   */
  private function buildRows(array $index_rows, CacheableMetadata $cacheability): array {
    if ($index_rows === []) {
      return [];
    }
    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    assert($paragraph_storage instanceof EntityStorageInterface);
    $pids = array_map(static fn(array $row): int => $row['pid'], $index_rows);
    $paragraphs = $paragraph_storage->loadMultiple($pids);

    $nids = [];
    foreach ($index_rows as $row) {
      if ($row['root_type'] === 'node') {
        $nids[] = $row['root_id'];
      }
    }
    $titles = $this->listQuery->nodeTitles($nids);

    $items = [];
    foreach ($index_rows as $row) {
      $pid = $row['pid'];
      $paragraph = $paragraphs[$pid] ?? NULL;
      $parent_label = $this->t('No Parent');
      if ($paragraph instanceof ParagraphInterface) {
        $parent_entity = $paragraph->getParentEntity();
        if ($parent_entity !== NULL) {
          $parent_label = $parent_entity->label();
        }
      }

      // Only the displayed rows (at most one page) are asked, never the table.
      // A row whose paragraph this user may not update gets no select box and
      // no Edit link; Paragraphs' own access handler asks the host entity and
      // allows a paragraph that has no host.
      $may_update = FALSE;
      if ($paragraph instanceof ParagraphInterface) {
        $access = $paragraph->access('update', $this->currentUser, TRUE);
        $cacheability->addCacheableDependency($access);
        $may_update = $access->isAllowed();
      }
      $edit_cell = $may_update
        ? Link::fromTextAndUrl($this->t('Edit'), Url::fromRoute('paragraphs_bundles.paragraph_edit', [
          'paragraph' => $pid,
        ]))
        : '';

      [$nid_cell, $title_cell] = $this->rootCells($row, $titles);

      $items[$pid] = [
        'id' => ['data' => $pid, 'class' => ['pb-col', 'pb-col--id']],
        'type' => $this->cell('type', $row['bundle']),
        'nid' => $this->cell('nid', $nid_cell),
        'node_title' => $this->cell('node_title', $title_cell),
        'label' => $this->cell('label', $row['label'] !== '' ? $row['label'] : $this->t('No label')),
        'parent' => $this->cell('parent', $parent_label),
        'edit' => $this->cell('edit', $edit_cell),
      ];
      if (!$may_update) {
        $items[$pid]['#disabled'] = TRUE;
      }
    }
    return $items;
  }

  /**
   * Builds a body cell tagged with its column, so the browser can hide it.
   *
   * @param string $column
   *   The column id.
   * @param mixed $data
   *   The cell content.
   *
   * @return array<string, mixed>
   *   The cell.
   */
  private function cell(string $column, mixed $data): array {
    $cell = [
      'data' => $data,
      'class' => ['pb-col', 'pb-col--' . $column],
    ];
    if ($column !== 'edit') {
      $cell['data-pb-col'] = $column;
    }
    return $cell;
  }

  /**
   * Works out the NID and Content title cells for one index row.
   *
   * @param array{pid: int, bundle: string, root_type: string, root_id: int, depth: int, langcode: string, label: string, root_title: string} $row
   *   An index row.
   * @param array<int, string> $titles
   *   Node titles keyed by nid.
   *
   * @return array{0: mixed, 1: mixed}
   *   The NID cell content and the title cell content.
   */
  private function rootCells(array $row, array $titles): array {
    $root_type = $row['root_type'];
    $root_id = $row['root_id'];
    if ($root_type === '') {
      return ['—', '—'];
    }
    if ($root_type !== 'node') {
      return [$root_type . ' ' . $root_id, '—'];
    }
    $title = $titles[$root_id] ?? NULL;
    if ($title === NULL) {
      return [$root_id, '—'];
    }
    return [
      $root_id,
      Link::createFromRoute($title, 'entity.node.canonical', ['node' => $root_id]),
    ];
  }

  /**
   * Builds the Previous / Next links.
   *
   * @param array{type: string, title: string, title_mode: string, label: string, sort: string, dir: string, after: string, before: string} $criteria
   *   The normalised criteria.
   * @param array{rows: list<array{pid: int, bundle: string, root_type: string, root_id: int, depth: int, langcode: string, label: string, root_title: string}>, next: string|null, prev: string|null} $page
   *   The page result with its cursors.
   *
   * @return array<string, mixed>
   *   A render array; empty when there is nowhere to go.
   */
  private function buildPager(array $criteria, array $page): array {
    $base = $this->criteriaQuery($criteria);
    $links = [];
    if ($page['prev'] !== NULL) {
      $links[] = [
        '#type' => 'link',
        '#title' => $this->t('Previous'),
        '#url' => Url::fromRoute('paragraphs_bundles.paragraphs_list', [], ['query' => $base + ['before' => $page['prev']]]),
        '#attributes' => ['rel' => 'prev'],
      ];
    }
    if ($page['next'] !== NULL) {
      $links[] = [
        '#type' => 'link',
        '#title' => $this->t('Next'),
        '#url' => Url::fromRoute('paragraphs_bundles.paragraphs_list', [], ['query' => $base + ['after' => $page['next']]]),
        '#attributes' => ['rel' => 'next'],
      ];
    }
    if ($links === []) {
      return [];
    }
    $list = [
      '#theme' => 'item_list',
      '#items' => $links,
      '#attributes' => ['class' => ['pb-paragraphs-list__pager']],
    ];
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['pb-paragraphs-list__pager-nav'],
        'role' => 'navigation',
        'aria-label' => (string) $this->t('Paragraphs list pagination'),
      ],
      'list' => $list,
    ];
  }

  /**
   * The filter and sort values as a query array, without a paging cursor.
   *
   * Only values that differ from the defaults are included, so the plain list
   * keeps its plain address.
   *
   * @param array{type: string, title: string, title_mode: string, label: string, sort: string, dir: string, after: string, before: string} $criteria
   *   The normalised criteria.
   *
   * @return array<string, string>
   *   The query-string values.
   */
  private function criteriaQuery(array $criteria): array {
    $query = [];
    if ($criteria['type'] !== '') {
      $query['type'] = $criteria['type'];
    }
    if ($criteria['title'] !== '') {
      $query['title'] = $criteria['title'];
      $query['title_mode'] = $criteria['title_mode'];
    }
    if ($criteria['label'] !== '') {
      $query['label'] = $criteria['label'];
    }
    // With a title search the sort is always written out, so choosing PID order
    // is not mistaken for "no sort chosen".
    if ($criteria['sort'] !== 'pid' || $criteria['dir'] !== 'asc' || $criteria['title'] !== '') {
      $query['sort'] = $criteria['sort'];
      $query['dir'] = $criteria['dir'];
    }
    return $query;
  }

  /**
   * Fetches available paragraph types.
   *
   * @return array<int|string, \Drupal\Core\StringTranslation\TranslatableMarkup|string|null>
   *   The type labels, keyed by type id.
   */
  protected function getParagraphTypes(): array {
    $types_storage = $this->entityTypeManager->getStorage('paragraphs_type');
    $types = $types_storage->loadMultiple();

    $options = ['' => $this->t('- All -')];
    foreach ($types as $type) {
      assert($type instanceof ParagraphsTypeInterface);
      $options[$type->id()] = $type->label();
    }
    return $options;
  }

  /**
   * Filter form submission handler.
   *
   * Starts again from the first page and keeps the chosen sort.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function filterForm(array &$form, FormStateInterface $form_state): void {
    $criteria = $this->listQuery->normalize([
      'type' => $form_state->getValue('type'),
      'title' => $form_state->getValue('title'),
      'title_mode' => $form_state->getValue('title_mode'),
      'label' => $form_state->getValue('label'),
      'sort' => $this->getRequest()->query->get('sort'),
      'dir' => $this->getRequest()->query->get('dir'),
    ]);
    $form_state->setRedirect('paragraphs_bundles.paragraphs_list', [], ['query' => $this->criteriaQuery($criteria)]);
  }

  /**
   * Reset form submission handler.
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function resetForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('paragraphs_bundles.paragraphs_list');
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string, mixed> $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $selected_paragraphs = array_filter((array) $form_state->getValue('paragraphs_table', []));

    if (empty($selected_paragraphs)) {
      $this->messenger()->addWarning($this->t('No paragraphs selected for deletion.'));
      $form_state->setRedirect('paragraphs_bundles.paragraphs_list');
      return;
    }

    $paragraph_storage = $this->entityTypeManager->getStorage('paragraph');
    assert($paragraph_storage instanceof EntityStorageInterface);

    $deleted_count = 0;
    $skipped_count = 0;
    foreach ($selected_paragraphs as $paragraph_id) {
      if (!is_int($paragraph_id) && !is_string($paragraph_id)) {
        continue;
      }
      $paragraph = $paragraph_storage->load($paragraph_id);
      if ($paragraph instanceof ParagraphInterface) {
        // Never trust the submitted set: ask again, server side, per id.
        if (!$paragraph->access('delete', $this->currentUser)) {
          $skipped_count++;
          continue;
        }
        try {
          $paragraph->delete();
          $deleted_count++;
        }
        catch (\Exception $e) {
          $this->messenger()->addError($this->t('Failed to delete paragraph @id: @message', [
            '@id' => $paragraph_id,
            '@message' => $e->getMessage(),
          ]));
        }
      }
    }

    if ($deleted_count > 0) {
      $this->messenger()->addStatus($this->t('Deleted @count paragraph(s).', [
        '@count' => $deleted_count,
      ]));
    }
    if ($skipped_count > 0) {
      $this->messenger()->addWarning($this->t('Skipped @count paragraph(s) you may not change.', [
        '@count' => $skipped_count,
      ]));
    }

    $form_state->setRedirect('paragraphs_bundles.paragraphs_list');
  }

}
