/**
 * @file
 * Pattern Swatch for the Tabs: puts each panel's picture into its tab.
 *
 * A tab holds text or an icon only, so the picture is copied from the panel:
 * the same image address, the same srcset with a thumbnail size, loaded lazily.
 * A tab whose panel starts with no picture keeps its text. The tab keeps its
 * visible name, so the thumbnail has an empty alt.
 */

((Drupal, once) => {
  const SELECTOR = '.pb-pattern--tabs-swatch';
  const ONCE_ID = 'pb-pattern-tabs-swatch';

  /** @type {WeakMap<HTMLElement, AbortController>} */
  const controllers = new WeakMap();

  /**
   * Builds one Swatch instance.
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
    const added = [];
    tabs.forEach((tab) => {
      const panel = tabset.querySelector(`#${CSS.escape(`${tab.id}-pane`)}`);
      const source = panel?.querySelector(
        ':scope > :first-child:is(.pb-image, .pb-image-wide, .pb-image-narrow) img',
      );
      if (!source) {
        return;
      }
      const thumb = document.createElement('img');
      thumb.className = 'pb-pattern-swatch-thumb';
      thumb.alt = '';
      thumb.setAttribute('aria-hidden', 'true');
      thumb.loading = 'lazy';
      thumb.decoding = 'async';
      if (source.getAttribute('srcset')) {
        // A responsive image: the browser picks the smallest candidate.
        thumb.setAttribute('srcset', source.getAttribute('srcset'));
        thumb.setAttribute('sizes', '104px');
      }
      thumb.src = source.getAttribute('src') || source.currentSrc;
      tab.prepend(thumb);
      added.push(thumb);
    });

    signal.addEventListener(
      'abort',
      () => added.forEach((thumb) => thumb.remove()),
      { once: true },
    );
  };

  Drupal.behaviors.pbPatternTabsSwatch = {
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
