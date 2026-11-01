/**
 * @file
 * SVG picker: a dropdown with live search and one row of previews per theme.
 *
 * The choices stay core radio buttons (visually hidden), so keyboard use,
 * screen readers and form submission are core's. Without JavaScript the radios
 * show as a plain list.
 */
((Drupal, drupalSettings, once) => {
  /**
   * Translated strings from PbSvgPickerWidget.
   *
   * @return {Object<string, string>}
   *   The strings.
   */
  function strings() {
    return (
      (drupalSettings.pbSvgPicker && drupalSettings.pbSvgPicker.strings) || {}
    );
  }

  /**
   * Replaces @placeholders in a string.
   *
   * @param {string} text
   *   The text.
   * @param {Object<string, number>} args
   *   Placeholder values.
   *
   * @return {string}
   *   The text with values.
   */
  function format(text, args) {
    return Object.keys(args).reduce(
      (out, key) => out.split(`@${key}`).join(String(args[key])),
      text || '',
    );
  }

  /**
   * Finds the admin theme's background behind an element.
   *
   * The panel floats over the form, so it needs a solid background; reading it
   * keeps the module from choosing a color.
   *
   * @param {HTMLElement} element
   *   The element.
   *
   * @return {string}
   *   A CSS color.
   */
  function adminBackground(element) {
    let node = element.parentElement;
    while (node) {
      const color = window.getComputedStyle(node).backgroundColor;
      if (color !== 'transparent' && !/,\s*0\)$|\/\s*0\)$/.test(color)) {
        return color;
      }
      node = node.parentElement;
    }
    return 'Canvas';
  }

  /**
   * Builds one picker around a radios wrapper.
   *
   * @param {HTMLElement} radios
   *   The element carrying data-pb-svg-picker.
   */
  function build(radios) {
    const t = strings();
    const items = Array.from(radios.querySelectorAll('.pb-svg-picker__item'));
    // The radio's own wrapper: a parent would take the description with it.
    const noneInput = radios.querySelector(
      'input[type="radio"][value="_none"]',
    );
    const none = noneInput
      ? noneInput.closest('.js-form-item') || noneInput.parentElement
      : null;
    const describedBy = radios.getAttribute('aria-describedby');
    const description =
      (describedBy && document.getElementById(describedBy)) ||
      radios.querySelector(
        '.description, .fieldset__description, .form-item__description',
      );

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'button pb-svg-picker__trigger';
    trigger.setAttribute('aria-expanded', 'false');

    const panel = document.createElement('div');
    panel.className = 'pb-svg-picker__panel';
    panel.hidden = true;
    const panelId = `${radios.id || 'pb-svg-picker'}-panel`;
    panel.id = panelId;
    trigger.setAttribute('aria-controls', panelId);

    const searchRow = document.createElement('div');
    searchRow.className = 'pb-svg-picker__search';
    const count = document.createElement('span');
    count.className = 'pb-svg-picker__count';
    count.setAttribute('aria-live', 'polite');
    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'form-text form-element pb-svg-picker__search-input';
    search.placeholder = t.search || '';
    search.setAttribute('aria-label', t.search || '');
    const clear = document.createElement('button');
    clear.type = 'button';
    clear.className = 'button pb-svg-picker__clear';
    clear.textContent = '×';
    clear.setAttribute('aria-label', t.clear || '');
    clear.disabled = true;
    searchRow.append(count, search, clear);

    const empty = document.createElement('p');
    empty.className = 'pb-svg-picker__empty';
    empty.textContent = t.empty || '';
    empty.hidden = true;

    const tip = document.createElement('span');
    tip.className = 'pb-svg-picker__tip';
    tip.setAttribute('aria-hidden', 'true');
    tip.hidden = true;
    let tipItem = null;
    let isOpen = false;
    let closeTimer = 0;

    const rows = document.createElement('div');
    rows.className = 'pb-svg-picker__rows';
    // The script shows its own name on hover, so the browser's would double it.
    radios.querySelectorAll('.pb-svg-picker__tile[title]').forEach((tile) => {
      tile.removeAttribute('title');
    });
    // One row per theme, in the server's order (default theme first).
    const sections = new Map();
    items.forEach((item) => {
      const theme = item.dataset.pbSvgTheme || '';
      if (!sections.has(theme)) {
        const section = document.createElement('section');
        section.className = 'pb-svg-picker__theme';
        const heading = document.createElement('h3');
        heading.className = 'pb-svg-picker__theme-title';
        heading.textContent = theme;
        const grid = document.createElement('div');
        grid.className = 'pb-svg-picker__grid';
        section.append(heading, grid);
        rows.append(section);
        sections.set(theme, { section, grid });
      }
      sections.get(theme).grid.append(item);
    });

    // "No SVG" is the first icon: a crossed circle, named on hover like the rest.
    if (none) {
      none.classList.add('pb-svg-picker__item', 'pb-svg-picker__item--none');
      const label = none.querySelector('label');
      if (label) {
        const name = document.createElement('span');
        name.className = 'pb-svg-picker__name visually-hidden';
        name.textContent = t.none || label.textContent;
        label.textContent = '';
        label.innerHTML =
          '<svg class="pb-svg-picker__none-icon" viewBox="0 0 16 16" aria-hidden="true"><circle cx="8" cy="8" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M3 3l10 10" stroke="currentColor" stroke-width="2"/></svg>';
        label.append(name);
      }
      const first = sections.values().next().value;
      if (first) {
        first.grid.prepend(none);
      } else {
        const grid = document.createElement('div');
        grid.className = 'pb-svg-picker__grid';
        grid.append(none);
        rows.append(grid);
      }
    }

    panel.append(searchRow);
    if (description) {
      description.classList.add('pb-svg-picker__description');
      panel.append(description);
    }
    panel.append(empty, rows, tip);
    // Core's radios wrapper is empty now and would only add space.
    const emptyWrapper = radios.querySelector('.form-radios');
    if (emptyWrapper && !emptyWrapper.querySelector('input')) {
      emptyWrapper.hidden = true;
    }
    radios.classList.add('pb-svg-picker--enhanced');
    // After the fieldset's legend, which must stay its first child.
    const legend = radios.querySelector(':scope > legend');
    if (legend) {
      legend.after(trigger);
    } else {
      radios.prepend(trigger);
    }
    radios.append(panel);

    /**
     * Shows the chosen preview and name on the trigger.
     */
    function updateTrigger() {
      const checked = radios.querySelector('input[type="radio"]:checked');
      const item =
        checked && checked.value !== '_none'
          ? checked.closest('.pb-svg-picker__item')
          : null;
      trigger.textContent = '';
      if (item) {
        const img = item.querySelector('img');
        const name = item.querySelector('.pb-svg-picker__name');
        if (img) {
          trigger.append(img.cloneNode());
        }
        const span = document.createElement('span');
        span.textContent = name ? name.textContent : checked.value;
        trigger.append(span);
      } else {
        const span = document.createElement('span');
        span.textContent = t.choose || '';
        trigger.append(span);
      }
    }

    /**
     * Filters the previews as the user types and updates the count.
     */
    function filter() {
      const query = search.value.trim().toLowerCase();
      clear.disabled = query === '';
      if (none) {
        none.hidden = query !== '';
      }
      let shown = 0;
      items.forEach((item) => {
        const match =
          query === '' || (item.dataset.pbSvgSearch || '').includes(query);
        item.hidden = !match;
        if (match) {
          shown += 1;
        }
      });
      sections.forEach(({ section, grid }) => {
        section.hidden = !grid.querySelector(
          '.pb-svg-picker__item:not([hidden])',
        );
      });
      empty.hidden = shown !== 0;
      count.textContent =
        query === ''
          ? format(t.count, { count: items.length })
          : format(t.countFiltered, { shown, count: items.length });
    }

    /**
     * Opens or closes the panel.
     *
     * @param {boolean} open
     *   TRUE to open.
     */
    function toggle(open) {
      if (open === isOpen) {
        return;
      }
      isOpen = open;
      window.clearTimeout(closeTimer);
      tip.hidden = true;
      trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        panel.classList.remove('pb-svg-picker__panel--closing');
        panel.style.backgroundColor = adminBackground(radios);
        panel.hidden = false;
        search.focus();
        return;
      }
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        panel.hidden = true;
        return;
      }
      panel.classList.add('pb-svg-picker__panel--closing');
      closeTimer = window.setTimeout(() => {
        panel.hidden = true;
        panel.classList.remove('pb-svg-picker__panel--closing');
      }, 120);
    }

    /**
     * Shows an icon's name just above it, kept inside the panel's width.
     *
     * @param {HTMLElement} item
     *   The icon's wrapper.
     */
    function showTip(item) {
      const name = item.querySelector('.pb-svg-picker__name');
      tip.textContent = name ? name.textContent : '';
      // Measured at the left edge: its old place must not change its width.
      tip.style.left = '0';
      tip.hidden = false;
      const box = item.getBoundingClientRect();
      const outer = panel.getBoundingClientRect();
      const left = Math.max(
        0,
        Math.min(
          box.left - outer.left,
          panel.clientWidth - tip.offsetWidth - 4,
        ),
      );
      tip.style.left = `${left}px`;
      tip.style.top = `${box.top - outer.top + panel.scrollTop - tip.offsetHeight - 4}px`;
    }

    // The gap between two icons keeps the name, so it does not flicker.
    rows.addEventListener('mouseover', (event) => {
      const item = event.target.closest('.pb-svg-picker__item');
      if (item) {
        if (item !== tipItem) {
          tipItem = item;
          showTip(item);
        }
      } else if (!event.target.closest('.pb-svg-picker__grid')) {
        tipItem = null;
        tip.hidden = true;
      }
    });
    rows.addEventListener('mouseleave', () => {
      tipItem = null;
      tip.hidden = true;
    });
    rows.addEventListener('focusin', (event) => {
      const item = event.target.closest('.pb-svg-picker__item');
      if (item) {
        showTip(item);
      }
    });
    rows.addEventListener('focusout', () => {
      tipItem = null;
      tip.hidden = true;
    });

    trigger.addEventListener('click', () => toggle(!isOpen));
    search.addEventListener('input', filter);
    clear.addEventListener('click', () => {
      search.value = '';
      filter();
      search.focus();
    });
    radios.addEventListener('change', (event) => {
      if (event.target.matches('input[type="radio"]')) {
        updateTrigger();
      }
    });
    // A click (not a keyboard arrow) on a preview picks it and closes the list.
    rows.addEventListener('click', (event) => {
      if (event.target.closest('label') && event.detail > 0) {
        window.setTimeout(() => {
          toggle(false);
          trigger.focus();
        }, 0);
      }
    });
    panel.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        if (search.value !== '') {
          search.value = '';
          filter();
        } else {
          toggle(false);
          trigger.focus();
        }
      }
    });
    const closeOnOutsideClick = (event) => {
      // An Ajax rebuild replaces the form: this listener goes with the picker.
      if (!radios.isConnected) {
        document.removeEventListener('click', closeOnOutsideClick);
        return;
      }
      if (isOpen && !radios.contains(event.target)) {
        toggle(false);
      }
    };
    document.addEventListener('click', closeOnOutsideClick);

    updateTrigger();
    filter();
  }

  /**
   * Attaches the SVG picker.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.pbSvgPicker = {
    attach(context) {
      once('pb-svg-picker', '[data-pb-svg-picker]', context).forEach(build);
    },
  };
})(Drupal, drupalSettings, once);
