/**
 * @file
 * Pattern Bloom for the Modal: tells CSS where the opening button is.
 *
 * Reads the button's center and writes it to two variables on the dialog. PB's
 * script still owns opening, closing, the focus trap, Escape and inert. The
 * listener runs in the capture phase so the position is written before PB's
 * click handler opens the dialog. A keyboard click has the same position.
 */

((Drupal, once) => {
  const SELECTOR = '.pb-pattern--modal-bloom';
  const ONCE_ID = 'pb-pattern-modal-bloom';
  const OPENER = ':scope > .pb-modal__button > .pb-ui-btn';
  const DIALOG = ':scope > .pb-modal__dialog';
  const VAR_X = '--pb-pattern-bloom-x';
  const VAR_Y = '--pb-pattern-bloom-y';

  /** @type {WeakMap<HTMLElement, AbortController>} */
  const controllers = new WeakMap();

  /**
   * Keeps a coordinate on the screen, for a button scrolled out of view.
   *
   * @param {number} value
   *   The coordinate.
   * @param {number} max
   *   The viewport size on that axis.
   *
   * @return {number}
   *   The coordinate between 0 and max.
   */
  const onScreen = (value, max) => Math.min(Math.max(value, 0), max);

  Drupal.behaviors.pbPatternModalBloom = {
    attach(context) {
      once(ONCE_ID, SELECTOR, context).forEach((root) => {
        const opener = root.querySelector(OPENER);
        const dialog = root.querySelector(DIALOG);
        if (!opener || !dialog) {
          return;
        }
        const controller = new AbortController();
        controllers.set(root, controller);
        root.addEventListener(
          'click',
          (event) => {
            if (!opener.contains(event.target)) {
              return;
            }
            const box = opener.getBoundingClientRect();
            const x = onScreen(box.left + box.width / 2, window.innerWidth);
            const y = onScreen(box.top + box.height / 2, window.innerHeight);
            dialog.style.setProperty(VAR_X, `${x}px`);
            dialog.style.setProperty(VAR_Y, `${y}px`);
          },
          { capture: true, signal: controller.signal },
        );
        controller.signal.addEventListener(
          'abort',
          () => {
            dialog.style.removeProperty(VAR_X);
            dialog.style.removeProperty(VAR_Y);
          },
          { once: true },
        );
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
