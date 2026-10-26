/**
 * @file
 * Paragraphs Bundles: rolling digits for any number a bundle updates.
 *
 * Mark the element that holds the number with `data-pb-digits`. The bundle's
 * own script keeps writing that element exactly as before, and screen readers
 * keep reading it: it is only made visually hidden. A copy beside it, hidden
 * from assistive technology, shows each character in its own slot, and a
 * digit that changes slides into place. A rising number brings the new digit
 * in from below, a falling one from above, so a countdown and a slide counter
 * both move the way they read. With reduced motion the digits change without
 * moving.
 *
 * The copy takes the element's own classes, so a theme that styled the number
 * keeps styling it, and `.pb-digits` is the hook for styling the face.
 *
 * Filename:     pb-rolling-digits.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com
 */

((Drupal, once) => {
  const PREFIX = 'pb-digits';

  // A leaving digit is removed when its animation ends, or after this long if
  // the animation never runs (a background tab, animations turned off).
  const LEAVE_MS = 400;

  /**
   * Shows a value in the digit box, one slot per character.
   *
   * @param {HTMLElement} box
   *   The digit box.
   * @param {string} value
   *   The value as the bundle wrote it.
   * @param {boolean} animate
   *   Whether a changed digit slides in.
   */
  function render(box, value, animate) {
    const chars = [...value];
    while (box.children.length < chars.length) {
      const slot = document.createElement('span');
      slot.className = `${PREFIX}__slot`;
      box.prepend(slot);
    }
    while (box.children.length > chars.length) {
      box.firstElementChild.remove();
    }
    chars.forEach((char, index) => {
      const slot = box.children[index];
      const current = slot.querySelector(
        `.${PREFIX}__digit:not(.is-leaving)`,
      );
      if (current && current.textContent === char) {
        return;
      }
      const next = document.createElement('span');
      next.className = `${PREFIX}__digit`;
      next.textContent = char;
      if (current && animate) {
        // A digit that entered earlier still carries is-entering, and that
        // rule comes later in the CSS, so it would replay the entry animation
        // over the new digit instead of leaving.
        current.classList.remove('is-entering');
        current.classList.add('is-leaving');
        next.classList.add('is-entering');
        const remove = () => current.remove();
        current.addEventListener('animationend', remove, { once: true });
        window.setTimeout(remove, LEAVE_MS);
      } else if (current) {
        current.remove();
      }
      slot.append(next);
    });
  }

  /**
   * Adds rolling digits to every element marked `data-pb-digits`.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Builds the digit box once per element and follows its updates.
   */
  Drupal.behaviors.pbRollingDigits = {
    attach(context) {
      const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
      once('pb-digits', '[data-pb-digits]', context).forEach((source) => {
        const box = document.createElement('span');
        box.className = source.className;
        box.classList.add(PREFIX);
        box.setAttribute('aria-hidden', 'true');
        source.after(box);
        source.classList.add('visually-hidden');

        let previous = source.textContent.trim();
        render(box, previous, false);
        new MutationObserver(() => {
          const value = source.textContent.trim();
          if (value === previous) {
            return;
          }
          const rising = Number(value) > Number(previous);
          box.classList.toggle(`${PREFIX}--rising`, rising);
          render(box, value, previous !== '' && !reduce.matches);
          previous = value;
        }).observe(source, {
          childList: true,
          characterData: true,
          subtree: true,
        });
      });
    },
  };
})(Drupal, once);
