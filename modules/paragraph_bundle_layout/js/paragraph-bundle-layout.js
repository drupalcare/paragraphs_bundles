/**
 * @file
 * Paragraph Bundle Layout - column toggles, expand, sticky.
 *
 * Filename:     paragraph-bundle-layout.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing

((Drupal, once) => {
  'use strict';

  const PARAGRAPHS_BUNDLES = 'paragraphs-bundles';
  const PB_LAYOUT = 'pb-layout';
  const PB_TWO_COLUMNS = 'pb-two-columns';
  const PB_THREE_COLUMNS = 'pb-three-columns';
  const PB_LAYOUT_COL__TOOLBAR = 'pb-layout-col__toolbar';
  const PB_LAYOUT_COL__CONTENT = 'pb-layout-col__content';
  const PB_LAYOUT_COL__TOGGLE = 'pb-layout-col__toggle';
  const PB_IS_COLLAPSED = 'pb-is-collapsed';
  const PB_IS_STICKY = 'pb-is-sticky';

  const PARAGRAPHS_BUNDLES_PB_LAYOUT =
    `.${PARAGRAPHS_BUNDLES}.${PB_LAYOUT}`;
  const PARAGRAPHS_BUNDLES_PB_LAYOUT_PB_TWO_COLUMNS =
    `${PARAGRAPHS_BUNDLES_PB_LAYOUT}.${PB_TWO_COLUMNS}`;
  const PARAGRAPHS_BUNDLES_PB_LAYOUT_PB_THREE_COLUMNS =
    `${PARAGRAPHS_BUNDLES_PB_LAYOUT}.${PB_THREE_COLUMNS}`;
  const LAYOUT_GRID_SELECTOR = [
    `${PARAGRAPHS_BUNDLES_PB_LAYOUT_PB_TWO_COLUMNS} .${PB_TWO_COLUMNS}:not(.${PB_LAYOUT_COL__TOOLBAR})`,
    `${PARAGRAPHS_BUNDLES_PB_LAYOUT_PB_THREE_COLUMNS} .${PB_THREE_COLUMNS}:not(.${PB_LAYOUT_COL__TOOLBAR})`,
  ].join(', ');
  const LAYOUT_COLUMN_SELECTOR = [
    `${PARAGRAPHS_BUNDLES_PB_LAYOUT_PB_TWO_COLUMNS} .${PB_TWO_COLUMNS}:not(.${PB_LAYOUT_COL__TOOLBAR}) > [data-col]`,
    `${PARAGRAPHS_BUNDLES_PB_LAYOUT_PB_THREE_COLUMNS} .${PB_THREE_COLUMNS}:not(.${PB_LAYOUT_COL__TOOLBAR}) > [data-col]`,
  ].join(', ');
  const LAYOUT_STICKY_SELECTOR = [
    `${PARAGRAPHS_BUNDLES_PB_LAYOUT_PB_TWO_COLUMNS} [data-col].${PB_IS_STICKY}`,
    `${PARAGRAPHS_BUNDLES_PB_LAYOUT_PB_THREE_COLUMNS} [data-col].${PB_IS_STICKY}`,
  ].join(', ');

  const PB_LAYOUT_GRID_OWNED_COLUMN = ':scope > [data-col]';
  const PB_LAYOUT_COLUMN_OWNED_CONTENT =
    `:scope > .${PB_LAYOUT_COL__CONTENT}`;

  const ONCE_PB_PARAGRAPH_BUNDLE_LAYOUT_COLUMN =
    'pb-paragraph-bundle-layout-column';
  const ONCE_PB_PARAGRAPH_BUNDLE_LAYOUT_GRID =
    'pb-paragraph-bundle-layout-grid';
  const ONCE_PB_PARAGRAPH_BUNDLE_LAYOUT_STICKY =
    'pb-paragraph-bundle-layout-sticky';

  /** @type {readonly string[]} */
  const COLLAPSE_CLASSES = [
    'left-is-collapsed',
    'right-is-collapsed',
    'middle-is-collapsed',
    'left-right-collapsed',
    'left-middle-collapsed',
    'right-middle-collapsed',
    'left-right-middle-collapsed',
  ];

  /** @type {readonly string[]} */
  const COL_ORDER = ['left', 'right', 'middle'];

  /** @type {WeakMap<HTMLElement, AbortController>} */
  const gridAbortControllers = new WeakMap();

  /**
   * @param {string} token
   *
   * @return {string}
   */
  const toBemToken = (token) => token.replace(/_/g, '-');

  /**
   * @param {HTMLElement} col
   *
   * @return {boolean}
   */
  const isCollapsed = (col) => col.hasAttribute('data-collapsed');

  /**
   * @param {HTMLElement} col
   * @param {boolean} collapsed
   */
  const setCollapsed = (col, collapsed) => {
    if (collapsed) {
      col.setAttribute('data-collapsed', '');
    }
    else {
      col.removeAttribute('data-collapsed');
    }
  };

  /**
   * @param {HTMLElement} grid
   */
  const updateCollapseClass = (grid) => {
    const paragraph = grid.closest(
      `${PARAGRAPHS_BUNDLES_PB_LAYOUT_PB_TWO_COLUMNS}, ${PARAGRAPHS_BUNDLES_PB_LAYOUT_PB_THREE_COLUMNS}`,
    );

    if (!(paragraph instanceof HTMLElement)) {
      return;
    }

    const columns = grid.querySelectorAll(PB_LAYOUT_GRID_OWNED_COLUMN);
    const collapsed = [];

    columns.forEach((column) => {
      if (column.hasAttribute('data-collapsed')) {
        const colName = column.getAttribute('data-col');
        if (colName) {
          collapsed.push(colName);
        }
      }
    });

    paragraph.classList.remove(...COLLAPSE_CLASSES);

    if (collapsed.length === 0) {
      return;
    }

    collapsed.sort(
      (a, b) => COL_ORDER.indexOf(a) - COL_ORDER.indexOf(b),
    );

    const cls = collapsed.length === 1
      ? `${collapsed[0]}-is-collapsed`
      : `${collapsed.join('-')}-collapsed`;

    if (COLLAPSE_CLASSES.includes(cls)) {
      paragraph.classList.add(cls);
    }
  };

  /**
   * @param {number} sx
   * @param {number} sy
   */
  const restoreScrollAfterLayout = (sx, sy) => {
    window.requestAnimationFrame(() => {
      window.scrollTo(sx, sy);
      window.requestAnimationFrame(() => {
        window.scrollTo(sx, sy);
      });
    });
  };

  /**
   * @param {HTMLElement} grid
   *
   * @return {number[]}
   */
  const parseRatios = (grid) => {
    const classes = grid.className.split(/\s+/);
    for (const className of classes) {
      const match = className.match(
        /^pb-(?:two|three)-columns--l(\d+)(?:-l(\d+))?(?:-l(\d+))?$/,
      );
      if (match) {
        const ratios = [parseInt(match[1], 10)];
        if (match[2]) {
          ratios.push(parseInt(match[2], 10));
        }
        if (match[3]) {
          ratios.push(parseInt(match[3], 10));
        }
        return ratios;
      }
    }

    const count = grid.querySelectorAll(PB_LAYOUT_GRID_OWNED_COLUMN).length;
    return Array.from({ length: count }, () => 1);
  };

  /**
   * @param {HTMLElement} grid
   *
   * @return {HTMLElement|null}
   */
  const getToolbar = (grid) => {
    const prev = grid.previousElementSibling;
    return prev instanceof HTMLElement
      && prev.classList.contains(PB_LAYOUT_COL__TOOLBAR)
      ? prev
      : null;
  };

  /**
   * @param {HTMLElement} grid
   * @param {number[]} ratios
   */
  const setGridColumns = (grid, ratios) => {
    if (!ratios.length) {
      return;
    }

    const columns = grid.querySelectorAll(PB_LAYOUT_GRID_OWNED_COLUMN);
    const expand = grid.getAttribute('data-col-expand') === '1';
    let visibleCount = 0;
    let hasCollapsed = false;
    const parts = [];

    columns.forEach((column, index) => {
      if (isCollapsed(column)) {
        parts.push('0fr');
        hasCollapsed = true;
      }
      else {
        parts.push(`${ratios[index] || 1}fr`);
        visibleCount += 1;
      }
    });

    const singleVisible = expand && visibleCount === 1 && hasCollapsed;
    const twoVisibleInThree =
      columns.length === 3 && visibleCount === 2 && hasCollapsed;

    grid.classList.toggle('pb-has-collapsed-col', hasCollapsed);
    grid.classList.toggle('pb-has-single-visible-col', singleVisible);
    grid.classList.toggle('pb-has-two-visible-cols', twoVisibleInThree);

    if (!hasCollapsed) {
      grid.style.gridTemplateColumns = parts.join(' ');
      return;
    }

    if (singleVisible) {
      const min = grid.getAttribute('data-col-layout-min') || '62rem';
      let mqMatches = true;

      try {
        mqMatches = window.matchMedia(`(min-width: ${min})`).matches;
      }
      catch {
        mqMatches = true;
      }

      if (mqMatches) {
        for (let i = 0; i < parts.length; i += 1) {
          if (parts[i] !== '0fr') {
            parts[i] = '1fr';
          }
        }
      }
    }

    grid.style.gridTemplateColumns = parts.join(' ');
  };

  /**
   * @param {HTMLElement} toolbar
   * @param {string} colName
   *
   * @return {HTMLElement|null}
   */
  const getToggleButton = (toolbar, colName) => {
    const button = toolbar.querySelector(`[data-col-toggle="${colName}"]`);
    return button instanceof HTMLElement ? button : null;
  };

  /**
   * @param {HTMLElement} col
   * @param {HTMLElement} toggleButton
   * @param {HTMLElement} content
   * @param {boolean} announce
   */
  const syncAriaState = (col, toggleButton, content, announce = false) => {
    const collapsed = isCollapsed(col);
    toggleButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    content.setAttribute('aria-hidden', collapsed ? 'true' : 'false');
    content.inert = collapsed;

    const expandLabel = toggleButton.getAttribute('data-label-expand');
    const collapseLabel = toggleButton.getAttribute('data-label-collapse');
    const actionLabel = collapsed ? expandLabel : collapseLabel;

    if (actionLabel) {
      toggleButton.setAttribute('aria-label', actionLabel);
      toggleButton.setAttribute('title', actionLabel);
      if (announce) {
        Drupal.announce(actionLabel, 'polite');
      }
    }
  };

  /**
   * @param {HTMLElement} grid
   */
  const expandAllColumns = (grid) => {
    const toolbar = getToolbar(grid);
    const columns = grid.querySelectorAll(PB_LAYOUT_GRID_OWNED_COLUMN);

    columns.forEach((col) => {
      setCollapsed(col, false);
      const colName = col.getAttribute('data-col');
      const btn = colName && toolbar ? getToggleButton(toolbar, colName) : null;
      const content = col.querySelector(PB_LAYOUT_COLUMN_OWNED_CONTENT);

      if (btn) {
        btn.removeAttribute('data-collapsed');
      }
      if (btn && content instanceof HTMLElement) {
        syncAriaState(col, btn, content);
      }
    });

    updateCollapseClass(grid);
    grid.style.removeProperty('grid-template-columns');
    grid.classList.remove(
      'pb-has-collapsed-col',
      'pb-has-single-visible-col',
      'pb-has-two-visible-cols',
    );
  };

  /**
   * @param {HTMLElement} grid
   * @param {number[]} ratios
   */
  const restoreDefaultStates = (grid, ratios) => {
    const toolbar = getToolbar(grid);
    const columns = grid.querySelectorAll(PB_LAYOUT_GRID_OWNED_COLUMN);

    columns.forEach((col) => {
      const def = col.getAttribute('data-col-default') || 'open';
      const collapsed = def === 'closed';
      setCollapsed(col, collapsed);
      const colName = col.getAttribute('data-col');
      const btn = colName && toolbar ? getToggleButton(toolbar, colName) : null;
      const content = col.querySelector(PB_LAYOUT_COLUMN_OWNED_CONTENT);

      if (btn) {
        if (collapsed) {
          btn.setAttribute('data-collapsed', '');
        }
        else {
          btn.removeAttribute('data-collapsed');
        }
      }
      if (btn && content instanceof HTMLElement) {
        syncAriaState(col, btn, content);
      }
    });

    updateCollapseClass(grid);
    setGridColumns(grid, ratios);
  };

  /**
   * @param {HTMLElement} grid
   */
  const initToggles = (grid) => {
    const iconStyle = grid.getAttribute('data-col-icon-style') || 'chevron';
    const iconPos = grid.getAttribute('data-col-icon-position') || 'edge';
    const anim = grid.getAttribute('data-col-anim') || 'slide';
    const animMs = parseInt(grid.getAttribute('data-col-anim-ms') || '300', 10);
    const stickyTop = parseInt(grid.getAttribute('data-col-sticky-top') || '0', 10);
    const layoutMin = grid.getAttribute('data-col-layout-min') || '62rem';
    const toolbar = getToolbar(grid);

    if (!toolbar) {
      return;
    }

    const abortController = new AbortController();
    const { signal } = abortController;
    gridAbortControllers.set(grid, abortController);

    const ratios = parseRatios(grid);
    let mq = null;

    try {
      mq = window.matchMedia(`(min-width: ${layoutMin})`);
    }
    catch {
      mq = null;
    }

    let aboveBreakpoint = mq ? mq.matches : true;

    const iconClasses = [
      `pb-layout-col__icon--${toBemToken(iconStyle)}`,
      `pb-layout-col__icon-pos--${toBemToken(iconPos)}`,
      `pb-col-anim-${toBemToken(anim)}`,
    ];
    grid.classList.add(...iconClasses);
    toolbar.classList.add(...iconClasses);

    if (anim === 'none' || animMs <= 0) {
      grid.style.setProperty('--pb-col-anim-ms', '0ms');
      toolbar.style.setProperty('--pb-col-anim-ms', '0ms');
    }
    else if (!Number.isNaN(animMs)) {
      const animValue = `${animMs}ms`;
      grid.style.setProperty('--pb-col-anim-ms', animValue);
      toolbar.style.setProperty('--pb-col-anim-ms', animValue);
    }

    if (!Number.isNaN(stickyTop)) {
      grid.style.setProperty('--pb-col-sticky-top', `${stickyTop}px`);
    }

    const columns = grid.querySelectorAll(PB_LAYOUT_GRID_OWNED_COLUMN);
    columns.forEach((col) => {
      if (col.classList.contains(PB_IS_COLLAPSED)) {
        col.classList.remove(PB_IS_COLLAPSED);
        if (aboveBreakpoint) {
          setCollapsed(col, true);
          const colName = col.getAttribute('data-col');
          const btn = colName ? getToggleButton(toolbar, colName) : null;
          if (btn) {
            btn.setAttribute('data-collapsed', '');
          }
        }
      }
    });

    if (aboveBreakpoint) {
      updateCollapseClass(grid);
      setGridColumns(grid, ratios);
    }
    else {
      expandAllColumns(grid);
    }

    columns.forEach((col) => {
      const toggleEnabled = col.getAttribute('data-col-toggle-enabled') === '1';
      const colName = col.getAttribute('data-col');
      const toggleButton = colName ? getToggleButton(toolbar, colName) : null;
      const content = col.querySelector(PB_LAYOUT_COLUMN_OWNED_CONTENT);

      if (!(content instanceof HTMLElement) || !toggleEnabled || !toggleButton) {
        return;
      }

      syncAriaState(col, toggleButton, content);

      toggleButton.addEventListener('click', () => {
        if (!aboveBreakpoint) {
          return;
        }

        const sx = window.scrollX;
        const sy = window.scrollY;
        const collapsing = !isCollapsed(col);

        setCollapsed(col, collapsing);
        toggleButton.toggleAttribute('data-collapsed', collapsing);
        updateCollapseClass(grid);
        syncAriaState(col, toggleButton, content, true);
        setGridColumns(grid, ratios);
        restoreScrollAfterLayout(sx, sy);
      }, { signal });
    });

    if (mq) {
      const onBreakpointChange = (event) => {
        aboveBreakpoint = event.matches;
        if (aboveBreakpoint) {
          restoreDefaultStates(grid, ratios);
        }
        else {
          expandAllColumns(grid);
        }
      };

      mq.addEventListener('change', onBreakpointChange, { signal });
    }
  };

  /**
   * @param {HTMLElement} stickyEl
   */
  const fixStickyAncestors = (stickyEl) => {
    const paragraph = stickyEl.closest(
      `${PARAGRAPHS_BUNDLES_PB_LAYOUT_PB_TWO_COLUMNS}, ${PARAGRAPHS_BUNDLES_PB_LAYOUT_PB_THREE_COLUMNS}`,
    );

    if (!(paragraph instanceof HTMLElement)) {
      return;
    }

    let el = stickyEl.parentElement;
    while (el && paragraph.contains(el)) {
      if (getComputedStyle(el).overflow !== 'visible') {
        el.style.overflow = 'visible';
      }
      el = el.parentElement;
    }
  };

  Drupal.behaviors.paragraphBundleLayout = {
    attach(context) {
      once(
        ONCE_PB_PARAGRAPH_BUNDLE_LAYOUT_COLUMN,
        LAYOUT_COLUMN_SELECTOR,
        context,
      ).forEach((element) => {
        const contentEl = element.querySelector(PB_LAYOUT_COLUMN_OWNED_CONTENT);
        const target = contentEl ?? element;
        if (target.children.length > 1) {
          element.classList.add('pb-has-multiple');
        }
      });

      once(
        ONCE_PB_PARAGRAPH_BUNDLE_LAYOUT_GRID,
        LAYOUT_GRID_SELECTOR,
        context,
      ).forEach(initToggles);

      once(
        ONCE_PB_PARAGRAPH_BUNDLE_LAYOUT_STICKY,
        LAYOUT_STICKY_SELECTOR,
        context,
      ).forEach(fixStickyAncestors);
    },

    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }

      once.remove(
        ONCE_PB_PARAGRAPH_BUNDLE_LAYOUT_COLUMN,
        LAYOUT_COLUMN_SELECTOR,
        context,
      );
      once.remove(
        ONCE_PB_PARAGRAPH_BUNDLE_LAYOUT_GRID,
        LAYOUT_GRID_SELECTOR,
        context,
      ).forEach((grid) => {
        gridAbortControllers.get(grid)?.abort();
        gridAbortControllers.delete(grid);
      });
      once.remove(
        ONCE_PB_PARAGRAPH_BUNDLE_LAYOUT_STICKY,
        LAYOUT_STICKY_SELECTOR,
        context,
      );
    },
  };
})(Drupal, once);
