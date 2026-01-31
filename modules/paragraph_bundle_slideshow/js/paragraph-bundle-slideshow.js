/**
 * @file
 * Paragraph Bundle Slideshow.
 *
 * Filename:     paragraph-bundle-slideshow.js
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

  Drupal.behaviors.paragraphSlideshowBundle = {
    attach(context) {
      const slideshows = once('paragraphSlideshowBundle', '.pb__slideshow-inner', context);
      if (!slideshows.length) {
        return;
      }

      slideshows.forEach((slideshowInner) => {
        // Cache DOM queries for performance
        const slideshow = slideshowInner.querySelector('.pb__slides');
        const slides = slideshow.querySelectorAll('.pb__slide-item');
        const announcer = slideshowInner.querySelector('.pb__announcer');
        const playPauseButton = slideshowInner.querySelector(`#btn-${slideshowInner.id.split('-').pop()}`);
        const nextButton = slideshowInner.querySelector('.pb__next');
        const prevButton = slideshowInner.querySelector('.pb__prev');
        const dotsContainer = slideshowInner.querySelector('.pb__slide-nav');
        const dots = slideshowInner.querySelectorAll('.pb__slide-bottom-btn');
        const slideTime = parseInt(slideshowInner.dataset.slideTime, 10) || 0;
        const totalSlides = slides.length;

        // Early exit if no slides
        if (totalSlides === 0) {
          return;
        }

        // State variables
        let slideIndex = 1;
        let isPaused = slideTime === 0;
        let autoSlideIntervalId = null;
        let previousVisibility = false;
        let isVisible = true;

        /**
         * Updates the visibility and accessibility attributes for all slides.
         */
        const updateSlideVisibility = () => {
          slides.forEach((slide, index) => {
            const isActive = (index + 1 === slideIndex);
            slide.style.display = isActive ? 'block' : 'none';
            slide.setAttribute('aria-hidden', String(!isActive));
            slide.toggleAttribute('inert', !isActive);
            slide.classList.toggle('active', isActive);
            slide.querySelectorAll('a, button, input, textarea, select, [tabindex]').forEach(el => {
              el.setAttribute('tabindex', isActive ? '0' : '-1');
            });
          });
          updateDots();
          announceSlide();
          adjustHeight();
        };

        /**
         * Adjusts the slideshow container height to match the current slide.
         */
        const adjustHeight = () => {
          const currentSlide = slides[slideIndex - 1];
          if (!currentSlide) {
            return;
          }
          const computedStyle = window.getComputedStyle(slideshow);
          const contentHeight = currentSlide.offsetHeight;
          const paddingTop = parseFloat(computedStyle.paddingTop) || 0;
          const paddingBottom = parseFloat(computedStyle.paddingBottom) || 0;
          const borderTop = parseFloat(computedStyle.borderTopWidth) || 0;
          const borderBottom = parseFloat(computedStyle.borderBottomWidth) || 0;
          const totalHeight = contentHeight + paddingTop + paddingBottom + borderTop + borderBottom;
          slideshow.style.height = `${totalHeight}px`;
        };

        /**
         * Announces the current slide to screen readers via live region.
         */
        const announceSlide = () => {
          if (announcer) {
            announcer.textContent = `Slide ${slideIndex} of ${totalSlides}`;
          }
        };

        /**
         * Updates the active state for navigation dots and scrolls active into view.
         */
        const updateDots = () => {
          dots.forEach((dot, index) => {
            const isActive = index + 1 === slideIndex;
            dot.classList.toggle('pb__active', isActive);
            dot.setAttribute('aria-selected', String(isActive));

            // Scroll active dot into view
            if (isActive && dotsContainer) {
              scrollDotIntoView(dotsContainer, dot);
            }
          });
        };

        /**
         * Scrolls the active dot into view within the dots container.
         *
         * @param {HTMLElement} container - The dots container element.
         * @param {HTMLElement} dot - The active dot element.
         */
        const scrollDotIntoView = (container, dot) => {
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
        };

        /**
         * Starts the automatic slideshow interval.
         */
        const startAutoSlide = () => {
          stopAutoSlide();
          if (slideTime > 0 && !isPaused && isVisible) {
            autoSlideIntervalId = setInterval(nextSlide, slideTime);
          }
        };

        /**
         * Stops the automatic slideshow interval.
         */
        const stopAutoSlide = () => {
          if (autoSlideIntervalId) {
            clearInterval(autoSlideIntervalId);
            autoSlideIntervalId = null;
          }
        };

        /**
         * Advances to the next slide with wrap-around.
         */
        const nextSlide = () => {
          slideIndex = (slideIndex % totalSlides) + 1;
          updateSlideVisibility();
        };

        /**
         * Goes to the previous slide with wrap-around.
         */
        const prevSlide = () => {
          slideIndex = (slideIndex === 1) ? totalSlides : slideIndex - 1;
          updateSlideVisibility();
        };

        /**
         * Toggles between play and pause states.
         */
        const togglePlayPause = () => {
          if (!playPauseButton) {
            return;
          }
          isPaused = !isPaused;
          playPauseButton.innerHTML = isPaused ? playIcon : pauseIcon;
          playPauseButton.setAttribute('aria-label', isPaused ? 'Play slideshow' : 'Pause slideshow');
          playPauseButton.classList.toggle('is-paused', isPaused);
          playPauseButton.classList.toggle('is-playing', !isPaused);
          if (slideTime > 0) {
            isPaused ? stopAutoSlide() : startAutoSlide();
          }
        };

        /**
         * Handles click on navigation dots.
         *
         * @param {number} index - The zero-based index of the clicked dot.
         */
        const handleDotClick = (index) => {
          slideIndex = index + 1;
          updateSlideVisibility();
          startAutoSlide();
        };

        /**
         * Applies reduced motion preferences from user settings.
         */
        const applyReducedMotion = () => {
          if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            isPaused = true;
            stopAutoSlide();
            slideshow.classList.add('reduced-motion');
            if (playPauseButton) {
              playPauseButton.innerHTML = playIcon;
              playPauseButton.setAttribute('aria-label', 'Play slideshow');
              playPauseButton.classList.add('is-paused');
              playPauseButton.classList.remove('is-playing');
            }
          }
        };

        /**
         * Checks if the element is mostly visible in the viewport.
         *
         * @param {HTMLElement} element - The element to check.
         * @returns {boolean} True if more than 20% visible.
         */
        const isMostlyVisible = (element) => {
          const rect = element.getBoundingClientRect();
          const visibleHeight = Math.min(rect.bottom, window.innerHeight) - Math.max(rect.top, 0);
          return visibleHeight / rect.height > 0.2;
        };

        /**
         * Handles visibility changes when scrolling.
         */
        const handleVisibility = () => {
          const currentlyVisible = isMostlyVisible(slideshowInner);
          if (currentlyVisible !== previousVisibility) {
            previousVisibility = currentlyVisible;
            if (currentlyVisible && !isPaused) {
              startAutoSlide();
            } else {
              stopAutoSlide();
            }
          }
        };

        /**
         * Handles document visibility changes (tab switching).
         */
        const handleSlideshowVisibilityChange = () => {
          if (document.hidden) {
            stopAutoSlide();
          } else {
            isVisible = isMostlyVisible(slideshowInner);
            if (isVisible && !isPaused) {
              startAutoSlide();
            }
          }
        };

        /**
         * Handles keyboard navigation for the slideshow.
         *
         * @param {KeyboardEvent} e - The keyboard event.
         */
        const handleKeydown = (e) => {
          // Only handle if slideshow or its children have focus
          if (!slideshowInner.contains(document.activeElement)) {
            return;
          }

          if (e.key === 'ArrowRight') {
            nextSlide();
          } else if (e.key === 'ArrowLeft') {
            prevSlide();
          } else if (e.key === ' ') {
            e.preventDefault();
            togglePlayPause();
          }
        };

        /**
         * Handles touch start for swipe detection.
         *
         * @param {TouchEvent} e - The touch event.
         */
        const handleTouchStart = (e) => {
          slideshow._touchStartX = e.touches[0].clientX;
        };

        /**
         * Handles touch end for swipe detection.
         *
         * @param {TouchEvent} e - The touch event.
         */
        const handleTouchEnd = (e) => {
          const delta = e.changedTouches[0].clientX - slideshow._touchStartX;
          if (delta > 50) {
            prevSlide();
          } else if (delta < -50) {
            nextSlide();
          }
          startAutoSlide();
        };

        // Intersection Observer for viewport visibility
        const observer = new IntersectionObserver((entries) => {
          const entry = entries[0];
          isVisible = entry.isIntersecting;
          if (isVisible && !isPaused) {
            startAutoSlide();
          } else {
            stopAutoSlide();
          }
        }, {
          threshold: 0.5
        });
        observer.observe(slideshowInner);

        // Debounced event handlers
        const debouncedHandleVisibility = debounce(handleVisibility, 200);
        const debouncedResize = debounce(() => {
          adjustHeight();
          handleVisibility();
        }, 200);

        /**
         * Sets up all event listeners for the slideshow.
         */
        const setupListeners = () => {
          // Button controls
          playPauseButton?.addEventListener('click', togglePlayPause);
          nextButton?.addEventListener('click', nextSlide);
          prevButton?.addEventListener('click', prevSlide);

          // Dot navigation
          dots.forEach((dot, index) => {
            dot.addEventListener('click', () => handleDotClick(index));
          });

          // Mouse interaction pauses autoplay
          slideshow.addEventListener('mouseenter', stopAutoSlide);
          slideshow.addEventListener('mouseleave', () => {
            if (!isPaused) {
              startAutoSlide();
            }
          });

          // Touch/swipe support
          slideshow.addEventListener('touchstart', handleTouchStart, { passive: true });
          slideshow.addEventListener('touchend', handleTouchEnd);

          // Keyboard navigation
          slideshowInner.addEventListener('keydown', handleKeydown);

          // Global listeners
          document.addEventListener('scroll', debouncedHandleVisibility, { passive: true });
          window.addEventListener('resize', debouncedResize);
          document.addEventListener('visibilitychange', handleSlideshowVisibilityChange);
        };

        // Initialize slideshow
        applyReducedMotion();
        updateSlideVisibility();
        startAutoSlide();
        setupListeners();
      });
    }
  };
})(Drupal, drupalSettings, once);
