/**
 * @param Drupal
 * @param once
 * @file
 * Pattern Lectern: opens the transcript on a wide region.
 *
 * A phone keeps it closed. The transcript text scrolls inside its panel, so a
 * panel that actually scrolls is made focusable for the keyboard. Without this
 * script the transcript still opens and closes as a normal disclosure.
 */

((Drupal, once) => {
  const ONCE_ID = 'pb-pattern-video-lectern';
  const ROOT = '.pb-pattern--video-lectern';
  // The same width as the @container rule in the CSS, in the same unit.
  const WIDE_REM = 44;

  /** @type {WeakMap<HTMLElement, Function>} */
  const cleanups = new WeakMap();

  /**
   * @param {HTMLElement} root
   *   The paragraph.
   * @return {boolean}
   *   Whether the transcript has its own column.
   */
  const isWide = (root) => {
    const rem = parseFloat(
      window.getComputedStyle(document.documentElement).fontSize,
    );
    return root.offsetWidth >= WIDE_REM * rem;
  };

  /**
   * @param {HTMLElement} root
   *   The paragraph.
   */
  const init = (root) => {
    const details = root.querySelector(
      ':scope > .pb-video__inner > .pb-video__transcript',
    );
    const panel = details?.querySelector(':scope > :not(summary)');
    if (!details || !panel) {
      return;
    }

    const summary = details.querySelector(':scope > summary');
    panel.setAttribute('role', 'group');
    panel.setAttribute('aria-label', summary ? summary.textContent.trim() : '');

    // A paragraph inside a closed tab has no width yet. The choice waits for
    // one, then is made once: after that the transcript is the visitor's.
    let decided = false;
    const update = () => {
      if (!decided && root.offsetWidth > 0) {
        decided = true;
        details.open = isWide(root);
      }
      if (panel.scrollHeight > panel.clientHeight) {
        panel.tabIndex = 0;
      } else {
        panel.removeAttribute('tabindex');
      }
    };

    const controller = new AbortController();
    details.addEventListener('toggle', update, { signal: controller.signal });
    const observer = new ResizeObserver(update);
    observer.observe(root);
    observer.observe(panel);

    cleanups.set(root, () => {
      controller.abort();
      observer.disconnect();
      panel.removeAttribute('tabindex');
      panel.removeAttribute('role');
      panel.removeAttribute('aria-label');
    });
  };

  /**
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.pbPatternVideoLectern = {
    attach(context) {
      once(ONCE_ID, ROOT, context).forEach(init);
    },

    /**
     * @param {Document|HTMLElement} context
     *   The detached context.
     * @param {object} settings
     *   The drupalSettings.
     * @param {string} trigger
     *   The detach trigger.
     */
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once.remove(ONCE_ID, ROOT, context).forEach((root) => {
        cleanups.get(root)?.();
        cleanups.delete(root);
      });
    },
  };
})(Drupal, once);
