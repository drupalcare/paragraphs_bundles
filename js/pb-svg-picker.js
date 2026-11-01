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
   * Builds one picker around a radios wrapper.
   *
   * @param {HTMLElement} radios
   *   The element carrying data-pb-svg-picker.
   */
  function build(radios) {
    const t = strings();
    const items = Array.from(radios.querySelectorAll('.pb-svg-picker__item'));
    const none = Array.from(radios.children).find(
      (el) => el.querySelector('input[type="radio"][value="_none"]') !== null,
    );

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'pb-svg-picker__trigger';
    trigger.setAttribute('aria-expanded', 'false');

    const panel = document.createElement('div');
    panel.className = 'pb-svg-picker__panel';
    panel.hidden = true;
    const panelId = `${radios.id || 'pb-svg-picker'}-panel`;
    panel.id = panelId;
    trigger.setAttribute('aria-controls', panelId);

    const searchRow = document.createElement('div');
    searchRow.className = 'pb-svg-picker__search';
    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'form-text pb-svg-picker__search-input';
    search.placeholder = t.search || '';
    search.setAttribute('aria-label', t.search || '');
    const clear = document.createElement('button');
    clear.type = 'button';
    clear.className = 'pb-svg-picker__clear';
    clear.textContent = '×';
    clear.setAttribute('aria-label', t.clear || '');
    clear.hidden = true;
    searchRow.append(search, clear);

    const count = document.createElement('p');
    count.className = 'pb-svg-picker__count';
    count.setAttribute('aria-live', 'polite');

    const empty = document.createElement('p');
    empty.className = 'pb-svg-picker__empty';
    empty.textContent = t.empty || '';
    empty.hidden = true;

    const rows = document.createElement('div');
    rows.className = 'pb-svg-picker__rows';
    if (none) {
      none.classList.add('pb-svg-picker__item', 'pb-svg-picker__item--none');
      const label = none.querySelector('label');
      if (label) {
        label.textContent = t.none || label.textContent;
      }
      rows.append(none);
    }
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

    panel.append(searchRow, count, empty, rows);
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
      clear.hidden = query === '';
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
      if (none) {
        none.hidden = query !== '';
      }
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
      panel.hidden = !open;
      trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open) {
        search.focus();
      }
    }

    trigger.addEventListener('click', () => toggle(panel.hidden));
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
      if (!panel.hidden && !radios.contains(event.target)) {
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
