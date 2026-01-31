/**
 * @file
 * Provides 3D carousel functionality for paragraph bundle.
 *
 * Supports multiple carousels on the page, auto-rotation, keyboard navigation,
 * and both infinite loop and optional rewind behavior.
 * Ping-Pong Carousel: Plays forward to the last slide, then reverses back to the first slide.
 *
 * Filename:     paragraph-bundle-3d-carousel.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, drupalSettings, once) => {
  'use strict';

  /**
   * Map to store carousel state objects keyed by carousel ID.
   *
   * @type {Map<string, Object>}
   */
  const carouselStates = new Map();

  /**
   * Map to track previous visibility state for each carousel.
   *
   * @type {Map<string, boolean>}
   */
  const previousVisibility = new Map();

  /**
   * Last known window width for resize detection.
   *
   * @type {number}
   */
  let lastKnownWidth = window.innerWidth;

  /**
   * Timeout ID for resize debouncing.
   *
   * @type {number|undefined}
   */
  let resizeTimeout;

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
   * SVG icon for play button state.
   *
   * @type {string}
   */
  const playIcon = `
    <span class="visually-hidden">Play and Stop Slideshow</span>
    <span><svg class="svg-play" xmlns="http://www.w3.org/2000/svg" viewBox="80 -880 800 800" fill="currentColor" aria-hidden="true">
      <path d="m380-300 280-180-280-180v360ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"></path>
    </svg></span>`;

  /**
   * SVG icon for pause button state.
   *
   * @type {string}
   */
  const pauseIcon = `
    <span class="visually-hidden">Play and Stop Slideshow</span>
    <span><svg class="svg-pause" xmlns="http://www.w3.org/2000/svg" viewBox="80 -880 800 800" fill="currentColor" aria-hidden="true"><path d="M360-320h80v-320h-80v320Zm160 0h80v-320h-80v320ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"></path></svg></span>`;

  /**
   * Drupal behavior for 3D Carousel initialization.
   */
  Drupal.behaviors.paragraphBundleCarousel = {
    attach(context) {
      once('paragraphBundleCarousel', '.pb__3d-carousel', context).forEach(initCarousel);

      // Attach visibility change listener only once globally
      if (!document.vvjCarouselVisibilityAttached) {
        document.vvjCarouselVisibilityAttached = true;
        document.addEventListener('visibilitychange', handleVisibilityChange);
      }
    }
  };

  /**
   * Initializes a single carousel instance.
   *
   * @param {HTMLElement} carousel - The carousel container element.
   */
  function initCarousel(carousel) {
    const carouselId = carousel.id;
    const uniqueId = carouselId.split('-')[2];

    const state = {
      container: carousel,
      cells: carousel.querySelectorAll('.pb__caro-item'),
      currentIndex: 0,
      isPaused: false,
      intervalId: null,
      direction: 1,
      rotationDelay: parseInt(carousel.getAttribute('data-rotation-delay'), 10) || 0,
      slideNumberElement: document.getElementById(`index-${uniqueId}`),
      prevButton: document.getElementById(`prev-${uniqueId}`),
      nextButton: document.getElementById(`next-${uniqueId}`),
      playPauseButton: document.getElementById(`btn-${uniqueId}`)
    };

    // Early exit if no cells
    if (state.cells.length === 0) {
      return;
    }

    const initiallyVisible = isMostlyVisible(state.container);
    previousVisibility.set(carousel.id, initiallyVisible);
    carouselStates.set(carousel.id, state);

    if (state.rotationDelay <= 0) {
      state.isPaused = true;
      updatePlayPauseButton(state, true);
    }

    buildCarousel(state);
    setupControls(state);
    setupTouchEvents(state);
    updateSlide(state);

    // Only start autoPlay if it's visible and not paused
    if (initiallyVisible && !state.isPaused) {
      startAutoPlay(state);
    }

    Drupal.debug && Drupal.debug(`3D Carousel initialized: ${carouselId}`);
  }

  /**
   * Builds the 3D carousel layout by positioning cells in a circle.
   *
   * @param {Object} state - The carousel state object.
   */
  function buildCarousel(state) {
    const { cells, container } = state;
    const cellCount = cells.length;

    if (cellCount === 0) {
      return;
    }

    const theta = 360 / cellCount;
    const radius = Math.round(container.offsetWidth / 2 / Math.tan(Math.PI / cellCount));
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    cells.forEach((cell, i) => {
      cell.style.opacity = '1';
      cell.style.transform = `rotateY(${theta * i}deg) translateZ(${radius}px)`;
      if (reducedMotion) {
        cell.style.transition = 'none';
      }
    });

    if (reducedMotion) {
      container.style.transition = 'none';
    }

    cells[0].classList.add('active');
    updateNavVisibility(state);
  }

  /**
   * Sets up control button event listeners.
   *
   * @param {Object} state - The carousel state object.
   */
  function setupControls(state) {
    state.nextButton?.addEventListener('click', () => manualAdvance(state, 1));
    state.prevButton?.addEventListener('click', () => manualAdvance(state, -1));

    if (state.playPauseButton) {
      state.playPauseButton.style.display = 'flex';
      state.playPauseButton.addEventListener('click', () => togglePlayPause(state));
      state.playPauseButton.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          togglePlayPause(state);
        }
      });
    }

    // Scoped keyboard navigation
    state.container.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowRight') {
        manualAdvance(state, 1);
      } else if (event.key === 'ArrowLeft') {
        manualAdvance(state, -1);
      }
    });

    // Pause on hover
    state.container.addEventListener('mouseenter', () => pauseAutoPlay(state));
    state.container.addEventListener('mouseleave', () => resumeAutoPlay(state));
  }

  /**
   * Handles manual slide advancement via controls.
   *
   * @param {Object} state - The carousel state object.
   * @param {number} direction - Direction to advance (1 for next, -1 for previous).
   */
  function manualAdvance(state, direction) {
    state.direction = direction;
    advanceSlide(state);
    restartAutoPlay(state);
  }

  /**
   * Advances the carousel to the next/previous slide with ping-pong behavior.
   *
   * @param {Object} state - The carousel state object.
   */
  function advanceSlide(state) {
    const { cells } = state;
    const cellCount = cells.length;

    state.currentIndex += state.direction;

    if (state.currentIndex >= cellCount) {
      // Bounce immediately to previous slide
      state.currentIndex = cellCount - 2;
      state.direction = -1;
    } else if (state.currentIndex < 0) {
      // Bounce immediately to next slide
      state.currentIndex = 1;
      state.direction = 1;
    }

    updateSlide(state);
  }

  /**
   * Updates the visual state of the carousel to reflect the current slide.
   *
   * @param {Object} state - The carousel state object.
   */
  function updateSlide(state) {
    const { cells, currentIndex, slideNumberElement, container } = state;
    const cellCount = cells.length;

    if (cellCount === 0) {
      return;
    }

    const theta = 360 / cellCount;
    const radius = Math.round(container.offsetWidth / 2 / Math.tan(Math.PI / cellCount));
    const angle = theta * currentIndex * -1;

    container.style.transition = 'transform 0.8s ease-in-out';
    container.style.transform = `translateZ(${-radius}px) rotateY(${angle}deg)`;

    cells.forEach((cell, index) => {
      const isActive = index === currentIndex;
      cell.setAttribute('aria-hidden', String(!isActive));
      updateFocusableElements(cell, isActive);
      if (isActive) {
        cell.classList.add('pb__active');
      } else {
        cell.classList.remove('pb__active');
      }
    });

    if (slideNumberElement) {
      slideNumberElement.setAttribute('aria-label', `Slide ${currentIndex + 1} of ${cellCount}`);
      slideNumberElement.textContent = currentIndex + 1;
    }

    updateNavVisibility(state);
  }

  /**
   * Updates tabindex for focusable elements within a cell.
   *
   * @param {HTMLElement} cell - The carousel cell element.
   * @param {boolean} isActive - Whether the cell is currently active.
   */
  function updateFocusableElements(cell, isActive) {
    cell.querySelectorAll('a, button, input, textarea, select, [tabindex]').forEach(el => {
      el.setAttribute('tabindex', isActive ? '0' : '-1');
    });
  }

  /**
   * Updates the visibility of prev/next navigation buttons.
   *
   * @param {Object} state - The carousel state object.
   */
  function updateNavVisibility(state) {
    const { currentIndex, prevButton, nextButton, cells } = state;

    if (prevButton) {
      if (currentIndex === 0) {
        prevButton.classList.add('btn-hidden');
        prevButton.setAttribute('tabindex', '-1');
      } else {
        prevButton.classList.remove('btn-hidden');
        prevButton.setAttribute('tabindex', '0');
      }
    }

    if (nextButton) {
      if (currentIndex === cells.length - 1) {
        nextButton.classList.add('btn-hidden');
        nextButton.setAttribute('tabindex', '-1');
      } else {
        nextButton.classList.remove('btn-hidden');
        nextButton.setAttribute('tabindex', '0');
      }
    }
  }

  /**
   * Toggles the play/pause state of the carousel.
   *
   * @param {Object} state - The carousel state object.
   */
  function togglePlayPause(state) {
    state.isPaused = !state.isPaused;
    updatePlayPauseButton(state, state.isPaused);

    if (state.isPaused) {
      pauseAutoPlay(state);
    } else {
      restartAutoPlay(state);
    }
  }

  /**
   * Updates the play/pause button visual state.
   *
   * @param {Object} state - The carousel state object.
   * @param {boolean} isPaused - Whether the carousel is paused.
   */
  function updatePlayPauseButton(state, isPaused) {
    if (!state.playPauseButton) {
      return;
    }

    if (isPaused) {
      state.playPauseButton.classList.remove('pb__pause');
      state.playPauseButton.classList.add('pb__play');
      state.playPauseButton.innerHTML = playIcon;
      state.playPauseButton.setAttribute('aria-label', 'Play slideshow');
      state.playPauseButton.setAttribute('aria-pressed', 'false');
    } else {
      state.playPauseButton.classList.remove('pb__play');
      state.playPauseButton.classList.add('pb__pause');
      state.playPauseButton.innerHTML = pauseIcon;
      state.playPauseButton.setAttribute('aria-label', 'Pause slideshow');
      state.playPauseButton.setAttribute('aria-pressed', 'true');
    }
  }

  /**
   * Sets up touch event listeners for swipe navigation.
   *
   * @param {Object} state - The carousel state object.
   */
  function setupTouchEvents(state) {
    let touchStartX = 0;
    let touchEndX = 0;

    state.container.addEventListener('touchstart', (e) => {
      touchStartX = e.touches[0].clientX;
    }, { passive: true });

    state.container.addEventListener('touchmove', (e) => {
      touchEndX = e.touches[0].clientX;
    }, { passive: true });

    state.container.addEventListener('touchend', () => {
      handleSwipe(state, touchStartX, touchEndX);
    });
  }

  /**
   * Handles swipe gestures for navigation.
   *
   * @param {Object} state - The carousel state object.
   * @param {number} startX - Starting X coordinate of the touch.
   * @param {number} endX - Ending X coordinate of the touch.
   */
  function handleSwipe(state, startX, endX) {
    const swipeThreshold = 50; // Minimum distance to count as a swipe

    if (startX - endX > swipeThreshold) {
      // Swiped left = next slide
      manualAdvance(state, 1);
    } else if (endX - startX > swipeThreshold) {
      // Swiped right = previous slide
      manualAdvance(state, -1);
    }
  }

  /**
   * Starts the automatic slide rotation.
   *
   * @param {Object} state - The carousel state object.
   */
  function startAutoPlay(state) {
    if (state.intervalId) {
      clearInterval(state.intervalId);
      state.intervalId = null;
    }

    if (state.rotationDelay > 0 && !state.isPaused) {
      state.intervalId = setInterval(() => advanceSlide(state), state.rotationDelay);
    }
  }

  /**
   * Pauses the automatic slide rotation.
   *
   * @param {Object} state - The carousel state object.
   */
  function pauseAutoPlay(state) {
    if (state.intervalId) {
      clearInterval(state.intervalId);
      state.intervalId = null;
    }
  }

  /**
   * Resumes automatic rotation if not manually paused.
   *
   * @param {Object} state - The carousel state object.
   */
  function resumeAutoPlay(state) {
    if (!state.isPaused) {
      startAutoPlay(state);
    }
  }

  /**
   * Handles document visibility changes (tab switching).
   */
  function handleVisibilityChange() {
    carouselStates.forEach((state) => {
      if (document.hidden) {
        pauseAutoPlay(state);
      } else if (isMostlyVisible(state.container) && !state.isPaused) {
        updateSlide(state);
        startAutoPlay(state);
      }
    });
  }

  /**
   * Restarts autoplay by stopping and starting again.
   *
   * @param {Object} state - The carousel state object.
   */
  function restartAutoPlay(state) {
    pauseAutoPlay(state);
    startAutoPlay(state);
  }

  /**
   * Resets all carousels to their initial state.
   */
  const resetCarousel = () => {
    carouselStates.forEach((state) => {
      state.currentIndex = 0;
      state.direction = 1;
      buildCarousel(state);
      updateSlide(state);
      restartAutoPlay(state);
    });
  };

  /**
   * Checks if an element is mostly visible in the viewport.
   *
   * @param {HTMLElement} element - The element to check.
   * @returns {boolean} True if more than 20% of the element is visible.
   */
  const isMostlyVisible = (element) => {
    const rect = element.getBoundingClientRect();
    const visibleHeight = Math.min(rect.bottom, window.innerHeight) - Math.max(rect.top, 0);
    return visibleHeight / rect.height > 0.2;
  };

  /**
   * Handles carousel visibility changes during scrolling.
   */
  function handleCarouselVisibility() {
    carouselStates.forEach((state, carouselId) => {
      const currentlyVisible = isMostlyVisible(state.container);
      const wasVisible = previousVisibility.get(carouselId) ?? false;

      if (currentlyVisible !== wasVisible) {
        previousVisibility.set(carouselId, currentlyVisible);
        if (currentlyVisible && !state.isPaused) {
          startAutoPlay(state);
        } else {
          pauseAutoPlay(state);
        }
      }
    });
  }

  /**
   * Handles window resize and orientation changes.
   */
  const handleResizeOrRotate = () => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
      const newWidth = window.innerWidth;
      if (Math.abs(newWidth - lastKnownWidth) >= 50) {
        resetCarousel();
        lastKnownWidth = newWidth;
      }
    }, 200);
  };

  // Global event listeners
  document.addEventListener('scroll', debounce(handleCarouselVisibility, 200), { passive: true });
  window.addEventListener('orientationchange', debounce(handleResizeOrRotate, 200));
  window.addEventListener('resize', debounce(() => {
    handleCarouselVisibility();
    handleResizeOrRotate();
  }, 200));

})(Drupal, drupalSettings, once);
