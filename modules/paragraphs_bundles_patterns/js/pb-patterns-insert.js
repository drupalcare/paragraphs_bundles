/**
 * @param Drupal
 * @param once
 * @file
 * The node form's PB Patterns picker: search, family chips, selected line.
 *
 * The same layout as the APEX picker. Enhancement only: without this file every card,
 * the one radio group and the Insert button still work, and the server
 * draws the "Added ..." line after an insert. This file builds the toolbar
 * from the DOM the server drew (one chip per family, read from the cards'
 * family tags), filters by hiding cards, writes "Selected: <name>" into the status line, and keeps
 * Insert from submitting while nothing is chosen.
 *
 * Insert is the server's one #ajax submit. Drupal binds its Ajax to
 * mousedown and keypress on the button and prevents the click, so the
 * "nothing chosen" guard listens in the CAPTURE phase on the picker and
 * stops all three before they reach the button. Focus and the screen-reader
 * announcement after an insert come from the Ajax response
 * (PatternInsertForm::ajax), not from here.
 *
 * @copyright Copyright (c) 2026 Flash Web Center, LLC
 * @license   SPDX-License-Identifier: GPL-2.0-or-later
 */

((Drupal, once) => {
  const ONCE_ID = 'pb-patterns-insert';
  const ROOT = '.pb-patterns-insert';
  const PREFIX = 'pb-patterns-insert';

  /**
   * Listener teardown per picker.
   *
   * @type {WeakMap<HTMLElement, AbortController>}
   */
  const controllers = new WeakMap();

  /**
   * Makes an element with a class and optional text.
   *
   * @param {string} tag
   *   The tag name.
   * @param {string} part
   *   The BEM element name after the block.
   * @param {string} [text]
   *   Text content.
   *
   * @return {HTMLElement}
   *   The element.
   */
  const make = (tag, part, text = '') => {
    const element = document.createElement(tag);
    element.className = `${PREFIX}__${part}`;
    if (text !== '') {
      element.textContent = text;
    }
    return element;
  };

  /**
   * The "N patterns" phrase.
   *
   * @param {number} count
   *   How many.
   *
   * @return {string}
   *   The phrase.
   */
  const patternCount = (count) =>
    Drupal.formatPlural(count, '1 pattern', '@count patterns');

  /**
   * Whether a key event is one that presses a button.
   *
   * @param {KeyboardEvent} event
   *   The event.
   *
   * @return {boolean}
   *   TRUE for Enter and Space.
   */
  const isPressKey = (event) => event.key === 'Enter' || event.key === ' ';

  /**
   * Sets up one picker.
   *
   * @param {HTMLElement} root
   *   The picker's details element.
   */
  const init = (root) => {
    const list = root.querySelector(`.${PREFIX}__list`);
    const options = [
      ...root.querySelectorAll(`.${PREFIX}__option[data-family]`),
    ];
    const status = root.querySelector(`.${PREFIX}__status`);
    const submit = root.querySelector(`.${PREFIX}__submit`);
    if (!list || options.length === 0 || !status || !submit) {
      return;
    }
    const controller = new AbortController();
    controllers.set(root, controller);
    const { signal } = controller;

    // One entry per card: its family and its searchable words.
    const cards = options.map((option) => ({
      option,
      family: option.dataset.family,
      words: [
        ...option.querySelectorAll(
          `.${PREFIX}__tag, .${PREFIX}__title, .${PREFIX}__description`,
        ),
      ]
        .map((part) => part.textContent)
        .join(' ')
        .replace(/\s+/g, ' ')
        .trim()
        .toLowerCase(),
    }));

    // One chip per family, in the order the cards come, named by their tag.
    const familyNames = new Map();
    const familyCounts = new Map();
    options.forEach((option) => {
      const id = option.dataset.family;
      if (!familyNames.has(id)) {
        const tag = option.querySelector(`.${PREFIX}__tag`);
        familyNames.set(id, (tag?.textContent ?? id).trim());
      }
      familyCounts.set(id, (familyCounts.get(id) ?? 0) + 1);
    });
    const chips = make('ul', 'chips');
    chips.setAttribute('aria-label', Drupal.t('Filter by bundle'));
    const addChip = (familyId, name, count) => {
      const item = document.createElement('li');
      const chip = make('button', 'chip', name);
      chip.type = 'button';
      chip.dataset.family = familyId;
      chip.setAttribute('aria-pressed', familyId === '' ? 'true' : 'false');
      chip.append(make('span', 'chip-count', String(count)));
      item.append(chip);
      chips.append(item);
    };
    addChip('', Drupal.t('All'), cards.length);
    familyNames.forEach((name, id) => {
      addChip(id, name, familyCounts.get(id));
    });

    // Search, with a visible-to-screen-readers label.
    const search = make('div', 'search');
    const inputId = `${root.id || PREFIX}-search`;
    const label = document.createElement('label');
    label.className = 'visually-hidden';
    label.htmlFor = inputId;
    label.textContent = Drupal.t('Search patterns');
    const input = document.createElement('input');
    input.type = 'search';
    input.id = inputId;
    input.className = 'form-element form-element--type-search';
    input.autocomplete = 'off';
    input.placeholder = Drupal.t('Search patterns, e.g. pricing, tabs, quote');
    search.append(label, input);

    // The count is for screen readers only: it announces how many cards a
    // search or chip leaves; the chips already show the counts.
    const count = make('p', 'count', patternCount(cards.length));
    count.classList.add('visually-hidden');
    count.setAttribute('role', 'status');
    // One row: the search on one side, the status line and Insert on the
    // other. Then the chips and the count, then the scrolling list.
    const bar = make('div', 'bar');
    bar.append(search);
    const actions = root.querySelector(`.${PREFIX}__actions`);
    if (actions) {
      bar.append(actions);
    }
    const toolbar = make('div', 'toolbar');
    toolbar.append(bar, chips, count);
    list.before(toolbar);

    const empty = make(
      'p',
      'empty',
      Drupal.t('No pattern matches. Clear the search or choose All.'),
    );
    empty.hidden = true;
    list.append(empty);

    let familyFilter = '';
    const apply = () => {
      const term = input.value.trim().toLowerCase();
      let shown = 0;
      cards.forEach(({ option, family, words }) => {
        const match =
          (familyFilter === '' || family === familyFilter) &&
          (term === '' || words.includes(term));
        option.hidden = !match;
        shown += match ? 1 : 0;
      });
      count.textContent = patternCount(shown);
      empty.hidden = shown > 0;
    };

    chips.addEventListener(
      'click',
      (event) => {
        const chip = event.target.closest(`.${PREFIX}__chip`);
        if (!chip) {
          return;
        }
        chips.querySelectorAll(`.${PREFIX}__chip`).forEach((other) => {
          other.setAttribute('aria-pressed', other === chip ? 'true' : 'false');
        });
        familyFilter = chip.dataset.family ?? '';
        apply();
      },
      { signal },
    );

    input.addEventListener('input', apply, { signal });
    input.addEventListener(
      'keydown',
      (event) => {
        // Enter in a text input would submit the whole node form (Save).
        if (event.key === 'Enter') {
          event.preventDefault();
        } else if (event.key === 'Escape' && input.value !== '') {
          event.preventDefault();
          input.value = '';
          apply();
        }
      },
      { signal },
    );

    // The selected line and the Insert state.
    const showSelected = (radio) => {
      const name = radio.labels?.[0]?.textContent.trim() ?? radio.value;
      const strong = document.createElement('strong');
      strong.textContent = name;
      status.replaceChildren(`${Drupal.t('Selected:')} `, strong);
      submit.removeAttribute('aria-disabled');
    };
    const checked = root.querySelector(`.${PREFIX}__radio:checked`);
    if (checked) {
      showSelected(checked);
    } else {
      submit.setAttribute('aria-disabled', 'true');
      // After an insert the server's "Added ..." line stays until a choice.
      if (status.textContent.trim() === '') {
        status.textContent = Drupal.t('Choose a pattern above.');
      }
    }
    root.addEventListener(
      'change',
      (event) => {
        if (event.target.matches(`.${PREFIX}__radio`)) {
          showSelected(event.target);
        }
      },
      { signal },
    );

    // Nothing chosen: stop Drupal's Ajax (mousedown, keypress) and the
    // native click before they reach the button.
    const guard = (event) => {
      if (
        event.target !== submit ||
        submit.getAttribute('aria-disabled') !== 'true'
      ) {
        return;
      }
      if (event.type.startsWith('key') && !isPressKey(event)) {
        return;
      }
      event.preventDefault();
      event.stopPropagation();
      if (event.type === 'click' || event.type === 'keydown') {
        status.textContent = Drupal.t('Choose a pattern first.');
      }
    };
    ['mousedown', 'keydown', 'keypress', 'click'].forEach((type) => {
      root.addEventListener(type, guard, { capture: true, signal });
    });
  };

  /**
   * Attaches the PB Patterns picker enhancements.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.pbPatternsInsert = {
    attach(context) {
      once(ONCE_ID, ROOT, context).forEach(init);
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once.remove(ONCE_ID, ROOT, context).forEach((root) => {
        controllers.get(root)?.abort();
        controllers.delete(root);
      });
    },
  };
})(Drupal, once);
