/**
 * @file
 * Lightweight slide helpers for smooth animations without jQuery.
 *
 * Provides reusable slideUp and slideDown utilities for accordion animations.
 *
 * Filename:     paragraph-bundle-accordion-utils.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing

((Drupal) => {
  /**
   * Initialize the accordion namespace if it doesn't exist.
   *
   * @namespace Drupal.paragraphBundleAccordion
   */
  Drupal.paragraphBundleAccordion = Drupal.paragraphBundleAccordion || {};

  /**
   * CSS styles applied during slide animations to collapse elements.
   *
   * @type {Object<string, string>}
   */
  const cssStyles = {
    overflow: 'hidden',
    height: '0',
    paddingTop: '0',
    paddingBottom: '0',
    marginTop: '0',
    marginBottom: '0',
  };

  /**
   * Reads a layout property so the browser flushes pending style changes.
   *
   * The read is the whole point and the value is discarded: without it the
   * browser coalesces the height just written with the one written next and
   * the transition never runs. Written as a call rather than a bare
   * `target.offsetHeight;` statement because that reads as dead code to both
   * ESLint and the next person.
   *
   * @param {HTMLElement} target - The element to measure.
   *
   * @return {number} The measured height, unused by callers.
   */
  const forceReflow = (target) => target.offsetHeight;

  /**
   * The timer of a slide still running on each element.
   *
   * A slide that starts cancels the one before it on the same pane, so opening
   * a pane that is still closing never has the old close hide it a moment
   * later.
   *
   * @type {WeakMap<HTMLElement, number>}
   */
  const pendingSlides = new WeakMap();

  /**
   * Cancels the slide still running on an element, if any.
   *
   * @param {HTMLElement} target - The element.
   */
  const cancelPending = (target) => {
    clearTimeout(pendingSlides.get(target));
    pendingSlides.delete(target);
  };

  /**
   * Removes transition-related inline styles from an element.
   *
   * @param {HTMLElement} target - The element to clean up.
   */
  const removeStyles = (target) => {
    const styles = [
      'height',
      'paddingTop',
      'paddingBottom',
      'marginTop',
      'marginBottom',
      'overflow',
      'transitionDuration',
      'transitionProperty',
    ];
    styles.forEach((style) => target.style.removeProperty(style));
  };

  /**
   * Animates an element to collapse (slide up) and hides it.
   *
   * Call it while the element is still shown: it measures the open height to
   * animate from. Anything that hides the element through CSS (the inactive
   * state class) belongs in `onDone`, which runs as the element is hidden.
   *
   * @param {HTMLElement} target - The element to collapse.
   * @param {number} [duration=300] - Animation duration in milliseconds.
   * @param {Function} [onDone] - Runs when the collapse ends.
   */
  const slideUp = (target, duration = 300, onDone = null) => {
    if (!target) {
      return;
    }
    cancelPending(target);

    target.style.transitionProperty = 'height, margin, padding';
    target.style.transitionDuration = `${duration}ms`;
    target.style.transitionTimingFunction = 'ease-in-out';
    target.style.boxSizing = 'border-box';
    target.style.height = `${target.offsetHeight}px`;
    forceReflow(target);

    target.setAttribute('aria-hidden', 'true');

    Object.keys(cssStyles).forEach((style) => {
      target.style[style] = cssStyles[style];
    });

    pendingSlides.set(
      target,
      setTimeout(() => {
        pendingSlides.delete(target);
        if (onDone) {
          onDone();
        }
        target.style.display = 'none';
        removeStyles(target);
      }, duration),
    );
  };

  /**
   * Animates an element to expand (slide down) and shows it.
   *
   * @param {HTMLElement} target - The element to expand.
   * @param {string} [display='block'] - The display value to use when showing.
   * @param {number} [duration=300] - Animation duration in milliseconds.
   */
  const slideDown = (target, display = 'block', duration = 300) => {
    if (!target) {
      return;
    }
    cancelPending(target);

    target.style.removeProperty('display');
    let computedDisplay = window.getComputedStyle(target).display;
    if (computedDisplay === 'none') {
      computedDisplay = display;
    }
    target.style.display = computedDisplay;

    const height = target.offsetHeight;
    Object.keys(cssStyles).forEach((style) => {
      target.style[style] = cssStyles[style];
    });

    forceReflow(target);
    target.style.transitionProperty = 'height, margin, padding';
    target.style.transitionDuration = `${duration}ms`;
    target.style.transitionTimingFunction = 'ease-in-out';
    target.style.boxSizing = 'border-box';
    target.style.height = `${height}px`;

    target.setAttribute('aria-hidden', 'false');

    ['paddingTop', 'paddingBottom', 'marginTop', 'marginBottom'].forEach(
      (prop) => {
        target.style.removeProperty(prop);
      },
    );

    pendingSlides.set(
      target,
      setTimeout(() => {
        pendingSlides.delete(target);
        [
          'height',
          'overflow',
          'transitionDuration',
          'transitionProperty',
        ].forEach((prop) => {
          target.style.removeProperty(prop);
        });
      }, duration),
    );
  };

  // Export utilities to Drupal namespace
  Drupal.paragraphBundleAccordion.slideUp = slideUp;
  Drupal.paragraphBundleAccordion.slideDown = slideDown;
})(Drupal);
