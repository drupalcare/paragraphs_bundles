/**
 * @file
 * Paragraph Bundle Layout — column toggles, expand, sticky.
 *
 * Filename:     paragraph-bundle-layout.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
(function (Drupal, once) {
  'use strict';

  /**
   * All possible collapse state classes (mutually exclusive).
   *
   * @type {string[]}
   */
  var COLLAPSE_CLASSES = [
    'left-is-collapsed',
    'right-is-collapsed',
    'middle-is-collapsed',
    'left-right-collapsed',
    'left-middle-collapsed',
    'right-middle-collapsed',
    'left-right-middle-collapsed',
  ];

  /**
   * Column sort order for building class names.
   *
   * @type {string[]}
   */
  var COL_ORDER = ['left', 'right', 'middle'];

  /**
   * Check whether a column is in collapsed state.
   *
   * @param {HTMLElement} col
   *   The column element.
   *
   * @return {boolean}
   *   True if the column is collapsed.
   */
  function isCollapsed(col) {
    return col.hasAttribute('data-collapsed');
  }

  /**
   * Set or remove the collapsed state on a column.
   *
   * @param {HTMLElement} col
   *   The column element.
   * @param {boolean} collapsed
   *   True to collapse, false to expand.
   */
  function setCollapsed(col, collapsed) {
    if (collapsed) {
      col.setAttribute('data-collapsed', '');
    }
    else {
      col.removeAttribute('data-collapsed');
    }
  }

  /**
   * Compute and apply the single collapse state class on the .paragraph wrapper.
   *
   * Reads which columns have [data-collapsed], builds the class name
   * (e.g. "left-is-collapsed", "left-right-collapsed"), and sets it
   * on the closest .paragraph ancestor. Removes all other state classes first.
   *
   * @param {HTMLElement} grid
   *   The grid container (.pb__two-columns or .pb__three-columns).
   */
  function updateCollapseClass(grid) {
    var paragraph = grid.closest('.paragraph');
    if (!paragraph) {
      return;
    }

    var columns = grid.querySelectorAll(':scope > [data-col]');
    var collapsed = [];
    var i;

    for (i = 0; i < columns.length; i++) {
      if (columns[i].hasAttribute('data-collapsed')) {
        collapsed.push(columns[i].getAttribute('data-col'));
      }
    }

    paragraph.classList.remove.apply(paragraph.classList, COLLAPSE_CLASSES);

    if (collapsed.length === 0) {
      return;
    }

    collapsed.sort(function (a, b) {
      return COL_ORDER.indexOf(a) - COL_ORDER.indexOf(b);
    });

    var cls = collapsed.length === 1
      ? collapsed[0] + '-is-collapsed'
      : collapsed.join('-') + '-collapsed';

    if (COLLAPSE_CLASSES.indexOf(cls) !== -1) {
      paragraph.classList.add(cls);
    }
  }

  /**
   * Restore viewport scroll after layout changes (focus / height / grid).
   *
   * @param {number} sx
   *   scrollX captured at interaction start.
   * @param {number} sy
   *   scrollY captured at interaction start.
   */
  function restoreScrollAfterLayout(sx, sy) {
    window.requestAnimationFrame(function () {
      window.scrollTo(sx, sy);
      window.requestAnimationFrame(function () {
        window.scrollTo(sx, sy);
      });
    });
  }

  /**
   * Update grid-template-columns for expand mode.
   *
   * When expand is enabled and only one column is visible, collapsed
   * tracks are set to 0fr so the visible column fills the width.
   *
   * @param {HTMLElement} grid
   *   The grid container.
   */
  function updateExpandedGrid(grid) {
    if (!grid) {
      return;
    }

    var expand = grid.getAttribute('data-col-expand') === '1';
    var columns = grid.querySelectorAll(':scope > [data-col]');
    var visibleCount = 0;
    var i;

    for (i = 0; i < columns.length; i++) {
      if (!isCollapsed(columns[i])) {
        visibleCount++;
      }
    }

    var singleVisible = expand && visibleCount === 1;
    grid.classList.toggle('has-single-visible-col', singleVisible);

    if (!expand) {
      grid.style.removeProperty('grid-template-columns');
      return;
    }

    var min = grid.getAttribute('data-col-layout-min') || '62rem';
    var mqMatches = false;

    try {
      mqMatches = window.matchMedia('(min-width: ' + min + ')').matches;
    }
    catch (e) {
      mqMatches = true;
    }

    if (!singleVisible || !mqMatches) {
      grid.style.removeProperty('grid-template-columns');
      return;
    }

    var parts = [];
    for (i = 0; i < columns.length; i++) {
      parts.push(isCollapsed(columns[i]) ? '0fr' : 'minmax(0, 1fr)');
    }
    grid.style.gridTemplateColumns = parts.join(' ');
  }

  /**
   * Animate column content using the Web Animations API.
   *
   * @param {HTMLElement} content
   *   The .pb-col-content element.
   * @param {boolean} collapsing
   *   True when collapsing, false when expanding.
   * @param {string} anim
   *   Animation type: 'slide', 'fade', 'slide_fade', or 'none'.
   * @param {number} animMs
   *   Duration in milliseconds.
   *
   * @return {Promise}
   *   Resolves when the animation finishes or is skipped.
   */
  function animateContent(content, collapsing, anim, animMs) {
    var prefersReduced =
      window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (
      !content ||
      animMs <= 0 ||
      anim === 'none' ||
      prefersReduced ||
      typeof content.animate !== 'function'
    ) {
      return Promise.resolve();
    }

    var height = content.scrollHeight;
    var useSlide = anim === 'slide' || anim === 'slide_fade';
    var useFade = anim === 'fade' || anim === 'slide_fade';

    var fromState = {
      maxHeight: collapsing ? height + 'px' : '0px',
      overflow: 'hidden',
    };
    var toState = {
      maxHeight: collapsing ? '0px' : height + 'px',
      overflow: 'hidden',
    };

    if (useFade) {
      fromState.opacity = collapsing ? 1 : 0;
      toState.opacity = collapsing ? 0 : 1;
    }
    if (useSlide) {
      fromState.transform = collapsing
        ? 'translateY(0)'
        : 'translateY(-6px)';
      toState.transform = collapsing
        ? 'translateY(-6px)'
        : 'translateY(0)';
    }

    var animation = content.animate([fromState, toState], {
      duration: animMs,
      easing: 'ease',
      fill: 'forwards',
    });

    return new Promise(function (resolve) {
      animation.onfinish = function () {
        animation.cancel();
        resolve();
      };
      animation.oncancel = function () {
        resolve();
      };
    });
  }

  /**
   * Synchronize ARIA attributes on a toggle button and its content.
   *
   * @param {HTMLElement} col
   *   The column element.
   * @param {HTMLElement} toggleButton
   *   The toggle button.
   * @param {HTMLElement} content
   *   The .pb-col-content element.
   */
  function syncAriaState(col, toggleButton, content) {
    var collapsed = isCollapsed(col);
    toggleButton.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    content.setAttribute('aria-hidden', collapsed ? 'true' : 'false');
  }

  /**
   * Find the toolbar sibling for a grid container.
   *
   * @param {HTMLElement} grid
   *   The grid container.
   *
   * @return {HTMLElement|null}
   *   The toolbar element, or null.
   */
  function getToolbar(grid) {
    var prev = grid.previousElementSibling;
    return prev && prev.classList.contains('pb-col-toolbar') ? prev : null;
  }

  /**
   * Find the toggle button for a given column name in the toolbar.
   *
   * @param {HTMLElement} toolbar
   *   The toolbar element.
   * @param {string} colName
   *   The column name (left, right, middle).
   *
   * @return {HTMLElement|null}
   *   The button element, or null.
   */
  function getToggleButton(toolbar, colName) {
    return toolbar
      ? toolbar.querySelector('[data-col-toggle="' + colName + '"]')
      : null;
  }

  /**
   * Expand all columns and remove collapse state classes.
   *
   * Used when the viewport is below the layout breakpoint so all
   * content is visible in the stacked mobile layout.
   *
   * @param {HTMLElement} grid
   *   The grid container.
   */
  function expandAllColumns(grid) {
    var toolbar = getToolbar(grid);
    var columns = grid.querySelectorAll(':scope > [data-col]');
    var i;
    for (i = 0; i < columns.length; i++) {
      setCollapsed(columns[i], false);
      var colName = columns[i].getAttribute('data-col');
      var btn = getToggleButton(toolbar, colName);
      var cnt = columns[i].querySelector(':scope > .pb-col-content');
      if (btn) {
        btn.removeAttribute('data-collapsed');
      }
      if (btn && cnt) {
        syncAriaState(columns[i], btn, cnt);
      }
    }
    updateCollapseClass(grid);
    updateExpandedGrid(grid);
  }

  /**
   * Restore each column to its configured default state.
   *
   * Reads data-col-default ("open" or "closed") from each column
   * and applies the corresponding collapsed state.
   *
   * @param {HTMLElement} grid
   *   The grid container.
   */
  function restoreDefaultStates(grid) {
    var toolbar = getToolbar(grid);
    var columns = grid.querySelectorAll(':scope > [data-col]');
    var i;
    for (i = 0; i < columns.length; i++) {
      var def = columns[i].getAttribute('data-col-default') || 'open';
      var collapsed = def === 'closed';
      setCollapsed(columns[i], collapsed);
      var colName = columns[i].getAttribute('data-col');
      var btn = getToggleButton(toolbar, colName);
      var cnt = columns[i].querySelector(':scope > .pb-col-content');
      if (btn) {
        if (collapsed) {
          btn.setAttribute('data-collapsed', '');
        }
        else {
          btn.removeAttribute('data-collapsed');
        }
      }
      if (btn && cnt) {
        syncAriaState(columns[i], btn, cnt);
      }
    }
    updateCollapseClass(grid);
    updateExpandedGrid(grid);
  }

  /**
   * Initialize toggle buttons for a two- or three-column grid.
   *
   * Reads configuration from data attributes on the grid, sets up
   * click handlers with animation, and manages collapse state classes.
   * Toggle functionality is disabled below the layout breakpoint.
   *
   * @param {HTMLElement} grid
   *   The grid container (.pb__two-columns or .pb__three-columns).
   */
  function initToggles(grid) {
    var iconStyle = grid.getAttribute('data-col-icon-style') || 'chevron';
    var iconPos = grid.getAttribute('data-col-icon-position') || 'edge';
    var anim = grid.getAttribute('data-col-anim') || 'slide';
    var animMs = parseInt(grid.getAttribute('data-col-anim-ms') || '300', 10);
    var stickyTop = parseInt(grid.getAttribute('data-col-sticky-top') || '0', 10);
    var layoutMin = grid.getAttribute('data-col-layout-min') || '62rem';
    var toolbar = getToolbar(grid);

    // No toolbar means no toggles are enabled — nothing to do.
    if (!toolbar) {
      return;
    }

    /** @type {MediaQueryList|null} */
    var mq = null;
    try {
      mq = window.matchMedia('(min-width: ' + layoutMin + ')');
    }
    catch (e) {
      // Invalid media query — treat as always above breakpoint.
    }

    /**
     * Whether the viewport is currently above the layout breakpoint.
     *
     * @type {boolean}
     */
    var aboveBreakpoint = mq ? mq.matches : true;

    // Add icon/anim classes to both toolbar and grid.
    var iconClasses = [
      'pb-col-icon-' + iconStyle,
      'pb-col-icon-pos-' + iconPos,
      'pb-col-anim-' + anim
    ];
    grid.classList.add.apply(grid.classList, iconClasses);
    toolbar.classList.add.apply(toolbar.classList, iconClasses);

    if (!Number.isNaN(animMs)) {
      grid.style.setProperty('--pb-col-anim-ms', animMs + 'ms');
      toolbar.style.setProperty('--pb-col-anim-ms', animMs + 'ms');
    }
    if (!Number.isNaN(stickyTop)) {
      grid.style.setProperty('--pb-col-sticky-top', stickyTop + 'px');
    }

    // Convert Twig-rendered is-collapsed classes to data-collapsed attributes.
    var columns = grid.querySelectorAll(':scope > [data-col]');
    var j;
    for (j = 0; j < columns.length; j++) {
      if (columns[j].classList.contains('is-collapsed')) {
        columns[j].classList.remove('is-collapsed');
        // Only apply collapse if above breakpoint.
        if (aboveBreakpoint) {
          setCollapsed(columns[j], true);
          // Mirror data-collapsed on the toolbar button.
          var colName = columns[j].getAttribute('data-col');
          var btn = getToggleButton(toolbar, colName);
          if (btn) {
            btn.setAttribute('data-collapsed', '');
          }
        }
      }
    }

    // Enable or disable collapse feature based on breakpoint.
    if (aboveBreakpoint) {
      updateCollapseClass(grid);
    }
    else {
      expandAllColumns(grid);
    }

    // Bind toggle buttons (found in toolbar, mapped to columns by name).
    for (j = 0; j < columns.length; j++) {
      (function (col) {
        var toggleEnabled =
          col.getAttribute('data-col-toggle-enabled') === '1';
        var colName = col.getAttribute('data-col');
        var toggleButton = getToggleButton(toolbar, colName);
        var content = col.querySelector(':scope > .pb-col-content');

        if (!content || !toggleEnabled || !toggleButton) {
          return;
        }

        var animating = false;

        syncAriaState(col, toggleButton, content);

        toggleButton.addEventListener('click', function () {
          // Do nothing below the layout breakpoint.
          if (!aboveBreakpoint || animating) {
            return;
          }

          var sx = window.scrollX;
          var sy = window.scrollY;
          var collapsing = !isCollapsed(col);
          var shouldAnimate = anim !== 'none' && animMs > 0;

          if (!shouldAnimate) {
            setCollapsed(col, collapsing);
            toggleButton.toggleAttribute('data-collapsed', collapsing);
            updateCollapseClass(grid);
            syncAriaState(col, toggleButton, content);
            updateExpandedGrid(grid);
            restoreScrollAfterLayout(sx, sy);
            return;
          }

          animating = true;

          if (collapsing) {
            animateContent(content, true, anim, animMs).then(function () {
              setCollapsed(col, true);
              toggleButton.setAttribute('data-collapsed', '');
              updateCollapseClass(grid);
              syncAriaState(col, toggleButton, content);
              updateExpandedGrid(grid);
              restoreScrollAfterLayout(sx, sy);
              animating = false;
            });
          }
          else {
            setCollapsed(col, false);
            toggleButton.removeAttribute('data-collapsed');
            updateCollapseClass(grid);
            syncAriaState(col, toggleButton, content);
            updateExpandedGrid(grid);
            restoreScrollAfterLayout(sx, sy);
            animateContent(content, false, anim, animMs).then(function () {
              restoreScrollAfterLayout(sx, sy);
              animating = false;
            });
          }
        });
      })(columns[j]);
    }

    updateExpandedGrid(grid);

    // Listen for breakpoint changes: toggle feature on/off.
    if (mq && typeof mq.addEventListener === 'function') {
      mq.addEventListener('change', function (e) {
        aboveBreakpoint = e.matches;
        if (aboveBreakpoint) {
          // Crossed above breakpoint: restore user-configured defaults.
          restoreDefaultStates(grid);
        }
        else {
          // Crossed below breakpoint: expand all, disable toggles.
          expandAllColumns(grid);
        }
      });
    }
  }

  /**
   * Force overflow:visible on ancestors that break position:sticky.
   *
   * Walks up the DOM tree from the sticky element and overrides any
   * ancestor with overflow other than 'visible'.
   *
   * @param {HTMLElement} stickyEl
   *   The sticky column element.
   */
  function fixStickyAncestors(stickyEl) {
    var paragraph = stickyEl.closest('.paragraph');
    var el = stickyEl.parentElement;
    while (el && paragraph && paragraph.contains(el)) {
      if (getComputedStyle(el).overflow !== 'visible') {
        el.style.overflow = 'visible';
      }
      el = el.parentElement;
    }
  }

  /**
   * Drupal behavior for paragraph bundle layout.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.paragraphBundleLayout = {
    attach: function (context) {
      // Detect multi-paragraph columns (plus-one class).
      once(
        'paragraphBundleLayout',
        '.pb__two-columns:not(.pb-col-toolbar) > div, .pb__three-columns:not(.pb-col-toolbar) > div',
        context
      ).forEach(function (element) {
        var contentEl = element.querySelector(':scope > .pb-col-content');
        var target = contentEl || element;
        if (target.children.length > 1) {
          element.classList.add('plus-one');
        }
      });

      // Initialize column toggles.
      once(
        'paragraphBundleLayoutToggle',
        '.pb__two-columns:not(.pb-col-toolbar), .pb__three-columns:not(.pb-col-toolbar)',
        context
      ).forEach(initToggles);

      // Fix sticky ancestors.
      once(
        'paragraphBundleLayoutSticky',
        '[data-col].is-sticky',
        context
      ).forEach(fixStickyAncestors);
    },
  };
})(Drupal, once);
