/**
 * @file
 * Provides 3D carousel functionality for paragraph bundle.
 *
 * Supports multiple carousels on the page, auto-rotation, keyboard navigation,
 * and both infinite loop and optional rewind behavior.
 * Ping-Pong Carousel: Plays forward to the last slide, then reverses back to the first slide.
 * Filename:     paragraph-bundle-3d-carousel.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, drupalSettings, once) => {
  'use strict';
  const carouselStates = new Map();
  let lastKnownWidth = window.innerWidth;
  let resizeTimeout;
  Drupal.behaviors.paragraphBundleCarousel = {
    attach(context) {
      once('paragraphBundleCarousel', '.pb__3d-carousel', context).forEach(initCarousel);
      if (!document.vvjCarouselVisibilityAttached) {
        document.vvjCarouselVisibilityAttached = true;
        document.addEventListener('visibilitychange', handleVisibilityChange);
      }
    }
  };

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
      rotationDelay: parseInt(carousel.getAttribute('data-rotation-delay'), 10),
      slideNumberElement: document.getElementById(`index-${uniqueId}`),
      prevButton: document.getElementById(`prev-${uniqueId}`),
      nextButton: document.getElementById(`next-${uniqueId}`),
      playPauseButton: document.getElementById(`btn-${uniqueId}`)
    };
    carouselStates.set(carousel.id, state);
    if (state.rotationDelay <= 0) {
      state.isPaused = true;
      updatePlayPauseButton(state, true);
    }
    buildCarousel(state);
    setupControls(state);
    setupTouchEvents(state);
    updateSlide(state);
    startAutoPlay(state);
    Drupal.debug && Drupal.debug(`3D Carousel initialized: ${carouselId}`);
  }

  function buildCarousel(state) {
    const {
      cells,
      container
    } = state;
    const theta = 360 / cells.length;
    const radius = Math.round(container.offsetWidth / 2 / Math.tan(Math.PI / cells.length));
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
    state.container.addEventListener('keydown', (event) => {
      if (event.key === 'ArrowRight') {
        manualAdvance(state, 1);
      } else if (event.key === 'ArrowLeft') {
        manualAdvance(state, -1);
      }
    });
    state.container.addEventListener('mouseenter', () => pauseAutoPlay(state));
    state.container.addEventListener('mouseleave', () => resumeAutoPlay(state));
  }

  function manualAdvance(state, direction) {
    state.direction = direction;
    advanceSlide(state);
    restartAutoPlay(state);
  }

  function advanceSlide(state) {
    const {
      cells
    } = state;
    const cellCount = cells.length;
    state.currentIndex += state.direction;
    if (state.currentIndex >= cellCount) {
      // Bounce immediately to previous slide.
      state.currentIndex = cellCount - 2;
      state.direction = -1;
    } else if (state.currentIndex < 0) {
      // Bounce immediately to next slide.
      state.currentIndex = 1;
      state.direction = 1;
    }
    updateSlide(state);
  }

  function updateSlide(state) {
    const {
      cells,
      currentIndex,
      slideNumberElement,
      container
    } = state;
    const theta = 360 / cells.length;
    const radius = Math.round(container.offsetWidth / 2 / Math.tan(Math.PI / cells.length));
    const angle = theta * currentIndex * -1;
    container.style.transition = 'transform 0.8s ease-in-out';
    container.style.transform = `translateZ(${-radius}px) rotateY(${angle}deg)`;
    cells.forEach((cell, index) => {
      const isActive = index === currentIndex;
      cell.setAttribute('aria-hidden', !isActive);
      updateFocusableElements(cell, isActive);
      if (isActive) {
        cell.classList.add('pb__active');
      } else {
        cell.classList.remove('pb__active');
      }
    });
    if (slideNumberElement) {
      slideNumberElement.setAttribute('aria-label', `Slide ${currentIndex + 1} of ${cells.length}`);
      slideNumberElement.textContent = currentIndex + 1;
    }
    updateNavVisibility(state);
  }

  function updateFocusableElements(cell, isActive) {
    cell.querySelectorAll('a, button').forEach(el => {
      el.setAttribute('tabindex', isActive ? '0' : '-1');
    });
  }

  function updateNavVisibility(state) {
    const {
      currentIndex,
      prevButton,
      nextButton,
      cells
    } = state;
    if (prevButton) {
      if (currentIndex === 0) {
        prevButton.classList.add('hidden');
        prevButton.setAttribute('tabindex', '-1');
      } else {
        prevButton.classList.remove('hidden');
        prevButton.setAttribute('tabindex', '0');
      }
    }
    if (nextButton) {
      if (currentIndex === cells.length - 1) {
        nextButton.classList.add('hidden');
        nextButton.setAttribute('tabindex', '-1');
      } else {
        nextButton.classList.remove('hidden');
        nextButton.setAttribute('tabindex', '0');
      }
    }
  }

  function togglePlayPause(state) {
    state.isPaused = !state.isPaused;
    updatePlayPauseButton(state, state.isPaused);
    if (state.isPaused) {
      pauseAutoPlay(state);
    } else {
      restartAutoPlay(state);
    }
  }

  function updatePlayPauseButton(state, isPaused) {
    if (!state.playPauseButton) {
      return;
    }
    if (isPaused) {
      state.playPauseButton.classList.remove('pb__pause');
      state.playPauseButton.classList.add('pb__play');
      state.playPauseButton.innerHTML = '<span>&#9654;</span>';
      state.playPauseButton.setAttribute('aria-label', 'Play slideshow');
      state.playPauseButton.setAttribute('aria-pressed', 'false');
    } else {
      state.playPauseButton.classList.remove('pb__play');
      state.playPauseButton.classList.add('pb__pause');
      state.playPauseButton.innerHTML = '<span>&#10073;&#10073;</span>';
      state.playPauseButton.setAttribute('aria-label', 'Pause slideshow');
      state.playPauseButton.setAttribute('aria-pressed', 'true');
    }
  }

  function setupTouchEvents(state) {
    let touchStartX = 0;
    let touchEndX = 0;
    state.container.addEventListener('touchstart', (e) => {
      touchStartX = e.touches[0].clientX;
    });
    state.container.addEventListener('touchmove', (e) => {
      touchEndX = e.touches[0].clientX;
    });
    state.container.addEventListener('touchend', () => {
      handleSwipe(state, touchStartX, touchEndX);
    });
  }

  function handleSwipe(state, startX, endX) {
    const swipeThreshold = 50; // Minimum distance to count as a swipe.
    if (startX - endX > swipeThreshold) {
      // Swiped left = next slide.
      manualAdvance(state, 1);
    } else if (endX - startX > swipeThreshold) {
      // Swiped right = previous slide.
      manualAdvance(state, -1);
    }
  }

  function startAutoPlay(state) {
    clearInterval(state.intervalId);
    if (state.rotationDelay > 0 && !state.isPaused) {
      state.intervalId = setInterval(() => advanceSlide(state), state.rotationDelay);
    }
  }

  function pauseAutoPlay(state) {
    clearInterval(state.intervalId);
  }

  function resumeAutoPlay(state) {
    if (!state.isPaused) {
      startAutoPlay(state);
    }
  }

  function handleVisibilityChange() {
    carouselStates.forEach((state) => {
      if (document.hidden) {
        pauseAutoPlay(state);
      } else {
        updateSlide(state);
        startAutoPlay(state);
      }
    });
  }

  function restartAutoPlay(state) {
    pauseAutoPlay(state);
    startAutoPlay(state);
  }

  const resetCarousel = () => {
    carouselStates.forEach((state) => {
      state.currentIndex = 0;
      state.direction = 1;
      buildCarousel(state);
      updateSlide(state);
      restartAutoPlay(state);
    });
  };

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

  window.addEventListener('resize', handleResizeOrRotate);
  window.addEventListener('orientationchange', handleResizeOrRotate);


})(Drupal, drupalSettings, once);
