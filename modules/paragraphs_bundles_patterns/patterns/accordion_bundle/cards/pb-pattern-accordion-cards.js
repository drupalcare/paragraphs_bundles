/**
 * @param Drupal
 * @param once
 * @file
 * Pattern Cards for the Accordion: one open card, previous and next.
 *
 * Works from PB's own markup and state. Opening and closing always go through
 * PB's button, so PB keeps its ARIA and its slide. The panes keep their photo
 * painted while closed, so an inline display none written after a close is
 * removed and the closed pane is made inert.
 */

((Drupal, once) => {
  const SELECTOR = '.pb-pattern--accordion-cards';
  const ONCE_ID = 'pb-pattern-accordion-cards';
  const BUTTON =
    ':scope > .pb-accordion__item > .pb-accordion__heading > .pb-accordion__button';

  /** @type {WeakMap<HTMLElement, AbortController>} */
  const controllers = new WeakMap();

  /**
   * Builds one Cards instance.
   *
   * @param {HTMLElement} root
   *   The paragraph carrying the Pattern class.
   * @param {AbortSignal} signal
   *   Stops listening when aborted.
   */
  const setup = (root, signal) => {
    const sections = root.querySelector('.pb-accordion__sections');
    if (!sections) {
      return;
    }
    const buttons = Array.from(sections.querySelectorAll(BUTTON));
    const items = buttons.map((button) =>
      button.closest('.pb-accordion__item'),
    );
    const total = buttons.length;
    if (total < 2) {
      return;
    }
    const panes = items.map((item) =>
      item.querySelector(':scope > .pb-accordion__pane'),
    );
    const isOpen = (button) => button.getAttribute('aria-expanded') === 'true';
    const current = () => buttons.findIndex(isOpen);

    // A card that opens must not rewrite the address.
    buttons.forEach((button) => button.removeAttribute('data-fragment'));

    // Keep the photo painted: PB writes display none when a pane has shut.
    const paint = (pane) => {
      if (pane.style.display === 'none') {
        pane.style.removeProperty('display');
      }
      pane.inert = pane.getAttribute('aria-hidden') === 'true';
    };
    panes.forEach(paint);
    const paneObserver = new MutationObserver((records) => {
      records.forEach((record) => paint(record.target));
    });
    panes.forEach((pane) =>
      paneObserver.observe(pane, {
        attributes: true,
        attributeFilter: ['style', 'aria-hidden'],
      }),
    );

    // One open at a time: PB has no setting for it, so close the others.
    sections.addEventListener(
      'click',
      (event) => {
        const button = event.target.closest('.pb-accordion__button');
        if (!button || !buttons.includes(button) || !isOpen(button)) {
          return;
        }
        buttons.forEach((other) => {
          if (other !== button && isOpen(other)) {
            other.click();
          }
        });
      },
      { signal },
    );

    // The grid row a card sits on while every card is closed. The open card
    // is placed on that row, so it grows from where it was clicked. Counted
    // from the card's place in the list, never from the current layout.
    let row = 0;
    // The section title, when there is one, takes a full row above the cards.
    const titleRows = Array.from(sections.children)
      .slice(0, Array.from(sections.children).indexOf(items[0]))
      .filter((child) => getComputedStyle(child).display !== 'none').length;
    const rowOf = (item) => {
      const columns =
        getComputedStyle(item.parentElement)
          .gridTemplateColumns.split(' ')
          .filter((track) => track && track !== 'none').length || 1;
      return Math.floor(items.indexOf(item) / columns) + 1 + titleRows;
    };
    sections.addEventListener(
      'click',
      (event) => {
        // Previous and next keep the row: only a visitor's click moves it.
        if (!event.isTrusted) {
          return;
        }
        const button = event.target.closest('.pb-accordion__button');
        if (button && buttons.includes(button) && !isOpen(button)) {
          row = rowOf(button.closest('.pb-accordion__item'));
        }
      },
      { capture: true, signal },
    );

    let bar = null;
    const go = (index) => {
      const target = (index + total) % total;
      if (!isOpen(buttons[target])) {
        buttons[target].click();
      }
      requestAnimationFrame(() => {
        buttons[target].focus({ preventScroll: true });
      });
    };
    const makeButton = (extra, label) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = `pb-ui-btn pb-pattern-cards-btn ${extra} control-btn`;
      button.setAttribute('aria-label', label);
      return button;
    };
    const render = () => {
      const open = current();
      items.forEach((item, i) => {
        item.style.gridRowStart = i === open && row ? String(row) : '';
      });
      bar?.remove();
      bar = null;
      if (open < 0) {
        return;
      }
      bar = document.createElement('div');
      bar.className = 'pb-pattern-cards-step';
      const count = document.createElement('p');
      count.className = 'pb-pattern-cards-count';
      const now = document.createElement('span');
      now.className = 'pb-pattern-cards-count-now';
      now.textContent = String(open + 1);
      count.append(now, ` / ${total}`);
      const prev = makeButton(
        'pb-pattern-cards-prev',
        Drupal.t('Previous card'),
      );
      const next = makeButton('pb-pattern-cards-next', Drupal.t('Next card'));
      prev.addEventListener('click', () => go(open - 1), { signal });
      next.addEventListener('click', () => go(open + 1), { signal });
      bar.append(count, prev, next);
      panes[open].appendChild(bar);
    };
    render();
    const stateObserver = new MutationObserver(render);
    buttons.forEach((button) =>
      stateObserver.observe(button, {
        attributes: true,
        attributeFilter: ['aria-expanded'],
      }),
    );

    signal.addEventListener(
      'abort',
      () => {
        paneObserver.disconnect();
        stateObserver.disconnect();
        bar?.remove();
        items.forEach((item) => item.style.removeProperty('grid-row-start'));
        panes.forEach((pane) => {
          pane.inert = false;
        });
      },
      { once: true },
    );
  };

  Drupal.behaviors.pbPatternAccordionCards = {
    attach(context) {
      once(ONCE_ID, SELECTOR, context).forEach((root) => {
        const controller = new AbortController();
        controllers.set(root, controller);
        // After PB's accordion, which attaches in the same pass.
        requestAnimationFrame(() => {
          if (!controller.signal.aborted) {
            setup(root, controller.signal);
          }
        });
      });
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once.remove(ONCE_ID, SELECTOR, context).forEach((root) => {
        controllers.get(root)?.abort();
        controllers.delete(root);
      });
    },
  };
})(Drupal, once);
