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
  Drupal.behaviors.paragraphBundleCarousel = {
    attach(context) {
      once('paragraphBundleCarousel', '.pb__3d-carousel', context).forEach(initCarousel);
    }
  };

  function initCarousel(carouselC) {
    const carouselId = carouselC.getAttribute('id');
    const state = {
      container: carouselC
      , cells: carouselC.querySelectorAll('.pb__caro-item')
      , currentIndex: 0
      , isPaused: false
      , intervalId: null
      , direction: 1
      , rotationDelay: parseInt(carouselC.getAttribute('data-rotation-delay'), 10) || 5000
      , slideNumberElement: document.getElementById(`index-${carouselId.split('-')[2]}`)
      , prevButton: document.getElementById(`prev-${carouselId.split('-')[2]}`)
      , nextButton: document.getElementById(`next-${carouselId.split('-')[2]}`)
      , playPauseButton: document.getElementById(`btn-${carouselId.split('-')[2]}`)
    , };
    if (state.rotationDelay <= 0) {
      state.isPaused = true;
      state.playPauseButton.classList.remove('pb__pause');
      state.playPauseButton.classList.add('pb__play');
      state.playPauseButton.innerHTML = '<span>&#9654;</span>';
      state.playPauseButton.setAttribute('aria-label', 'Play slideshow');
      state.playPauseButton.setAttribute('aria-pressed', 'false');
    }
    buildCarousel(state);
    setupControls(state);
    updateSlide(state);
    startAutoPlay(state);
    Drupal.debug && Drupal.debug(`3D Carousel initialized: ${carouselId}`);
  }

  function buildCarousel(state) {
    const {
      cells
    } = state;
    const cellCount = cells.length;
    const theta = 360 / cellCount;
    const radius = Math.round(state.container.offsetWidth / 2 / Math.tan(Math.PI / cellCount));
    cells.forEach((cell, i) => {
      cell.style.opacity = '1';
      cell.style.transform = `rotateY(${theta * i}deg) translateZ(${radius}px)`;
    });
    state.cells[0].classList.add('pb__active');
    state.prevButton.classList.add('hidden');
    state.prevButton.setAttribute('tabindex', '-1');
  }

  function setupControls(state) {
    if (state.nextButton) {
      state.nextButton.addEventListener('click', () => manualAdvance(state, 1));
    }
    if (state.prevButton) {
      state.prevButton.addEventListener('click', () => manualAdvance(state, -1));
    }
    if (state.playPauseButton) {
      state.playPauseButton.style.display = 'inline';
      state.playPauseButton.addEventListener('click', () => {
        if (state.playPauseButton.classList.contains('pb__play')) {
          state.playPauseButton.classList.remove('pb__play');
          state.playPauseButton.classList.add('pb__pause');
          state.playPauseButton.innerHTML = '<span>&#10073;&nbsp;&#10073;</span>';
          state.playPauseButton.setAttribute('aria-label', 'Pause slideshow');
          state.playPauseButton.setAttribute('aria-pressed', 'true');
          state.isPaused = false;
          restartAutoPlay(state);
        } else {
          state.playPauseButton.classList.remove('pb__pause');
          state.playPauseButton.classList.add('pb__play');
          state.playPauseButton.innerHTML = '<span>&#9654;</span>';
          state.playPauseButton.setAttribute('aria-label', 'Play slideshow');
          state.playPauseButton.setAttribute('aria-pressed', 'false');
          state.isPaused = true;
          clearInterval(state.intervalId);
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
    state.direction = direction; // Respect manual navigation.
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
      state.currentIndex = cellCount - 2; // Jump directly to second-to-last.
      state.direction = -1;
    } else if (state.currentIndex < 0) {
      state.currentIndex = 1; // Jump directly to second slide.
      state.direction = 1;
    }
    updateSlide(state);
  }

  function updateSlide(state) {
    const {
      cells
      , currentIndex
      , slideNumberElement
      , prevButton
      , nextButton
    } = state;
    const cellCount = cells.length;
    const theta = 360 / cellCount;
    const radius = Math.round(state.container.offsetWidth / 2 / Math.tan(Math.PI / cellCount));
    const angle = theta * currentIndex * -1;
    state.container.style.transition = 'transform 0.8s ease-in-out';
    state.container.style.transform = `translateZ(${-radius}px) rotateY(${angle}deg)`;
    cells.forEach((cell, index) => {
      const isActive = index === currentIndex;
      cell.setAttribute('aria-hidden', !isActive);
      cell.setAttribute('tabindex', isActive ? '0' : '-1');
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
    if (currentIndex === 0 && state.direction === -1) {
      prevButton.classList.add('hidden');
      prevButton.setAttribute('tabindex', '-1');
    } else {
      prevButton.classList.remove('hidden');
      prevButton.setAttribute('tabindex', '0');
    }
    if (currentIndex === cellCount - 1 && state.direction === 1) {
      nextButton.classList.add('hidden');
      nextButton.setAttribute('tabindex', '-1');
    } else {
      nextButton.classList.remove('hidden');
      nextButton.setAttribute('tabindex', '0');
    }
    prevButton.setAttribute('tabindex', prevButton.classList.contains('hidden') ? '-1' : '0');
    nextButton.setAttribute('tabindex', nextButton.classList.contains('hidden') ? '-1' : '0');
  }

  function updateFocusableElements(parentElement, isActive) {
    parentElement.querySelectorAll('a, button').forEach((el) => {
      el.setAttribute('tabindex', isActive ? '0' : '-1');
    });
  }

  function startAutoPlay(state) {
    clearInterval(state.intervalId);
    if (state.rotationDelay <= 0) {
      return;
    }
    state.intervalId = setInterval(() => advanceSlide(state), state.rotationDelay);
  }

  function pauseAutoPlay(state) {
    clearInterval(state.intervalId);
    state.isPaused = true;
  }

  function resumeAutoPlay(state) {
    if (state.rotationDelay > 0 && state.isPaused) {
      state.intervalId = setInterval(() => advanceSlide(state), state.rotationDelay);
      state.isPaused = false;
    }
  }

  function restartAutoPlay(state) {
    clearInterval(state.intervalId);
    if (!state.isPaused) {
      startAutoPlay(state);
    }
  }
})(Drupal, drupalSettings, once);
