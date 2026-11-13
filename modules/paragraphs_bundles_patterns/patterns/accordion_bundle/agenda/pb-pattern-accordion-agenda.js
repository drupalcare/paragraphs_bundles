/**
 * @param Drupal
 * @param once
 * @file
 * Pattern Agenda for the Accordion: copy-link buttons and a row of numbers.
 *
 * The copy link is PB's own shareable address (data-fragment). The dock opens
 * an item through PB's button and follows the scroll.
 */

((Drupal, once) => {
  const SELECTOR = '.pb-pattern--accordion-agenda';
  const ONCE_ID = 'pb-pattern-accordion-agenda';
  const BUTTON =
    ':scope > .pb-accordion__item > .pb-accordion__heading > .pb-accordion__button';

  /** @type {WeakMap<HTMLElement, AbortController>} */
  const controllers = new WeakMap();

  /**
   * Builds one Agenda instance.
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
    if (!buttons.length) {
      return;
    }
    const items = buttons.map((button) =>
      button.closest('.pb-accordion__item'),
    );
    const added = [];
    const timers = new Set();
    const reduced = () =>
      window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Copy link: only where the browser allows it and the item has an address.
    if (navigator.clipboard) {
      buttons.forEach((button, index) => {
        const slug = button.getAttribute('data-fragment');
        const pane = items[index].querySelector(':scope > .pb-accordion__pane');
        if (!slug || !pane) {
          return;
        }
        const copy = document.createElement('button');
        copy.type = 'button';
        copy.className = 'pb-ui-btn pb-pattern-agenda-copy control-btn';
        const text = document.createElement('span');
        text.setAttribute('aria-live', 'polite');
        text.textContent = Drupal.t('Copy link');
        copy.appendChild(text);
        copy.addEventListener(
          'click',
          () => {
            const base = window.location.href.split('#')[0];
            navigator.clipboard.writeText(`${base}#${slug}`).then(
              () => {
                copy.classList.add('is-done');
                text.textContent = Drupal.t('Link copied');
                const timer = setTimeout(() => {
                  timers.delete(timer);
                  copy.classList.remove('is-done');
                  text.textContent = Drupal.t('Copy link');
                }, 2000);
                timers.add(timer);
              },
              () => {
                text.textContent = Drupal.t('The link could not be copied');
              },
            );
          },
          { signal },
        );
        pane.appendChild(copy);
        added.push(copy);
      });
    }

    // The dock: one number per item.
    const dock = document.createElement('nav');
    dock.className = 'pb-pattern-agenda-dock';
    dock.setAttribute('aria-label', Drupal.t('Jump to an item'));
    const track = document.createElement('div');
    track.className = 'pb-pattern-agenda-track';
    const chips = buttons.map((button, index) => {
      const title =
        button.querySelector('.pb-ui-btn__title')?.textContent.trim() || '';
      const number = String(index + 1);
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'pb-ui-btn pb-pattern-agenda-chip control-btn';
      // The name starts with the visible number so voice control can use it.
      chip.setAttribute('aria-label', title ? `${number}, ${title}` : number);
      const label = document.createElement('span');
      label.textContent = number;
      chip.appendChild(label);
      chip.addEventListener(
        'click',
        () => {
          if (button.getAttribute('aria-expanded') !== 'true') {
            button.click();
          }
          items[index].scrollIntoView({
            block: 'start',
            behavior: reduced() ? 'auto' : 'smooth',
          });
        },
        { signal },
      );
      track.appendChild(chip);
      return chip;
    });
    dock.appendChild(track);
    (root.querySelector('.pb-content__full') || sections).appendChild(dock);
    added.push(dock);

    const mark = (index) => {
      chips.forEach((chip, i) => {
        chip.setAttribute('aria-current', String(i === index));
      });
    };

    let observer = null;
    if ('IntersectionObserver' in window) {
      observer = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            if (entry.isIntersecting) {
              mark(items.indexOf(entry.target));
            }
          });
        },
        { rootMargin: '-40% 0px -55% 0px' },
      );
      items.forEach((item) => observer.observe(item));
    }
    const stateObserver = new MutationObserver((records) => {
      records.forEach((record) => {
        if (record.target.getAttribute('aria-expanded') === 'true') {
          mark(buttons.indexOf(record.target));
        }
      });
    });
    buttons.forEach((button) =>
      stateObserver.observe(button, {
        attributes: true,
        attributeFilter: ['aria-expanded'],
      }),
    );

    signal.addEventListener(
      'abort',
      () => {
        observer?.disconnect();
        stateObserver.disconnect();
        timers.forEach(clearTimeout);
        added.forEach((element) => element.remove());
      },
      { once: true },
    );
  };

  Drupal.behaviors.pbPatternAccordionAgenda = {
    attach(context) {
      once(ONCE_ID, SELECTOR, context).forEach((root) => {
        const controller = new AbortController();
        controllers.set(root, controller);
        setup(root, controller.signal);
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
