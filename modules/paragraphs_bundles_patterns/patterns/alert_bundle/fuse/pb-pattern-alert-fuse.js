/**
 * @file
 * Pattern Fuse for the Alert: passes the countdown to the burning line.
 *
 * PB's alert script owns the timer and closes the alert. This file only gives
 * the line its duration and mirrors the Pause button, which PB reports through
 * aria-pressed. It closes nothing and changes no timing.
 */

((Drupal, once) => {
  const SELECTOR = '.pb-pattern--alert-fuse[data-dismissal-type="countdown"]';
  const ONCE_ID = 'pb-pattern-alert-fuse';
  const RUNNING = 'pb-pattern-fuse--running';
  const PAUSED = 'pb-pattern-fuse--paused';
  const SECONDS_VAR = '--pb-pattern-fuse-seconds';
  // A child path: an alert's body may hold another alert with its own Pause.
  const PAUSE_BUTTON =
    ':scope > .pb-ui-inner > .pb-alert--top > .pb-alert__countdown > [data-pb-countdown-pause]';

  /** @type {WeakMap<HTMLElement, AbortController>} */
  const controllers = new WeakMap();

  /**
   * Starts the line for one alert.
   *
   * @param {HTMLElement} root
   *   The paragraph carrying the Pattern class.
   * @param {AbortSignal} signal
   *   Stops watching when aborted.
   */
  const setup = (root, signal) => {
    const seconds = parseInt(root.dataset.countdownSeconds, 10);
    if (!(seconds > 0) || root.classList.contains('pb-is-closed')) {
      return;
    }
    root.style.setProperty(SECONDS_VAR, `${seconds}s`);
    root.classList.add(RUNNING);

    const pause = root.querySelector(PAUSE_BUTTON);
    let observer = null;
    if (pause) {
      // PB's click handler may run before or after one added here, so the
      // attribute it writes is read instead of the click.
      observer = new MutationObserver(() => {
        root.classList.toggle(
          PAUSED,
          pause.getAttribute('aria-pressed') === 'true',
        );
      });
      observer.observe(pause, {
        attributes: true,
        attributeFilter: ['aria-pressed'],
      });
    }

    signal.addEventListener(
      'abort',
      () => {
        if (observer) {
          observer.disconnect();
        }
        root.classList.remove(RUNNING, PAUSED);
        root.style.removeProperty(SECONDS_VAR);
      },
      { once: true },
    );
  };

  Drupal.behaviors.pbPatternAlertFuse = {
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
