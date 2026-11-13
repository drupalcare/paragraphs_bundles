/**
 * @param Drupal
 * @param once
 * @file
 * Pattern Spotlight for the Accordion: the bottom bar and one open panel.
 *
 * Opening and closing always go through PB's button. The closed panes keep
 * their photo painted, so an inline display none written after a close is
 * removed and the closed pane is made inert.
 */

((Drupal, once) => {
  const SELECTOR = '.pb-pattern--accordion-spotlight';
  const ONCE_ID = 'pb-pattern-accordion-spotlight';
  const BUTTON =
    ':scope > .pb-accordion__item > .pb-accordion__heading > .pb-accordion__button';

  /** @type {WeakMap<HTMLElement, AbortController>} */
  const controllers = new WeakMap();

  /**
   * Builds one Spotlight instance.
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
    const total = buttons.length;
    if (total < 2) {
      return;
    }
    const panes = buttons.map((button) =>
      button
        .closest('.pb-accordion__item')
        .querySelector(':scope > .pb-accordion__pane'),
    );
    const isOpen = (button) => button.getAttribute('aria-expanded') === 'true';
    const current = () => buttons.findIndex(isOpen);
    const pad = (n) =>
      String(n).padStart(
        String(total).length < 2 ? 2 : String(total).length,
        '0',
      );

    // A panel that opens must not rewrite the address.
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

    const open = (index) => {
      const target = buttons[((index % total) + total) % total];
      if (!isOpen(target)) {
        target.click();
      }
    };

    const nav = document.createElement('div');
    nav.className = 'pb-pattern-spotlight-nav';
    const count = document.createElement('p');
    count.className = 'pb-pattern-spotlight-count';
    const now = document.createElement('span');
    now.className = 'pb-pattern-spotlight-count-now';
    count.append(now, ` / ${pad(total)}`);

    const bars = document.createElement('div');
    bars.className = 'pb-pattern-spotlight-bars';
    bars.setAttribute('role', 'group');
    bars.setAttribute('aria-label', Drupal.t('Panels'));
    const barButtons = buttons.map((button, index) => {
      const title =
        button.querySelector('.pb-ui-btn__title')?.textContent.trim() ||
        String(index + 1);
      const bar = document.createElement('button');
      bar.type = 'button';
      bar.className = 'pb-ui-btn pb-pattern-spotlight-bar control-btn';
      bar.setAttribute(
        'aria-label',
        Drupal.t('Show !title', { '!title': title }),
      );
      bar.addEventListener('click', () => open(index), { signal });
      bars.appendChild(bar);
      return bar;
    });

    const arrows = document.createElement('div');
    arrows.className = 'pb-pattern-spotlight-arrows';
    const makeArrow = (extra, label, delta) => {
      const arrow = document.createElement('button');
      arrow.type = 'button';
      arrow.className = `pb-ui-btn pb-pattern-spotlight-btn ${extra} control-btn`;
      arrow.setAttribute('aria-label', label);
      arrow.addEventListener(
        'click',
        () => {
          const c = current();
          open(c < 0 ? 0 : c + delta);
        },
        { signal },
      );
      return arrow;
    };
    arrows.append(
      makeArrow('pb-pattern-spotlight-prev', Drupal.t('Previous'), -1),
      makeArrow('pb-pattern-spotlight-next', Drupal.t('Next'), 1),
    );
    nav.append(count, bars, arrows);
    (root.querySelector('.pb-content__full') || sections).appendChild(nav);

    const render = () => {
      const c = current();
      now.textContent = pad(c + 1);
      barButtons.forEach((bar, i) => {
        bar.setAttribute('aria-current', String(i === c));
      });
    };
    render();
    const stateObserver = new MutationObserver(render);
    buttons.forEach((button) =>
      stateObserver.observe(button, {
        attributes: true,
        attributeFilter: ['aria-expanded'],
      }),
    );

    // Open the first panel without PB's page-load scroll.
    if (current() < 0) {
      open(0);
    }

    signal.addEventListener(
      'abort',
      () => {
        paneObserver.disconnect();
        stateObserver.disconnect();
        nav.remove();
        panes.forEach((pane) => {
          pane.inert = false;
        });
      },
      { once: true },
    );
  };

  Drupal.behaviors.pbPatternAccordionSpotlight = {
    attach(context) {
      once(ONCE_ID, SELECTOR, context).forEach((root) => {
        const controller = new AbortController();
        controllers.set(root, controller);
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
