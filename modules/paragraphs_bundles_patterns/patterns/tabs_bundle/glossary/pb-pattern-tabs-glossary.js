/**
 * @param Drupal
 * @param once
 * @file
 * Pattern Glossary for the Tabs: scroll buttons, fade edges and a counter.
 *
 * The buttons and the counter are placed beside PB's tab list, never inside
 * it: PB refuses a tab whose list is not a child of the tab set. The page
 * never moves when a term opens; only the strip scrolls.
 */

((Drupal, once) => {
  const SELECTOR = '.pb-pattern--tabs-glossary';
  const ONCE_ID = 'pb-pattern-tabs-glossary';

  /** @type {WeakMap<HTMLElement, AbortController>} */
  const controllers = new WeakMap();

  /**
   * Builds one Glossary instance.
   *
   * @param {HTMLElement} root
   *   The paragraph carrying the Pattern class.
   * @param {AbortSignal} signal
   *   Stops listening when aborted.
   */
  const setup = (root, signal) => {
    const tabset = root.querySelector('.pb-tabs__tabset');
    const list = tabset?.querySelector(':scope > .pb-tabs__tablist');
    if (!list) {
      return;
    }
    const tabs = Array.from(list.querySelectorAll(':scope > .pb-tabs__tab'));
    if (!tabs.length) {
      return;
    }
    const total = tabs.length;
    const pad = (n) =>
      String(n).padStart(
        String(total).length < 2 ? 2 : String(total).length,
        '0',
      );
    const rtl = () => getComputedStyle(root).direction === 'rtl';

    const makeButton = (name, label) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = `pb-ui-btn pb-pattern-glossary-scroll pb-pattern-glossary-${name} control-btn`;
      button.setAttribute('aria-label', label);
      return button;
    };
    const prev = makeButton('prev', Drupal.t('Scroll terms back'));
    const next = makeButton('next', Drupal.t('Scroll terms forward'));
    const count = document.createElement('p');
    count.className = 'pb-pattern-glossary-count';
    const now = document.createElement('span');
    now.className = 'pb-pattern-glossary-count-now';
    count.append(now, ` / ${pad(total)}`);

    // Beside the list, as children of the tab set.
    list.before(prev);
    list.after(next);
    next.after(count);

    // Scroll edges: Math.abs because scrollLeft runs negative right to left.
    const updateEdges = () => {
      const left = Math.abs(list.scrollLeft);
      const atStart = left < 2;
      const atEnd = left + list.clientWidth >= list.scrollWidth - 2;
      root.classList.toggle('pb-pattern-glossary--at-start', atStart);
      root.classList.toggle('pb-pattern-glossary--at-end', atEnd);
      root.classList.toggle('pb-pattern-glossary--static', atStart && atEnd);
      [prev, next].forEach((button, i) => {
        const disabled = i === 0 ? atStart : atEnd;
        button.setAttribute('aria-disabled', String(disabled));
        button.tabIndex = disabled ? -1 : 0;
      });
    };

    const page = (direction) => {
      if (
        (direction < 0 &&
          root.classList.contains('pb-pattern-glossary--at-start')) ||
        (direction > 0 &&
          root.classList.contains('pb-pattern-glossary--at-end'))
      ) {
        return;
      }
      const delta = direction * Math.max(list.clientWidth * 0.7, 120);
      list.scrollBy({ left: rtl() ? -delta : delta });
    };
    prev.addEventListener('click', () => page(-1), { signal });
    next.addEventListener('click', () => page(1), { signal });

    list.addEventListener('scroll', updateEdges, { passive: true, signal });
    const resize = new ResizeObserver(updateEdges);
    resize.observe(list);

    const showActive = () => {
      const index = tabs.findIndex(
        (tab) => tab.getAttribute('aria-selected') === 'true',
      );
      now.textContent = pad(index + 1);
      if (index >= 0) {
        const tab = tabs[index];
        // Only the strip scrolls: the page never moves.
        const listBox = list.getBoundingClientRect();
        const tabBox = tab.getBoundingClientRect();
        if (tabBox.left < listBox.left || tabBox.right > listBox.right) {
          list.scrollBy({ left: tabBox.left - listBox.left - 56 });
        }
      }
      updateEdges();
    };
    showActive();
    const observer = new MutationObserver(showActive);
    observer.observe(list, {
      subtree: true,
      attributes: true,
      attributeFilter: ['aria-selected'],
    });

    root.classList.add('pb-pattern-glossary--ready');

    signal.addEventListener(
      'abort',
      () => {
        observer.disconnect();
        resize.disconnect();
        prev.remove();
        next.remove();
        count.remove();
      },
      { once: true },
    );
  };

  Drupal.behaviors.pbPatternTabsGlossary = {
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
