/**
 * @param Drupal
 * @param once
 * @file
 * Pattern Stage for the Accordion: autoplay, Pause, one open step.
 *
 * The progress line is the clock: a step ends when its CSS animation ends, so
 * holding the animation holds the step and the script keeps no timer. A step
 * the visitor picks stops autoplay for good; hover and focus hold it; reduced
 * motion starts it stopped.
 */

((Drupal, once) => {
  const SELECTOR = '.pb-pattern--accordion-stage';
  const ONCE_ID = 'pb-pattern-accordion-stage';
  const BUTTON =
    ':scope > .pb-accordion__item > .pb-accordion__heading > .pb-accordion__button';
  const PLAYING = 'pb-pattern-stage--playing';
  const HELD = 'pb-pattern-stage--held';
  const ANIMATION = 'pb-pattern-accordion-stage-fill';

  /** @type {WeakMap<HTMLElement, AbortController>} */
  const controllers = new WeakMap();

  /**
   * Builds one Stage instance.
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

    // Autoplay must not rewrite the address at every step.
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

    const controls = document.createElement('div');
    controls.className = 'pb-pattern-stage-controls';
    const play = document.createElement('button');
    play.type = 'button';
    play.className = 'pb-ui-btn pb-pattern-stage-play control-btn';
    const label = document.createElement('span');
    play.appendChild(label);
    const count = document.createElement('p');
    count.className = 'pb-pattern-stage-count';
    const now = document.createElement('span');
    now.className = 'pb-pattern-stage-count-now';
    count.append(now, ` / ${total}`);
    controls.append(play, count);
    (root.querySelector('.pb-content__full') || sections).appendChild(controls);

    const setPlaying = (playing) => {
      root.classList.toggle(PLAYING, playing);
      play.classList.toggle('is-stopped', !playing);
      label.textContent = playing ? Drupal.t('Pause') : Drupal.t('Play');
    };
    const playing = () => root.classList.contains(PLAYING);

    // Holding pauses the line, and the line is the clock. A visitor who has
    // moved focus or the pointer into the stage has control of it.
    const hold = (value) => root.classList.toggle(HELD, value);
    root.addEventListener('pointerenter', () => hold(true), { signal });
    root.addEventListener('pointerleave', () => hold(false), { signal });
    root.addEventListener('focusin', () => hold(true), { signal });
    root.addEventListener(
      'focusout',
      (event) => {
        if (!root.contains(event.relatedTarget)) {
          hold(false);
        }
      },
      { signal },
    );
    document.addEventListener('visibilitychange', () => hold(document.hidden), {
      signal,
    });

    // A step ends when its progress line has filled.
    sections.addEventListener(
      'animationend',
      (event) => {
        if (
          event.animationName === ANIMATION &&
          playing() &&
          !root.classList.contains(HELD)
        ) {
          open(current() + 1);
        }
      },
      { signal },
    );

    // A step the visitor picks stops autoplay for good.
    sections.addEventListener(
      'click',
      (event) => {
        if (event.isTrusted && event.target.closest('.pb-accordion__button')) {
          setPlaying(false);
        }
      },
      { signal },
    );
    play.addEventListener(
      'click',
      () => {
        setPlaying(!playing());
        if (playing() && current() < 0) {
          open(0);
        }
      },
      { signal },
    );

    const render = () => {
      now.textContent = String(current() + 1);
    };
    render();
    const stateObserver = new MutationObserver(render);
    buttons.forEach((button) =>
      stateObserver.observe(button, {
        attributes: true,
        attributeFilter: ['aria-expanded'],
      }),
    );

    setPlaying(!window.matchMedia('(prefers-reduced-motion: reduce)').matches);

    // Open the first step without PB's page-load scroll.
    if (current() < 0) {
      open(0);
    }

    signal.addEventListener(
      'abort',
      () => {
        paneObserver.disconnect();
        stateObserver.disconnect();
        controls.remove();
        root.classList.remove(PLAYING, HELD);
        panes.forEach((pane) => {
          pane.inert = false;
        });
      },
      { once: true },
    );
  };

  Drupal.behaviors.pbPatternAccordionStage = {
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
