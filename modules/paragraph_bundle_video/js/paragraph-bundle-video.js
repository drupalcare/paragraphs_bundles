/**
 * @file
 * Paragraph Bundle Video.
 *
 * Player: swaps the poster for the deferred video when Play is pressed, and
 * stops it when a tab, accordion or modal around it closes.
 * Background: attaches the film's sources only when the viewport, motion and
 * data settings allow it, and offers a pause button.
 *
 * Filename:     paragraph-bundle-video.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing

((Drupal, once) => {
  const PB_VIDEO = '.paragraphs-bundles.pb-video';
  const ONCE_PB_VIDEO = 'pb-paragraph-bundle-video';
  const BACKGROUND_CLASS = 'pb-video--background';
  const FILM_PLAYING_CLASS = 'is-film-playing';

  /** @type {WeakMap<HTMLElement, AbortController>} */
  const videoAbortControllers = new WeakMap();

  /**
   * Returns the ancestors whose aria-hidden state hides this paragraph.
   *
   * PB's tabs and accordions keep aria-hidden on their panes, and its modal
   * drops the attribute while open, so an open modal is found by its role.
   *
   * @param {HTMLElement} root
   *   The paragraph.
   * @return {HTMLElement[]}
   *   The hiding ancestors, nearest first.
   */
  const hidingAncestors = (root) => {
    const hosts = [];
    for (let el = root.parentElement; el; el = el.parentElement) {
      if (
        el.hasAttribute('aria-hidden') ||
        el.getAttribute('role') === 'dialog'
      ) {
        hosts.push(el);
      }
    }
    return hosts;
  };

  /**
   * Starts playback that may legitimately be refused.
   *
   * @param {HTMLMediaElement} media
   *   The video.
   * @param {Function} onRefused
   *   Runs when the browser refuses to play.
   */
  const startPlayback = (media, onRefused) => {
    const started = media.play();
    if (started) {
      started.catch(onRefused);
    }
  };

  /**
   * @param {HTMLElement} root
   *   The paragraph.
   * @param {AbortSignal} signal
   *   Removes the listeners.
   */
  const initPlayer = (root, signal) => {
    const stage = root.querySelector(
      ':scope > .pb-video__inner > [data-pb-video-stage]',
    );
    if (!stage) {
      return;
    }
    const poster = stage.querySelector(':scope > [data-pb-video-poster]');
    const frame = stage.querySelector(':scope > [data-pb-video-frame]');
    const deferred = stage.querySelector(':scope > [data-pb-video-deferred]');
    const button = stage.querySelector(':scope > [data-pb-video-play]');
    const link = stage.querySelector(':scope > [data-pb-video-link]');
    if (!poster || !frame || !deferred || !button) {
      return;
    }

    let observer = null;

    const stopWatching = () => {
      if (observer) {
        observer.disconnect();
        observer = null;
      }
    };

    const stop = () => {
      const video = frame.querySelector('video');
      if (video) {
        video.pause();
        return;
      }
      // A remote player cannot be paused from here, so it is removed.
      stopWatching();
      frame.replaceChildren();
      frame.hidden = true;
      poster.hidden = false;
      button.hidden = false;
    };

    const watchHidingAncestors = () => {
      const hosts = hidingAncestors(root);
      if (hosts.length === 0) {
        return;
      }
      observer = new MutationObserver(() => {
        if (hosts.some((host) => host.getAttribute('aria-hidden') === 'true')) {
          stop();
        }
      });
      hosts.forEach((host) => {
        observer.observe(host, {
          attributes: true,
          attributeFilter: ['aria-hidden'],
        });
      });
    };

    const play = () => {
      frame.replaceChildren(deferred.content.cloneNode(true));
      frame.hidden = false;
      poster.hidden = true;
      button.hidden = true;
      watchHidingAncestors();

      const video = frame.querySelector('video');
      if (video) {
        video.focus();
        // Refusal is not an error here: the controls remain for the visitor.
        startPlayback(video, () => {});
        return;
      }
      frame.querySelector('iframe')?.focus();
    };

    button.addEventListener('click', play, { signal });
    signal.addEventListener('abort', stopWatching, { once: true });

    button.hidden = false;
    if (link) {
      link.hidden = true;
    }
  };

  /**
   * @param {HTMLElement} root
   *   The paragraph.
   * @param {AbortSignal} signal
   *   Removes the listeners.
   */
  const initBackground = (root, signal) => {
    const film = root.querySelector(':scope > .pb-video__film');
    const sources = root.querySelector(':scope > [data-pb-video-sources]');
    const pause = root.querySelector(':scope > [data-pb-video-pause]');
    const pauseText = pause?.querySelector('[data-pb-video-pause-text]');
    if (!film || !sources || !pause || !pauseText) {
      return;
    }

    film.muted = true;

    const breakpoint = parseInt(root.dataset.pbVideoBreakpoint || '0', 10);
    const wideEnough = window.matchMedia(`(min-width: ${breakpoint}px)`);
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const connection = navigator.connection || null;

    let attached = false;
    let pausedByVisitor = false;

    const allowed = () =>
      wideEnough.matches &&
      !reducedMotion.matches &&
      !(connection && connection.saveData) &&
      !root.closest('[aria-hidden="true"]');

    const showPoster = () => {
      root.classList.remove(FILM_PLAYING_CLASS);
      pause.hidden = true;
    };

    const syncPauseButton = () => {
      const paused = film.paused;
      pause.classList.toggle('is-paused', paused);
      pauseText.textContent = paused
        ? pause.dataset.labelPlay
        : pause.dataset.labelPause;
    };

    const detachFilm = () => {
      film.pause();
      film.replaceChildren();
      film.removeAttribute('src');
      film.load();
      attached = false;
      showPoster();
    };

    const update = () => {
      if (!allowed()) {
        if (attached) {
          detachFilm();
        }
        return;
      }
      if (!attached) {
        film.replaceChildren(sources.content.cloneNode(true));
        film.load();
        attached = true;
      }
      if (!pausedByVisitor) {
        startPlayback(film, showPoster);
      }
    };

    film.addEventListener(
      'playing',
      () => {
        root.classList.add(FILM_PLAYING_CLASS);
        pause.hidden = false;
        syncPauseButton();
      },
      { signal },
    );
    film.addEventListener('pause', syncPauseButton, { signal });
    // A failed <source> reports on the source and does not bubble.
    film.addEventListener('error', showPoster, { capture: true, signal });

    pause.addEventListener(
      'click',
      () => {
        if (film.paused) {
          pausedByVisitor = false;
          startPlayback(film, showPoster);
        } else {
          pausedByVisitor = true;
          film.pause();
        }
      },
      { signal },
    );

    // A closed Tab, Accordion or Modal starts the film when it opens and
    // stops it when it closes.
    const hosts = hidingAncestors(root);
    if (hosts.length > 0) {
      const observer = new MutationObserver(update);
      hosts.forEach((host) => {
        observer.observe(host, {
          attributes: true,
          attributeFilter: ['aria-hidden'],
        });
      });
      signal.addEventListener('abort', () => observer.disconnect(), {
        once: true,
      });
    }

    wideEnough.addEventListener('change', update, { signal });
    reducedMotion.addEventListener('change', update, { signal });
    if (connection && connection.addEventListener) {
      connection.addEventListener('change', update, { signal });
    }

    update();
  };

  /**
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.paragraphBundleVideo = {
    attach(context) {
      once(ONCE_PB_VIDEO, PB_VIDEO, context).forEach((root) => {
        const abortController = new AbortController();
        videoAbortControllers.set(root, abortController);
        if (root.classList.contains(BACKGROUND_CLASS)) {
          initBackground(root, abortController.signal);
        } else {
          initPlayer(root, abortController.signal);
        }
      });
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
      once.remove(ONCE_PB_VIDEO, PB_VIDEO, context).forEach((root) => {
        const abortController = videoAbortControllers.get(root);
        if (abortController) {
          abortController.abort();
          videoAbortControllers.delete(root);
        }
      });
    },
  };
})(Drupal, once);
