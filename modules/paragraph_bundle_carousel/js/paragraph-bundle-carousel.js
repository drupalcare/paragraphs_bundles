/**
 * @file
 * Paragraph Bundle Carousel.
 *
 * Provides carousel functionality with support for horizontal, vertical,
 * and hybrid layouts, touch/swipe navigation, keyboard controls, and
 * optional dot navigation.
 *
 * Filename:     paragraph-bundle-carousel.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, drupalSettings, once) => {
  'use strict';

  /**
   * Debounce utility to limit function execution frequency.
   *
   * @param {Function} func - The function to debounce.
   * @param {number} delay - Delay in milliseconds.
   * @returns {Function} Debounced function.
   */
  function debounce(func, delay) {
    let timer;
    return function(...args) {
      clearTimeout(timer);
      timer = setTimeout(() => func.apply(this, args), delay);
    };
  }

  /**
   * Drupal behavior for carousel functionality.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.paragraphCarouselBundle = {
    attach(context) {
      const carousels = once('paragraphCarouselBundle', '.pb__carousel__inner', context);
      carousels.forEach(initCarousel);
    }
  };

  /**
   * Initializes a single carousel instance.
   *
   * @param {HTMLElement} carouselInner - The carousel container element.
   */
  function initCarousel(carouselInner) {
    const state = createState(carouselInner);
    if (!state) {
      return;
    }

    bindNavigation(state);
    bindTouch(state);
    bindAutoSlide(state);
    bindKeyboard(state);

    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        updateLayout(state);
        createDots(state);
        bindDots(state);
        state.container.removeAttribute('hidden');
        setTimeout(() => {
          updateLayout(state);
        }, 100);
      });
    });

    // Observe wrapper resize for responsive updates
    if ('ResizeObserver' in window && state.wrapper) {
      const observer = new ResizeObserver(() => updateLayout(state));
      observer.observe(state.wrapper);
    }

    const refreshDots = debounce(() => {
      createDots(state);
      bindDots(state);
    }, 300);

    window.addEventListener('resize', () => {
      clampPageIndex(state);
      updateLayout(state);
      refreshDots();
    });
  }

  /**
   * Creates the state object for a carousel instance.
   *
   * @param {HTMLElement} container - The carousel container element.
   * @returns {Object|undefined} The carousel state object or undefined if invalid.
   */
  function createState(container) {
    const wrapper = container.querySelector('.pb__carousel-wrapper');
    const itemsContainer = wrapper?.querySelector('.pb__carousel-items');

    if (!wrapper || !itemsContainer) {
      return;
    }

    const items = itemsContainer.querySelectorAll('.pb__carousel-item');
    const nextButton = container.querySelector('.pb__next');
    const prevButton = container.querySelector('.pb__prev');
    const announcer = container.querySelector('.pb__carousel-announcer');
    const dataset = itemsContainer.dataset;
    const breakpoints = parseInt(dataset.breakpoints, 10) || 0;

    const config = {
      rawOrientation: dataset.orientation,
      orientation: 'horizontal',
      breakpoints,
      itemsSmall: parseInt(dataset.smallScreen, 10) || 1,
      itemsBig: parseInt(dataset.bigScreen, 10) || 1,
      totalSlides: parseInt(dataset.totalSlides, 10) || 0,
      slideTime: parseInt(dataset.slideTime, 10) || 0,
      gap: parseInt(dataset.gap, 10) || 0,
      looping: dataset.carouselLoop === '1',
      navigation: dataset.navigation || 'arrows',
    };

    return {
      container,
      wrapper,
      itemsContainer,
      items,
      nextButton,
      prevButton,
      announcer,
      config,
      pageIndex: 0,
      autoSlideTimer: null,
      centerOffset: 0
    };
  }

  /**
   * Resolves the current orientation based on viewport width and settings.
   *
   * @param {Object} state - The carousel state object.
   * @returns {string} The resolved orientation ('horizontal' or 'vertical').
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
   * @returns {boolean} True if viewport is at or below the breakpoint.
   */
  function isSmallScreen(state) {
    return window.innerWidth <= state.config.breakpoints;
  }

  /**
   * Gets the size of an item based on orientation.
   *
   * @param {HTMLElement} item - The carousel item element.
   * @param {boolean} isVertical - Whether the carousel is in vertical mode.
   * @returns {number} The item's height or width.
   */
  function getItemSize(item, isVertical) {
    return isVertical ? item.offsetHeight : item.offsetWidth;
  }

  /**
   * Calculates the number of visible items based on viewport and settings.
   *
   * @param {Object} state - The carousel state object.
   * @returns {number} The number of items visible at once.
   */
  function getItemsVisible(state) {
    const isVertical = state.config.orientation === 'vertical';
    const isHybrid = state.config.rawOrientation === 'hybrid';
    const smallScreen = isSmallScreen(state);

    const max = smallScreen
      ? state.config.itemsSmall
      : state.config.itemsBig;

    // For vertical layout, always return the configured max
    if (isVertical) {
      return Math.min(max, state.items.length);
    }

    // For hybrid layout on small screens (acts as vertical), return max
    if (isHybrid && smallScreen) {
      return Math.min(max, state.items.length);
    }

    // On small screens with pure horizontal layout, trust the configured value
    // This prevents the smart-fit logic from causing centering issues
    if (smallScreen) {
      return Math.min(max, state.items.length);
    }

    // Large screen horizontal (or hybrid in horizontal mode): smart fit logic
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
   * @returns {HTMLElement[]} Array of visible item elements.
   */
  function getVisibleItems(state) {
    const itemsVisible = getItemsVisible(state);
    const start = state.pageIndex * itemsVisible;
    return Array.from(state.items).slice(start, start + itemsVisible);
  }

  /**
   * Gets the maximum item size among visible items.
   *
   * @param {Object} state - The carousel state object.
   * @returns {number} The maximum item size.
   */
  function getMaxItemSize(state) {
    const isVertical = state.config.orientation === 'vertical';
    return getVisibleItems(state).reduce((max, item) => {
      const size = getItemSize(item, isVertical);
      return Math.max(max, size);
    }, 0);
  }

  /**
   * Calculates the total width of visible items including gaps.
   *
   * @param {Object} state - The carousel state object.
   * @returns {number} The total width in pixels.
   */
  function getVisibleItemsTotalWidth(state) {
    const visibleItems = getVisibleItems(state);
    const gap = state.config.gap || 0;

    return visibleItems.reduce((sum, item, idx) => {
      return sum + getItemSize(item, false) + (idx > 0 ? gap : 0);
    }, 0);
  }

  /**
   * Calculates the scroll offset for the current page.
   *
   * @param {Object} state - The carousel state object.
   * @returns {number} The offset in pixels.
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
      // Horizontal mode
      const totalWidth = visibleItems.reduce((sum, item, idx) => {
        return sum + getItemSize(item, false) + (idx > 0 ? gap : 0);
      }, 0);
      const wrapperWidth = state.wrapper.offsetWidth;

      // Calculate centering offset
      if (totalWidth > 0 && totalWidth <= wrapperWidth) {
        state.centerOffset = Math.max(0, (wrapperWidth - totalWidth) / 2);
      } else {
        // Items exceed wrapper width - no centering, align to start
        state.centerOffset = 0;
      }

      state.wrapper.style.maxWidth = '';
      state.wrapper.style.width = '';
      state.wrapper.style.maxHeight = '';
    }

    if (state.wrapper.style.visibility !== 'visible') {
      state.wrapper.style.visibility = 'visible';
      state.wrapper.classList.add('is-visible');
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
   * Updates the entire carousel layout and state.
   *
   * @param {Object} state - The carousel state object.
   */
  function updateLayout(state) {
    state.config.orientation = resolveOrientation(state);

    // Apply small screen class for CSS targeting
    const smallScreen = isSmallScreen(state);
    state.wrapper.classList.toggle('pb__small-screen', smallScreen);
    state.wrapper.classList.toggle('pb__large-screen', !smallScreen);

    setWrapperSize(state);

    const itemsVisible = getItemsVisible(state);
    const start = state.pageIndex * itemsVisible;
    const end = start + itemsVisible;

    state.items.forEach((item, i) => {
      const isActive = i >= start && i < end;
      item.classList.toggle('active-slide', isActive);
      item.classList.toggle('non-active-slide', !isActive);
      item.setAttribute('aria-hidden', String(!isActive));
    });

    // Update dots active state and scroll active dot into view
    if (state.config.navigation === 'dots' || state.config.navigation === 'both') {
      const dotsContainer = state.container.querySelector('.pb__carousel-dots');
      const dots = state.container.querySelectorAll('.pb__carousel-dot');

      dots.forEach((dot, index) => {
        const isActive = index === state.pageIndex;
        dot.classList.toggle('pb__active', isActive);
        dot.setAttribute('aria-current', isActive ? 'true' : 'false');

        // Scroll active dot into view within the container
        if (isActive && dotsContainer) {
          scrollDotIntoView(dotsContainer, dot);
        }
      });
    }

    updateNavVisibility(state);
    announceVisible(state, start, end);
    scrollToPage(state);
  }

  /**
   * Scrolls the active dot into view within the dots container.
   *
   * @param {HTMLElement} container - The dots container element.
   * @param {HTMLElement} dot - The active dot element.
   */
  function scrollDotIntoView(container, dot) {
    const containerRect = container.getBoundingClientRect();
    const dotRect = dot.getBoundingClientRect();

    // Calculate dot position relative to container
    const dotLeft = dotRect.left - containerRect.left + container.scrollLeft;
    const dotRight = dotLeft + dotRect.width;
    const containerWidth = container.clientWidth;
    const scrollLeft = container.scrollLeft;

    // Check if dot is outside visible area
    if (dotLeft < scrollLeft) {
      // Dot is to the left of visible area
      container.scrollTo({
        left: dotLeft - 16,
        behavior: 'smooth'
      });
    } else if (dotRight > scrollLeft + containerWidth) {
      // Dot is to the right of visible area
      container.scrollTo({
        left: dotRight - containerWidth + 16,
        behavior: 'smooth'
      });
    }
  }

  /**
   * Updates the visibility of navigation buttons.
   *
   * @param {Object} state - The carousel state object.
   */
  function updateNavVisibility(state) {
    if (state.config.navigation !== 'arrows' && state.config.navigation !== 'both') {
      // No arrows used, do nothing
      return;
    }

    const itemsVisible = getItemsVisible(state);
    const maxPage = Math.ceil(state.config.totalSlides / itemsVisible) - 1;

    if (!state.config.looping) {
      if (state.prevButton) {
        state.prevButton.style.visibility = state.pageIndex === 0 ? 'hidden' : 'visible';
      }
      if (state.nextButton) {
        state.nextButton.style.visibility = state.pageIndex >= maxPage ? 'hidden' : 'visible';
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
   * Advances to the next slide/page.
   *
   * @param {Object} state - The carousel state object.
   */
  function nextSlide(state) {
    const itemsVisible = getItemsVisible(state);
    const totalPages = Math.ceil(state.config.totalSlides / itemsVisible);

    state.pageIndex = (state.pageIndex >= totalPages - 1)
      ? (state.config.looping ? 0 : totalPages - 1)
      : state.pageIndex + 1;

    updateLayout(state);
  }

  /**
   * Goes to the previous slide/page.
   *
   * @param {Object} state - The carousel state object.
   */
  function prevSlide(state) {
    const itemsVisible = getItemsVisible(state);
    const maxPage = Math.ceil(state.config.totalSlides / itemsVisible) - 1;

    state.pageIndex = (state.pageIndex > 0)
      ? state.pageIndex - 1
      : (state.config.looping ? maxPage : 0);

    updateLayout(state);
  }

  /**
   * Binds click events to navigation buttons.
   *
   * @param {Object} state - The carousel state object.
   */
  function bindNavigation(state) {
    const shouldAutoplay = state.config.slideTime > 0 &&
      state.config.slideTime >= 1000 &&
      state.config.slideTime <= 15000;

    const resetAutoSlide = () => {
      if (shouldAutoplay && state.autoSlideTimer) {
        clearInterval(state.autoSlideTimer);
        bindAutoSlide(state);
      }
    };

    state.nextButton?.addEventListener('click', () => {
      nextSlide(state);
      resetAutoSlide();
    });

    state.prevButton?.addEventListener('click', () => {
      prevSlide(state);
      resetAutoSlide();
    });
  }

  /**
   * Binds touch/swipe events for mobile navigation.
   *
   * @param {Object} state - The carousel state object.
   */
  function bindTouch(state) {
    let startX = 0;
    let startY = 0;

    state.wrapper.addEventListener('touchstart', (e) => {
      startX = e.touches[0].clientX;
      startY = e.touches[0].clientY;
    }, { passive: true });

    state.wrapper.addEventListener('touchend', (e) => {
      const deltaX = e.changedTouches[0].clientX - startX;
      const deltaY = e.changedTouches[0].clientY - startY;

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
    });
  }

  /**
   * Creates dot navigation elements.
   *
   * @param {Object} state - The carousel state object.
   */
  function createDots(state) {
    const navigationType = state.config.navigation;
    const dotsContainer = state.container.querySelector('.pb__carousel-dots');

    if (!dotsContainer) {
      return;
    }

    if (navigationType !== 'dots' && navigationType !== 'both') {
      // If user chose arrows only or none, do not create dots
      dotsContainer.innerHTML = '';
      return;
    }

    dotsContainer.innerHTML = '';
    const itemsVisible = getItemsVisible(state);
    const totalPages = Math.ceil(state.config.totalSlides / itemsVisible);

    for (let i = 0; i < totalPages; i++) {
      const dot = document.createElement('button');
      dot.className = 'pb__carousel-dot';
      dot.setAttribute('aria-label', `Go to slide group ${i + 1}`);
      dot.setAttribute('type', 'button');
      dot.dataset.slideGroup = i;
      dotsContainer.appendChild(dot);
    }
  }

  /**
   * Binds click events to dot navigation.
   *
   * @param {Object} state - The carousel state object.
   */
  function bindDots(state) {
    if (state.config.navigation !== 'dots' && state.config.navigation !== 'both') {
      return;
    }

    const dotsContainer = state.container.querySelector('.pb__carousel-dots');
    if (!dotsContainer) {
      return;
    }

    dotsContainer.querySelectorAll('.pb__carousel-dot').forEach((dot) => {
      dot.addEventListener('click', () => {
        const groupIndex = parseInt(dot.dataset.slideGroup, 10);
        state.pageIndex = groupIndex;
        updateLayout(state);
      });
    });
  }

  /**
   * Sets up automatic slide advancement.
   *
   * @param {Object} state - The carousel state object.
   */
  function bindAutoSlide(state) {
    let interval = parseInt(state.config.slideTime, 10);

    if (interval === 0) {
      return;
    }

    if (isNaN(interval) || interval < 1000 || interval > 15000) {
      interval = 5000;
    }

    let isVisible = true;

    const start = () => {
      if (state.autoSlideTimer) {
        clearInterval(state.autoSlideTimer);
      }
      state.autoSlideTimer = setInterval(() => nextSlide(state), interval);
    };

    const stop = () => {
      if (state.autoSlideTimer) {
        clearInterval(state.autoSlideTimer);
        state.autoSlideTimer = null;
      }
    };

    start();

    state.wrapper.addEventListener('mouseenter', stop);
    state.wrapper.addEventListener('mouseleave', () => {
      if (isVisible) {
        start();
      }
    });

    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        stop();
      } else if (isVisible) {
        start();
      }
    });

    const observer = new IntersectionObserver((entries) => {
      isVisible = entries[0].isIntersecting;
      if (isVisible) {
        start();
      } else {
        stop();
      }
    }, {
      threshold: 0.3
    });

    observer.observe(state.container);
  }

  /**
   * Binds keyboard navigation events.
   *
   * @param {Object} state - The carousel state object.
   */
  function bindKeyboard(state) {
    state.container.setAttribute('tabindex', '0'); // Make focusable

    state.container.addEventListener('keydown', (e) => {
      const orientation = state.config.orientation;

      if (
        (orientation === 'horizontal' && e.key === 'ArrowRight') ||
        (orientation === 'vertical' && e.key === 'ArrowDown')
      ) {
        e.preventDefault();
        nextSlide(state);
      }

      if (
        (orientation === 'horizontal' && e.key === 'ArrowLeft') ||
        (orientation === 'vertical' && e.key === 'ArrowUp')
      ) {
        e.preventDefault();
        prevSlide(state);
      }
    });
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

})(Drupal, drupalSettings, once);
