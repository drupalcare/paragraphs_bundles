/**
 * @file
 * Pattern Rail: a bottom bar with previous, next, track and counter; click-only.
 *
 * The row is overflow-x: hidden (no scrollbar, no wheel), so the buttons, a
 * touch swipe and keyboard focus are what move it. PB's hover and focus flip
 * sources are stopped on their way to PB's listeners; the CSS removes the
 * matching flips. Without this script the row shows its first cards and the
 * cards turn as PB's default.
 */

((Drupal, once) => {
  /**
   * Holds PB's pointer and focus sources back from a card's flip state.
   *
   * @param {HTMLElement} root
   *   The Pattern's root.
   */
  function holdClickOnly(root) {
    const stopForScene = (event) => {
      if (
        event.target instanceof Element &&
        event.target.matches('.pb-3d-flip-box__scene')
      ) {
        event.stopPropagation();
      }
    };
    const stopForFaces = (event) => {
      if (
        event.target instanceof Element &&
        event.target.closest('.pb-3d-flip-box__inner')
      ) {
        event.stopPropagation();
      }
    };
    root.addEventListener('pointerenter', stopForScene, true);
    root.addEventListener('pointerleave', stopForScene, true);
    root.addEventListener('focusin', stopForFaces, true);
    root.addEventListener('focusout', stopForFaces, true);
  }

  /**
   * Builds the bottom bar and keeps it in step with the row.
   *
   * @param {HTMLElement} strip
   *   The `.pb-grid__wrapper` element.
   */
  function addBar(strip) {
    const cards = strip.querySelectorAll(':scope > .pb-3d-flip-box');
    if (!cards.length) {
      return;
    }
    const bar = document.createElement('div');
    bar.className = 'pb-pattern-rail__bar';
    bar.setAttribute('role', 'group');
    bar.setAttribute('aria-label', Drupal.t('Move through the cards'));
    const makeButton = (name, label) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = `pb-pattern-rail__btn pb-pattern-rail__${name} control-btn`;
      button.setAttribute('aria-label', label);
      return button;
    };
    const prev = makeButton('prev', Drupal.t('Previous cards'));
    const next = makeButton('next', Drupal.t('Next cards'));
    const track = document.createElement('div');
    track.className = 'pb-pattern-rail__track';
    track.setAttribute('aria-hidden', 'true');
    const thumb = document.createElement('span');
    thumb.className = 'pb-pattern-rail__thumb';
    track.append(thumb);
    const count = document.createElement('p');
    count.className = 'pb-pattern-rail__count';
    const now = document.createElement('span');
    now.className = 'pb-pattern-rail__now';
    now.textContent = '1';
    count.append(now, ` / ${cards.length}`);
    bar.append(prev, track, count, next);
    strip.after(bar);

    const stepSize = () =>
      cards[0].getBoundingClientRect().width +
      (parseFloat(getComputedStyle(strip).columnGap) || 0);
    const update = () => {
      const max = strip.scrollWidth - strip.clientWidth;
      const offset = Math.abs(strip.scrollLeft);
      const size = strip.scrollWidth
        ? strip.clientWidth / strip.scrollWidth
        : 1;
      thumb.style.setProperty(
        '--pb-pattern-rail-p',
        max > 0 ? (offset / max).toFixed(3) : '0',
      );
      thumb.style.setProperty(
        '--pb-pattern-rail-size',
        Math.min(1, size).toFixed(3),
      );
      now.textContent = String(
        Math.min(cards.length, Math.round(offset / stepSize()) + 1),
      );
      prev.setAttribute('aria-disabled', offset < 2 ? 'true' : 'false');
      next.setAttribute('aria-disabled', offset > max - 2 ? 'true' : 'false');
      bar.hidden = max <= 0;
    };
    const move = (direction) => {
      const rtl = getComputedStyle(strip).direction === 'rtl';
      strip.scrollBy({
        left: stepSize() * direction * (rtl ? -1 : 1),
        behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches
          ? 'auto'
          : 'smooth',
      });
    };
    prev.addEventListener('click', () => move(-1));
    next.addEventListener('click', () => move(1));
    strip.addEventListener('scroll', update, { passive: true });
    new ResizeObserver(update).observe(strip);

    // Touch swipe: the row is overflow hidden, so it does not pan by itself.
    // CSS turns snapping off while the finger moves it.
    let start = null;
    strip.addEventListener('pointerdown', (event) => {
      if (event.pointerType === 'touch') {
        start = { x: event.clientX, left: strip.scrollLeft };
      }
    });
    strip.addEventListener('pointermove', (event) => {
      if (start) {
        strip.classList.add('pb-pattern-rail--moving');
        strip.scrollLeft = start.left + (start.x - event.clientX);
      }
    });
    const end = () => {
      if (start) {
        start = null;
        strip.classList.remove('pb-pattern-rail--moving');
      }
    };
    strip.addEventListener('pointerup', end);
    strip.addEventListener('pointercancel', end);
    update();
  }

  Drupal.behaviors.pbPatternGridRail = {
    attach(context) {
      once('pb-pattern-grid-rail', '.pb-pattern--grid-rail', context).forEach(
        (root) => {
          holdClickOnly(root);
          const strip = root.querySelector('.pb-grid__wrapper');
          if (strip) {
            addBar(strip);
          }
        },
      );
    },
  };
})(Drupal, once);
