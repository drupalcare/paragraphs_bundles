/**
 * @file
 * Paragraph Bundle Slideshow.
 *
 * Slideshow with autoplay, navigation dots, arrows, keyboard, and touch support.
 * Follows Drupal 11 contrib best practices.
 *
 * Filename:     paragraph-bundle-slideshow.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing

((Drupal, drupalSettings, once) => {
  'use strict';

  const PARAGRAPHS_BUNDLES = 'paragraphs-bundles';
  const PB_SLIDESHOW = 'pb-slideshow';
  const PB_SLIDESHOW__STAGE = 'pb-slideshow__stage';
  const PB_SLIDESHOW__SLIDES = 'pb-slideshow__slides';
  const PB_SLIDESHOW__SLIDE = 'pb-slideshow__slide';
  const PB_SLIDESHOW__NAV = 'pb-slideshow__nav';
  const PB_SLIDESHOW__NAV_BTN = 'pb-slideshow__nav-btn';
  const PB_SLIDESHOW__ARROWS = 'pb-slideshow__arrows';
  const PB_NAV__ANNOUNCER = 'pb-nav__announcer';
  const PB_NAV__PLAY = 'pb-nav__play';
  const PB_NAV__PLAY_ICON = 'pb-nav__play-icon';
  const PB_NAV__PAUSE_ICON = 'pb-nav__pause-icon';
  const PB_NAV__PREV = 'pb-nav__prev';
  const PB_NAV__NEXT = 'pb-nav__next';
  const PB_IS_ACTIVE = 'pb-is-active';
  const PB_IS_PLAYING = 'pb-is-playing';
  const PB_IS_PAUSED = 'pb-is-paused';
  const PB_U_REDUCED_MOTION = 'pb-u-reduced-motion';

  const PARAGRAPHS_BUNDLES_PB_SLIDESHOW =
    `.${PARAGRAPHS_BUNDLES}.${PB_SLIDESHOW}`;
  const PARAGRAPHS_BUNDLES_PB_SLIDESHOW_PB_SLIDESHOW__STAGE =
    `${PARAGRAPHS_BUNDLES_PB_SLIDESHOW} .${PB_SLIDESHOW__STAGE}`;

  const PB_SLIDESHOW__STAGE_OWNED_SLIDES =
    `:scope > .${PB_SLIDESHOW__SLIDES}`;
  const PB_SLIDESHOW__STAGE_OWNED_SLIDE =
    `:scope > .${PB_SLIDESHOW__SLIDES} > .${PB_SLIDESHOW__SLIDE}`;
  const PB_SLIDESHOW__STAGE_OWNED_NAV =
    `:scope > .${PB_SLIDESHOW__NAV}`;
  const PB_SLIDESHOW__STAGE_OWNED_NAV_BTN =
    `:scope > .${PB_SLIDESHOW__NAV} > .${PB_SLIDESHOW__NAV_BTN}`;
  const PB_SLIDESHOW__STAGE_OWNED_PLAY =
    `:scope > .${PB_SLIDESHOW__NAV} > .${PB_NAV__PLAY}`;
  const PB_SLIDESHOW__STAGE_OWNED_ARROWS =
    `:scope > .${PB_SLIDESHOW__ARROWS}`;

  const ONCE_PB_PARAGRAPH_BUNDLE_SLIDESHOW_STAGE =
    'pb-paragraph-bundle-slideshow-stage';

  /** Elements that consume typed keys themselves. */
  const PB_KEY_OWNING_TEXT_ENTRY =
    'input, textarea, select, [contenteditable=""], [contenteditable="true"]';

  /** Elements that Space activates natively. */
  const PB_KEY_OWNING_CONTROL =
    `${PB_KEY_OWNING_TEXT_ENTRY}, a[href], button, summary, audio, video,`
    + ' [role="button"], [role="tab"], [role="checkbox"], [role="radio"]';

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
  const stageCleanupHandlers = new WeakMap();

  Drupal.behaviors.paragraphSlideshowBundle = {
    attach(context, settings) {
      const stages = once(
        ONCE_PB_PARAGRAPH_BUNDLE_SLIDESHOW_STAGE,
        PARAGRAPHS_BUNDLES_PB_SLIDESHOW_PB_SLIDESHOW__STAGE,
        context,
      );

      stages.forEach((stage) => {
        const abortController = new AbortController();
        const { signal } = abortController;

        const slidesContainer = stage.querySelector(PB_SLIDESHOW__STAGE_OWNED_SLIDES);
        if (!slidesContainer) {
          return;
        }

        const slides = stage.querySelectorAll(PB_SLIDESHOW__STAGE_OWNED_SLIDE);
        const announcer = stage.querySelector(`:scope > .${PB_NAV__ANNOUNCER}`);
        const playPauseButton = stage.querySelector(
          PB_SLIDESHOW__STAGE_OWNED_PLAY,
        );
        const playIcon = playPauseButton?.querySelector(
          `:scope > .${PB_NAV__PLAY_ICON}`,
        ) ?? null;
        const pauseIcon = playPauseButton?.querySelector(
          `:scope > .${PB_NAV__PAUSE_ICON}`,
        ) ?? null;
        const arrows = stage.querySelector(PB_SLIDESHOW__STAGE_OWNED_ARROWS);
        const nextButton = arrows?.querySelector(`.${PB_NAV__NEXT}`) ?? null;
        const prevButton = arrows?.querySelector(`.${PB_NAV__PREV}`) ?? null;
        const dotsContainer = stage.querySelector(PB_SLIDESHOW__STAGE_OWNED_NAV);
        const dots = stage.querySelectorAll(PB_SLIDESHOW__STAGE_OWNED_NAV_BTN);
        const slideTime = Number.parseInt(stage.dataset.slideTime, 10) || 0;
        const totalSlides = slides.length;

        if (totalSlides === 0) {
          return;
        }

        let slideIndex = 1;
        let isPaused = slideTime === 0;
        let autoSlideIntervalId = null;
        let previousVisibility = false;
        let isVisible = true;
        let touchStartX = 0;

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
         * Updates slide visibility and accessibility state.
         */
        const updateSlideVisibility = () => {
          slides.forEach((slide, index) => {
            const isActive = (index + 1 === slideIndex);
            slide.style.display = isActive ? 'block' : 'none';
            slide.setAttribute('aria-hidden', String(!isActive));
            slide.toggleAttribute('inert', !isActive);
            slide.classList.toggle(PB_IS_ACTIVE, isActive);
            slide.querySelectorAll('a, button, input, textarea, select, [tabindex]').forEach((el) => {
              el.setAttribute('tabindex', isActive ? '0' : '-1');
            });
          });
          updateDots();
          announceSlide();
          adjustHeight();
        };

        /**
         * Adjusts slides container height to match the active slide.
         */
        const adjustHeight = () => {
          const currentSlide = slides[slideIndex - 1];
          if (!currentSlide) {
            return;
          }
          const computedStyle = window.getComputedStyle(slidesContainer);
          const contentHeight = currentSlide.offsetHeight;
          const paddingTop = Number.parseFloat(computedStyle.paddingTop) || 0;
          const paddingBottom = Number.parseFloat(computedStyle.paddingBottom) || 0;
          const borderTop = Number.parseFloat(computedStyle.borderTopWidth) || 0;
          const borderBottom = Number.parseFloat(computedStyle.borderBottomWidth) || 0;
          const totalHeight = contentHeight + paddingTop + paddingBottom + borderTop + borderBottom;
          slidesContainer.style.height = `${totalHeight}px`;
        };

        /**
         * Announces the current slide to screen readers.
         */
        const announceSlide = () => {
          if (announcer) {
            announcer.textContent = `Slide ${slideIndex} of ${totalSlides}`;
          }
        };

        /**
         * Scrolls the active dot into view within the nav row.
         *
         * @param {HTMLElement} container - Nav container element.
         * @param {HTMLElement} dot - Active nav button.
         */
        const scrollDotIntoView = (container, dot) => {
          const containerRect = container.getBoundingClientRect();
          const dotRect = dot.getBoundingClientRect();
          const dotLeft = dotRect.left - containerRect.left + container.scrollLeft;
          const dotRight = dotLeft + dotRect.width;
          const containerWidth = container.clientWidth;
          const scrollLeft = container.scrollLeft;

          if (dotLeft < scrollLeft) {
            container.scrollTo({
              left: dotLeft - 16,
              behavior: 'smooth',
            });
          }
          else if (dotRight > scrollLeft + containerWidth) {
            container.scrollTo({
              left: dotRight - containerWidth + 16,
              behavior: 'smooth',
            });
          }
        };

        /**
         * Updates nav dot active state.
         */
        const updateDots = () => {
          dots.forEach((dot, index) => {
            const isActive = index + 1 === slideIndex;
            dot.classList.toggle(PB_IS_ACTIVE, isActive);
            dot.setAttribute('aria-selected', String(isActive));
            if (isActive && dotsContainer) {
              scrollDotIntoView(dotsContainer, dot);
            }
          });
        };

        /**
         * Starts automatic slide rotation.
         */
        const startAutoSlide = () => {
          stopAutoSlide();
          if (slideTime > 0 && !isPaused && isVisible) {
            autoSlideIntervalId = setInterval(nextSlide, slideTime);
          }
        };

        /**
         * Advances to the next slide.
         */
        const nextSlide = () => {
          slideIndex = (slideIndex % totalSlides) + 1;
          updateSlideVisibility();
        };

        /**
         * Goes to the previous slide.
         */
        const prevSlide = () => {
          slideIndex = (slideIndex === 1) ? totalSlides : slideIndex - 1;
          updateSlideVisibility();
        };

        /**
         * Toggles play/pause for autoplay.
         */
        const togglePlayPause = () => {
          // The state toggles even without a button: a template that renders no
          // play control must still not leave autoplay unstoppable (WCAG 2.2.2).
          isPaused = !isPaused;
          playIcon?.toggleAttribute('hidden', !isPaused);
          pauseIcon?.toggleAttribute('hidden', isPaused);
          playPauseButton?.setAttribute(
            'aria-label',
            isPaused ? 'Play slideshow' : 'Pause slideshow',
          );
          playPauseButton?.classList.toggle(PB_IS_PAUSED, isPaused);
          playPauseButton?.classList.toggle(PB_IS_PLAYING, !isPaused);
          if (slideTime > 0) {
            if (isPaused) {
              stopAutoSlide();
            }
            else {
              startAutoSlide();
            }
          }
        };

        /**
         * Activates a slide from a nav dot index.
         *
         * @param {number} index - Zero-based dot index.
         */
        const handleDotClick = (index) => {
          slideIndex = index + 1;
          updateSlideVisibility();
          startAutoSlide();
        };

        /**
         * Applies reduced-motion user preference.
         */
        const applyReducedMotion = () => {
          if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
          }
          isPaused = true;
          stopAutoSlide();
          slidesContainer.classList.add(PB_U_REDUCED_MOTION);
          if (!playPauseButton) {
            return;
          }
          playIcon?.removeAttribute('hidden');
          pauseIcon?.setAttribute('hidden', '');
          playPauseButton.setAttribute('aria-label', 'Play slideshow');
          playPauseButton.classList.add(PB_IS_PAUSED);
          playPauseButton.classList.remove(PB_IS_PLAYING);
        };

        /**
         * Returns true when more than 20% of the stage is visible.
         *
         * @return {boolean}
         */
        const isMostlyVisible = () => {
          const rect = stage.getBoundingClientRect();
          const visibleHeight = Math.min(rect.bottom, window.innerHeight)
            - Math.max(rect.top, 0);
          return visibleHeight / rect.height > 0.2;
        };

        /**
         * Pauses or resumes autoplay based on scroll visibility.
         */
        const handleVisibility = () => {
          const currentlyVisible = isMostlyVisible();
          if (currentlyVisible !== previousVisibility) {
            previousVisibility = currentlyVisible;
            if (currentlyVisible && !isPaused) {
              startAutoSlide();
            }
            else {
              stopAutoSlide();
            }
          }
        };

        /**
         * Handles document visibility changes.
         */
        const handleSlideshowVisibilityChange = () => {
          if (document.hidden) {
            stopAutoSlide();
            return;
          }
          isVisible = isMostlyVisible();
          if (isVisible && !isPaused) {
            startAutoSlide();
          }
        };

        /**
         * Handles keyboard navigation within the stage.
         *
         * @param {KeyboardEvent} event - Keyboard event.
         */
        const handleKeydown = (event) => {
          const active = document.activeElement;
          if (!(active instanceof HTMLElement) || !stage.contains(active)) {
            return;
          }
          // Text entry owns every printable key and the arrows; never steal
          // them, or a space cannot be typed into a field inside a slide.
          if (active.closest(PB_KEY_OWNING_TEXT_ENTRY)) {
            return;
          }
          if (event.key === 'ArrowRight') {
            nextSlide();
          }
          else if (event.key === 'ArrowLeft') {
            prevSlide();
          }
          // Space activates a focused control natively (WCAG 2.1.1), so the
          // play/pause shortcut only applies outside one.
          else if (event.key === ' ' && !active.closest(PB_KEY_OWNING_CONTROL)) {
            event.preventDefault();
            togglePlayPause();
          }
        };

        /**
         * Records touch start position for swipe detection.
         *
         * @param {TouchEvent} event - Touch event.
         */
        const handleTouchStart = (event) => {
          touchStartX = event.touches[0].clientX;
        };

        /**
         * Handles swipe gestures on the slide track.
         *
         * @param {TouchEvent} event - Touch event.
         */
        const handleTouchEnd = (event) => {
          const delta = event.changedTouches[0].clientX - touchStartX;
          if (delta > 50) {
            prevSlide();
          }
          else if (delta < -50) {
            nextSlide();
          }
          startAutoSlide();
        };

        const intersectionObserver = new IntersectionObserver((entries) => {
          const entry = entries[0];
          isVisible = entry.isIntersecting;
          if (isVisible && !isPaused) {
            startAutoSlide();
          }
          else {
            stopAutoSlide();
          }
        }, {
          threshold: 0.5,
        });
        intersectionObserver.observe(stage);

        const debouncedHandleVisibility = debounce(handleVisibility, 200);
        const debouncedResize = debounce(() => {
          adjustHeight();
          handleVisibility();
        }, 200);

        playPauseButton?.addEventListener('click', togglePlayPause, { signal });
        nextButton?.addEventListener('click', nextSlide, { signal });
        prevButton?.addEventListener('click', prevSlide, { signal });

        dots.forEach((dot, index) => {
          dot.addEventListener('click', () => handleDotClick(index), { signal });
        });

        slidesContainer.addEventListener('mouseenter', stopAutoSlide, { signal });
        slidesContainer.addEventListener('mouseleave', () => {
          if (!isPaused) {
            startAutoSlide();
          }
        }, { signal });

        slidesContainer.addEventListener('touchstart', handleTouchStart, {
          passive: true,
          signal,
        });
        slidesContainer.addEventListener('touchend', handleTouchEnd, { signal });
        stage.addEventListener('keydown', handleKeydown, { signal });
        document.addEventListener('scroll', debouncedHandleVisibility, {
          passive: true,
          signal,
        });
        window.addEventListener('resize', debouncedResize, { signal });
        document.addEventListener(
          'visibilitychange',
          handleSlideshowVisibilityChange,
          { signal },
        );

        stageCleanupHandlers.set(stage, () => {
          abortController.abort();
          intersectionObserver.disconnect();
          stopAutoSlide();
        });

        applyReducedMotion();
        updateSlideVisibility();
        startAutoSlide();
      });
    },

    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }

      once.remove(
        ONCE_PB_PARAGRAPH_BUNDLE_SLIDESHOW_STAGE,
        PARAGRAPHS_BUNDLES_PB_SLIDESHOW_PB_SLIDESHOW__STAGE,
        context,
      ).forEach((stage) => {
        stageCleanupHandlers.get(stage)?.();
        stageCleanupHandlers.delete(stage);
      });
    },
  };
})(Drupal, drupalSettings, once);
