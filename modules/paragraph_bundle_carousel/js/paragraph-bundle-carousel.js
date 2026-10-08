/**
 * @file
 * Paragraph Bundle Carousel.
 *
 * Carousel with horizontal, vertical, and hybrid layouts, touch, keyboard,
 * dots, and autoplay. Follows Drupal 11 contrib best practices.
 *
 * Filename:     paragraph-bundle-carousel.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing

((Drupal, once) => {
  const PARAGRAPHS_BUNDLES = 'paragraphs-bundles';
  const PB_CAROUSEL = 'pb-carousel';
  const PB_CAROUSEL__VIEWPORT = 'pb-carousel__viewport';
  const PB_CAROUSEL__WRAPPER = 'pb-carousel__wrapper';
  const PB_CAROUSEL__SLIDES = 'pb-carousel__slides';
  const PB_CAROUSEL__SLIDE = 'pb-carousel__slide';
  const PB_CAROUSEL__DOTS = 'pb-carousel__dots';
  const PB_CAROUSEL__DOT = 'pb-carousel__dot';
  const PB_CAROUSEL__OPTIONS_BAR = 'pb-carousel__options-bar';
  const PB_CAROUSEL__ANNOUNCER = 'pb-carousel__announcer';
  const PB_NAV__PREV = 'pb-nav__prev';
  const PB_NAV__NEXT = 'pb-nav__next';
  const PB_CAROUSEL__BOTTOM_BAR = 'pb-carousel__bottom-bar';
  const PB_CAROUSEL__PLAY_PAUSE = 'pb-carousel__play-pause';
  const PB_IS_ACTIVE = 'pb-is-active';
  const PB_IS_INACTIVE = 'pb-is-inactive';
  const PB_IS_VISIBLE = 'pb-is-visible';
  const PB_U_SMALL_SCREEN = 'pb-u-small-screen';
  const PB_U_LARGE_SCREEN = 'pb-u-large-screen';

  const PARAGRAPHS_BUNDLES_PB_CAROUSEL = `.${PARAGRAPHS_BUNDLES}.${PB_CAROUSEL}`;
  const PARAGRAPHS_BUNDLES_PB_CAROUSEL_PB_CAROUSEL__VIEWPORT = `${PARAGRAPHS_BUNDLES_PB_CAROUSEL} .${PB_CAROUSEL__VIEWPORT}`;

  const PB_CAROUSEL__VIEWPORT_OWNED_WRAPPER = `:scope > .${PB_CAROUSEL__WRAPPER}`;
  const PB_CAROUSEL__VIEWPORT_OWNED_SLIDES = `:scope > .${PB_CAROUSEL__WRAPPER} > .${PB_CAROUSEL__SLIDES}`;
  const PB_CAROUSEL__VIEWPORT_OWNED_SLIDE = `:scope > .${PB_CAROUSEL__WRAPPER} > .${PB_CAROUSEL__SLIDES} > .${PB_CAROUSEL__SLIDE}`;
  const PB_CAROUSEL__VIEWPORT_OWNED_DOTS = `:scope > .${PB_CAROUSEL__BOTTOM_BAR} > .${PB_CAROUSEL__DOTS}`;
  const PB_CAROUSEL__VIEWPORT_OWNED_OPTIONS_BAR = `:scope > .${PB_CAROUSEL__OPTIONS_BAR}`;
  const PB_CAROUSEL__VIEWPORT_OWNED_PREV = `:scope > .${PB_CAROUSEL__OPTIONS_BAR} > .${PB_NAV__PREV}`;
  const PB_CAROUSEL__VIEWPORT_OWNED_NEXT = `:scope > .${PB_CAROUSEL__OPTIONS_BAR} > .${PB_NAV__NEXT}`;
  const PB_CAROUSEL__VIEWPORT_OWNED_PLAY_PAUSE = `:scope > .${PB_CAROUSEL__BOTTOM_BAR} > .${PB_CAROUSEL__PLAY_PAUSE}`;

  const ONCE_PB_PARAGRAPH_BUNDLE_CAROUSEL_VIEWPORT =
    'pb-paragraph-bundle-carousel-viewport';

  /**
   * Debounce utility to limit function execution frequency.
   *
   * @param {Function} func - The function to debounce.
   * @param {number} delay - Delay in milliseconds.
   * @return {Function} Debounced function.
   */
  const debounce = (func, delay) => {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => func(...args), delay);
    };
  };

  /** @type {WeakMap<HTMLElement, () => void>} */
  const viewportCleanupHandlers = new WeakMap();

  /**
   * Creates the state object for a carousel instance.
   *
   * @param {HTMLElement} viewport - L3 carousel host element.
   * @return {Object|undefined} Carousel state or undefined when invalid.
   */
  function createState(viewport) {
    const wrapper = viewport.querySelector(PB_CAROUSEL__VIEWPORT_OWNED_WRAPPER);
    const itemsContainer = viewport.querySelector(
      PB_CAROUSEL__VIEWPORT_OWNED_SLIDES,
    );

    if (!wrapper || !itemsContainer) {
      return undefined;
    }

    const items = viewport.querySelectorAll(PB_CAROUSEL__VIEWPORT_OWNED_SLIDE);
    const nextButton = viewport.querySelector(PB_CAROUSEL__VIEWPORT_OWNED_NEXT);
    const prevButton = viewport.querySelector(PB_CAROUSEL__VIEWPORT_OWNED_PREV);
    const playPauseButton = viewport.querySelector(
      PB_CAROUSEL__VIEWPORT_OWNED_PLAY_PAUSE,
    );
    const announcer = viewport.querySelector(
      `:scope > .${PB_CAROUSEL__ANNOUNCER}`,
    );
    const dataset = itemsContainer.dataset;
    const breakpoints = Number.parseInt(dataset.breakpoints, 10) || 0;

    const config = {
      rawOrientation: dataset.orientation,
      orientation: 'horizontal',
      breakpoints,
      itemsSmall: Number.parseInt(dataset.smallScreen, 10) || 1,
      itemsBig: Number.parseInt(dataset.bigScreen, 10) || 1,
      totalSlides: Number.parseInt(dataset.totalSlides, 10) || 0,
      slideTime: Number.parseInt(dataset.slideTime, 10) || 0,
      gap: Number.parseInt(dataset.gap, 10) || 0,
      looping: dataset.carouselLoop === '1',
      navigation: dataset.navigation || 'arrows',
    };

    return {
      viewport,
      wrapper,
      itemsContainer,
      items,
      nextButton,
      prevButton,
      playPauseButton,
      announcer,
      config,
      pageIndex: 0,
      isPaused: false,
      autoSlideTimer: null,
      centerOffset: 0,
    };
  }

  /**
   * Resolves the current orientation based on viewport width and settings.
   *
   * @param {Object} state - The carousel state object.
   * @return {string} The resolved orientation.
   */
  function resolveOrientation(state) {
    const raw = state.config.rawOrientation;
    const bp = state.config.breakpoints;

    if (raw === 'vertical') {
      return 'vertical';
    }
    if (raw === 'hybrid' && window.innerWidth <= bp) {
      return 'vertical';
    }
    return 'horizontal';
  }

  /**
   * Checks if the current viewport is considered a small screen.
   *
   * @param {Object} state - The carousel state object.
   * @return {boolean} True if viewport is at or below the breakpoint.
   */
  function isSmallScreen(state) {
    return window.innerWidth <= state.config.breakpoints;
  }

  /**
   * Gets the size of an item based on orientation.
   *
   * @param {HTMLElement} item - The carousel item element.
   * @param {boolean} isVertical - Whether the carousel is in vertical mode.
   * @return {number} The item height or width.
   */
  function getItemSize(item, isVertical) {
    return isVertical ? item.offsetHeight : item.offsetWidth;
  }

  /**
   * Calculates the number of visible items based on viewport and settings.
   *
   * @param {Object} state - The carousel state object.
   * @return {number} The number of items visible at once.
   */
  function getItemsVisible(state) {
    const isVertical = state.config.orientation === 'vertical';
    const isHybrid = state.config.rawOrientation === 'hybrid';
    const smallScreen = isSmallScreen(state);

    const max = smallScreen ? state.config.itemsSmall : state.config.itemsBig;

    if (isVertical) {
      return Math.min(max, state.items.length);
    }

    if (isHybrid && smallScreen) {
      return Math.min(max, state.items.length);
    }

    if (smallScreen) {
      return Math.min(max, state.items.length);
    }

    const containerSize = state.wrapper.offsetWidth;
    const gap = state.config.gap || 0;
    let total = 0;
    let count = 0;

    for (let i = 0; i < state.items.length; i++) {
      const itemSize = state.items[i].offsetWidth;
      if (i > 0) {
        total += gap;
      }
      total += itemSize;

      if (total > containerSize) {
        break;
      }
      count++;
      if (count >= max) {
        break;
      }
    }

    return Math.max(1, count);
  }

  /**
   * Gets the currently visible items based on page index.
   *
   * @param {Object} state - The carousel state object.
   * @return {HTMLElement[]} Array of visible item elements.
   */
  function getVisibleItems(state) {
    const itemsVisible = getItemsVisible(state);
    const start = state.pageIndex * itemsVisible;
    return Array.from(state.items).slice(start, start + itemsVisible);
  }

  /**
   * Calculates the scroll offset for the current page.
   *
   * @param {Object} state - The carousel state object.
   * @return {number} The offset in pixels.
   */
  function getGroupOffset(state) {
    const itemsVisible = getItemsVisible(state);
    const isVertical = state.config.orientation === 'vertical';
    const gap = state.config.gap || 0;
    let offset = 0;

    for (let page = 0; page < state.pageIndex; page++) {
      const start = page * itemsVisible;
      const end = start + itemsVisible;
      for (let i = start; i < end && i < state.items.length; i++) {
        const size = getItemSize(state.items[i], isVertical);
        offset += size;
        if (i < end - 1) {
          offset += gap;
        }
      }
      if (end < state.items.length) {
        offset += gap;
      }
    }
    return offset;
  }

  /**
   * Sets the wrapper size based on visible items.
   *
   * @param {Object} state - The carousel state object.
   */
  function setWrapperSize(state) {
    const itemsVisible = getItemsVisible(state);
    if (!state.items.length) {
      return;
    }

    const visibleItems = getVisibleItems(state);
    const gap = state.config.gap || 0;
    const totalGap = (itemsVisible - 1) * gap;

    if (state.config.orientation === 'vertical') {
      const totalHeight = visibleItems.reduce((sum, item) => {
        return sum + getItemSize(item, true);
      }, 0);
      state.wrapper.style.maxHeight = `${totalHeight + totalGap}px`;
      state.wrapper.style.height = '';
      state.wrapper.style.maxWidth = '';
      state.centerOffset = 0;
    } else {
      const totalWidth = visibleItems.reduce((sum, item, idx) => {
        return sum + getItemSize(item, false) + (idx > 0 ? gap : 0);
      }, 0);
      const wrapperWidth = state.wrapper.offsetWidth;

      if (totalWidth > 0 && totalWidth <= wrapperWidth) {
        state.centerOffset = Math.max(0, (wrapperWidth - totalWidth) / 2);
      } else {
        state.centerOffset = 0;
      }

      state.wrapper.style.maxWidth = '';
      state.wrapper.style.width = '';
      state.wrapper.style.maxHeight = '';
    }

    if (state.wrapper.style.visibility !== 'visible') {
      state.wrapper.style.visibility = 'visible';
      state.wrapper.classList.add(PB_IS_VISIBLE);
    }
  }

  /**
   * Scrolls to the current page position.
   *
   * @param {Object} state - The carousel state object.
   */
  function scrollToPage(state) {
    const offset = getGroupOffset(state);
    const centering = state.centerOffset || 0;
    const transform = centering - offset;

    if (state.config.orientation === 'vertical') {
      state.itemsContainer.style.transform = `translateY(${-offset}px)`;
    } else {
      state.itemsContainer.style.transform = `translateX(${transform}px)`;
    }
  }

  /**
   * Updates navigation button visibility.
   *
   * @param {Object} state - The carousel state object.
   */
  function updateNavVisibility(state) {
    if (
      state.config.navigation !== 'arrows' &&
      state.config.navigation !== 'both'
    ) {
      return;
    }

    const itemsVisible = getItemsVisible(state);
    const maxPage = Math.ceil(state.config.totalSlides / itemsVisible) - 1;

    if (!state.config.looping) {
      if (state.prevButton) {
        state.prevButton.style.visibility =
          state.pageIndex === 0 ? 'hidden' : 'visible';
      }
      if (state.nextButton) {
        state.nextButton.style.visibility =
          state.pageIndex >= maxPage ? 'hidden' : 'visible';
      }
    } else {
      if (state.prevButton) {
        state.prevButton.style.visibility = 'visible';
      }
      if (state.nextButton) {
        state.nextButton.style.visibility = 'visible';
      }
    }
  }

  /**
   * Announces the currently visible slides to screen readers.
   *
   * @param {Object} state - The carousel state object.
   * @param {number} start - Start index of visible items.
   * @param {number} end - End index of visible items.
   */
  function announceVisible(state, start, end) {
    if (!state.announcer) {
      return;
    }
    const total = state.config.totalSlides;
    state.announcer.textContent = `Showing slides ${start + 1} to ${Math.min(end, total)} of ${total}`;
  }

  /**
   * Updates the entire carousel layout and state.
   *
   * @param {Object} state - The carousel state object.
   */
  function updateLayout(state) {
    state.config.orientation = resolveOrientation(state);

    const smallScreen = isSmallScreen(state);
    state.wrapper.classList.toggle(PB_U_SMALL_SCREEN, smallScreen);
    state.wrapper.classList.toggle(PB_U_LARGE_SCREEN, !smallScreen);

    setWrapperSize(state);

    const itemsVisible = getItemsVisible(state);
    const start = state.pageIndex * itemsVisible;
    const end = start + itemsVisible;

    state.items.forEach((item, i) => {
      const isActive = i >= start && i < end;
      item.classList.toggle(PB_IS_ACTIVE, isActive);
      item.classList.toggle(PB_IS_INACTIVE, !isActive);
      item.setAttribute('aria-hidden', String(!isActive));
      // Off-page slides are also removed from the tab order / a11y tree.
      item.toggleAttribute('inert', !isActive);
    });

    if (
      state.config.navigation === 'dots' ||
      state.config.navigation === 'both'
    ) {
      const dotsContainer = state.viewport.querySelector(
        PB_CAROUSEL__VIEWPORT_OWNED_DOTS,
      );
      const dots =
        dotsContainer?.querySelectorAll(`.${PB_CAROUSEL__DOT}`) ?? [];

      dots.forEach((dot, index) => {
        const isActive = index === state.pageIndex;
        dot.classList.toggle(PB_IS_ACTIVE, isActive);
        dot.setAttribute('aria-current', isActive ? 'true' : 'false');
      });
    }

    updateNavVisibility(state);
    announceVisible(state, start, end);
    scrollToPage(state);
  }

  /**
   * Advances to the next slide page.
   *
   * @param {Object} state - The carousel state object.
   */
  function nextSlide(state) {
    const itemsVisible = getItemsVisible(state);
    const totalPages = Math.ceil(state.config.totalSlides / itemsVisible);

    if (state.pageIndex < totalPages - 1) {
      state.pageIndex += 1;
    } else {
      state.pageIndex = state.config.looping ? 0 : totalPages - 1;
    }

    updateLayout(state);
  }

  /**
   * Goes to the previous slide page.
   *
   * @param {Object} state - The carousel state object.
   */
  function prevSlide(state) {
    const itemsVisible = getItemsVisible(state);
    const maxPage = Math.ceil(state.config.totalSlides / itemsVisible) - 1;

    if (state.pageIndex > 0) {
      state.pageIndex -= 1;
    } else {
      state.pageIndex = state.config.looping ? maxPage : 0;
    }

    updateLayout(state);
  }

  /**
   * Clamps the page index to valid bounds after resize.
   *
   * @param {Object} state - The carousel state object.
   */
  function clampPageIndex(state) {
    const itemsVisible = getItemsVisible(state);
    const maxPage = Math.ceil(state.config.totalSlides / itemsVisible) - 1;
    state.pageIndex = Math.min(state.pageIndex, Math.max(0, maxPage));
  }

  /**
   * Binds click events to navigation buttons.
   *
   * @param {Object} state - The carousel state object.
   * @param {AbortSignal} signal - Abort signal for listeners.
   * @param {Function} stopAutoSlide - Stops autoplay interval.
   */
  function bindNavigation(state, signal, stopAutoSlide) {
    const shouldAutoplay =
      state.config.slideTime > 0 &&
      state.config.slideTime >= 1000 &&
      state.config.slideTime <= 15000;

    const resetAutoSlide = () => {
      if (shouldAutoplay) {
        stopAutoSlide();
        state.restartAutoSlide?.();
      }
    };

    state.nextButton?.addEventListener(
      'click',
      () => {
        nextSlide(state);
        resetAutoSlide();
      },
      { signal },
    );

    state.prevButton?.addEventListener(
      'click',
      () => {
        prevSlide(state);
        resetAutoSlide();
      },
      { signal },
    );
  }

  /**
   * Binds touch and swipe events.
   *
   * @param {Object} state - The carousel state object.
   * @param {AbortSignal} signal - Abort signal for listeners.
   */
  function bindTouch(state, signal) {
    let startX = 0;
    let startY = 0;

    state.wrapper.addEventListener(
      'touchstart',
      (event) => {
        startX = event.touches[0].clientX;
        startY = event.touches[0].clientY;
      },
      { passive: true, signal },
    );

    state.wrapper.addEventListener(
      'touchend',
      (event) => {
        const deltaX = event.changedTouches[0].clientX - startX;
        const deltaY = event.changedTouches[0].clientY - startY;

        if (Math.abs(deltaX) > Math.abs(deltaY)) {
          if (deltaX < -50) {
            nextSlide(state);
          } else if (deltaX > 50) {
            prevSlide(state);
          }
        } else if (state.config.orientation === 'vertical') {
          if (deltaY < -50) {
            nextSlide(state);
          } else if (deltaY > 50) {
            prevSlide(state);
          }
        }
      },
      { signal },
    );
  }

  /**
   * Creates dot navigation elements.
   *
   * @param {Object} state - The carousel state object.
   */
  function createDots(state) {
    const navigationType = state.config.navigation;
    const dotsContainer = state.viewport.querySelector(
      PB_CAROUSEL__VIEWPORT_OWNED_DOTS,
    );

    if (!dotsContainer) {
      return;
    }

    if (navigationType !== 'dots' && navigationType !== 'both') {
      dotsContainer.replaceChildren();
      return;
    }

    const itemsVisible = getItemsVisible(state);
    const totalPages = Math.ceil(state.config.totalSlides / itemsVisible);
    const dots = [];

    for (let i = 0; i < totalPages; i++) {
      const dot = document.createElement('button');
      dot.className = `${PB_CAROUSEL__DOT} control-btn`;
      dot.setAttribute(
        'aria-label',
        Drupal.t('Go to slide group @n', { '@n': i + 1 }),
      );
      dot.setAttribute('type', 'button');
      dot.dataset.slideGroup = String(i);
      dots.push(dot);
    }

    dotsContainer.replaceChildren(...dots);
  }

  /**
   * Binds dot navigation via event delegation.
   *
   * @param {Object} state - The carousel state object.
   * @param {AbortSignal} signal - Abort signal for listeners.
   */
  function bindDots(state, signal) {
    const dotsContainer = state.viewport.querySelector(
      PB_CAROUSEL__VIEWPORT_OWNED_DOTS,
    );
    if (!dotsContainer) {
      return;
    }

    dotsContainer.addEventListener(
      'click',
      (event) => {
        const dot = event.target.closest(`.${PB_CAROUSEL__DOT}`);
        if (!dot || !dotsContainer.contains(dot)) {
          return;
        }

        const groupIndex = Number.parseInt(dot.dataset.slideGroup ?? '', 10);
        if (Number.isNaN(groupIndex)) {
          return;
        }

        state.pageIndex = groupIndex;
        updateLayout(state);
      },
      { signal },
    );
  }

  /**
   * Sets up automatic slide advancement.
   *
   * @param {Object} state - The carousel state object.
   * @param {AbortSignal} signal - Abort signal for listeners.
   * @param {Function} setObserver - Stores IntersectionObserver reference.
   * @param {Function} stopAutoSlide - Stops autoplay interval.
   */
  function bindAutoSlide(state, signal, setObserver, stopAutoSlide) {
    let interval = Number.parseInt(String(state.config.slideTime), 10);

    if (interval === 0) {
      return;
    }

    if (Number.isNaN(interval) || interval < 1000 || interval > 15000) {
      interval = 5000;
    }

    // Respect prefers-reduced-motion: begin paused so nothing auto-advances;
    // the play/pause control still lets a user opt in.
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      state.isPaused = true;
    }

    let isVisible = true;

    const start = () => {
      stopAutoSlide();
      if (state.isPaused) {
        return;
      }
      state.autoSlideTimer = setInterval(() => nextSlide(state), interval);
    };

    const updatePlayPauseButton = () => {
      const button = state.playPauseButton;
      if (!button) {
        return;
      }
      const paused = state.isPaused;
      button.classList.toggle('pb-is-playing', !paused);
      button.classList.toggle('pb-is-paused', paused);
      button.setAttribute('aria-pressed', String(!paused));
      button.setAttribute(
        'aria-label',
        paused ? Drupal.t('Play slideshow') : Drupal.t('Pause slideshow'),
      );
      button
        .querySelector('.pb-nav__play-icon')
        ?.toggleAttribute('hidden', !paused);
      button
        .querySelector('.pb-nav__pause-icon')
        ?.toggleAttribute('hidden', paused);
    };

    const togglePlayPause = () => {
      state.isPaused = !state.isPaused;
      updatePlayPauseButton();
      if (state.isPaused) {
        stopAutoSlide();
      } else {
        start();
      }
    };

    state.playPauseButton?.addEventListener('click', togglePlayPause, {
      signal,
    });
    updatePlayPauseButton();

    state.wrapper.addEventListener('mouseenter', stopAutoSlide, { signal });
    state.wrapper.addEventListener(
      'mouseleave',
      () => {
        if (isVisible) {
          start();
        }
      },
      { signal },
    );

    document.addEventListener(
      'visibilitychange',
      () => {
        if (document.hidden) {
          stopAutoSlide();
        } else if (isVisible) {
          start();
        }
      },
      { signal },
    );

    const observer = new IntersectionObserver(
      (entries) => {
        isVisible = entries[0].isIntersecting;
        if (isVisible) {
          start();
        } else {
          stopAutoSlide();
        }
      },
      {
        threshold: 0.3,
      },
    );

    observer.observe(state.viewport);
    setObserver(observer);

    state.restartAutoSlide = start;
    start();
  }

  /**
   * Binds keyboard navigation events.
   *
   * @param {Object} state - The carousel state object.
   * @param {AbortSignal} signal - Abort signal for listeners.
   */
  function bindKeyboard(state, signal) {
    const carouselRoot = state.viewport.closest(PARAGRAPHS_BUNDLES_PB_CAROUSEL);
    if (!carouselRoot) {
      return;
    }

    carouselRoot.addEventListener(
      'keydown',
      (event) => {
        const activeElement = document.activeElement;
        if (!(activeElement instanceof HTMLElement)) {
          return;
        }

        const activeCarouselRoot = activeElement.closest(
          PARAGRAPHS_BUNDLES_PB_CAROUSEL,
        );
        if (
          activeElement !== carouselRoot &&
          activeCarouselRoot !== carouselRoot
        ) {
          return;
        }

        const orientation = state.config.orientation;

        if (
          (orientation === 'horizontal' && event.key === 'ArrowRight') ||
          (orientation === 'vertical' && event.key === 'ArrowDown')
        ) {
          event.preventDefault();
          nextSlide(state);
        }

        if (
          (orientation === 'horizontal' && event.key === 'ArrowLeft') ||
          (orientation === 'vertical' && event.key === 'ArrowUp')
        ) {
          event.preventDefault();
          prevSlide(state);
        }
      },
      { signal },
    );
  }

  Drupal.behaviors.paragraphCarouselBundle = {
    attach(context, settings) {
      const viewports = once(
        ONCE_PB_PARAGRAPH_BUNDLE_CAROUSEL_VIEWPORT,
        PARAGRAPHS_BUNDLES_PB_CAROUSEL_PB_CAROUSEL__VIEWPORT,
        context,
      );

      viewports.forEach((viewport) => {
        const abortController = new AbortController();
        const { signal } = abortController;

        const state = createState(viewport);
        if (!state) {
          return;
        }

        let resizeObserver = null;
        let intersectionObserver = null;

        const stopAutoSlide = () => {
          if (state.autoSlideTimer) {
            clearInterval(state.autoSlideTimer);
            state.autoSlideTimer = null;
          }
        };

        const cleanup = () => {
          abortController.abort();
          resizeObserver?.disconnect();
          intersectionObserver?.disconnect();
          stopAutoSlide();
        };

        viewportCleanupHandlers.set(viewport, cleanup);

        bindAutoSlide(
          state,
          signal,
          (observer) => {
            intersectionObserver = observer;
          },
          stopAutoSlide,
        );
        bindNavigation(state, signal, stopAutoSlide);
        bindKeyboard(state, signal);
        bindTouch(state, signal);
        bindDots(state, signal);

        const debouncedResize = debounce(() => {
          updateLayout(state);
          clampPageIndex(state);
          createDots(state);
          updateLayout(state);
        }, 200);

        if ('ResizeObserver' in window && state.wrapper) {
          resizeObserver = new ResizeObserver(() => updateLayout(state));
          resizeObserver.observe(state.wrapper);
        }

        window.addEventListener('resize', debouncedResize, { signal });

        requestAnimationFrame(() => {
          requestAnimationFrame(() => {
            updateLayout(state);
            createDots(state);
            setTimeout(() => {
              updateLayout(state);
            }, 100);
          });
        });
      });
    },

    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }

      once
        .remove(
          ONCE_PB_PARAGRAPH_BUNDLE_CAROUSEL_VIEWPORT,
          PARAGRAPHS_BUNDLES_PB_CAROUSEL_PB_CAROUSEL__VIEWPORT,
          context,
        )
        .forEach((viewport) => {
          viewportCleanupHandlers.get(viewport)?.();
          viewportCleanupHandlers.delete(viewport);
        });
    },
  };
})(Drupal, once);
