/**
 * @file
 * Paragraph Bundle 3D Carousel.
 *
 * 3D ring carousel with autoplay, keyboard navigation, touch, and
 * ping-pong slide behavior at the ends.
 *
 * Filename:     paragraph-bundle-3d-carousel.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing

((Drupal, once) => {
  const PARAGRAPHS_BUNDLES = 'paragraphs-bundles';
  const PB_3D_CAROUSEL = 'pb-3d-carousel';
  const PB_3D_CAROUSEL__RING = 'pb-3d-carousel__ring';
  const PB_3D_CAROUSEL__SLIDE = 'pb-3d-carousel__slide';
  const PB_NAV__PLAY = 'pb-nav__play';
  const PB_NAV__PAUSE = 'pb-nav__pause';
  const PB_NAV__PLAY_ICON = 'pb-nav__play-icon';
  const PB_NAV__PAUSE_ICON = 'pb-nav__pause-icon';
  const PB_NAV__PREV = 'pb-nav__prev';
  const PB_NAV__NEXT = 'pb-nav__next';
  const PB_IS_ACTIVE = 'pb-is-active';

  const PARAGRAPHS_BUNDLES_PB_3D_CAROUSEL = `.${PARAGRAPHS_BUNDLES}.${PB_3D_CAROUSEL}`;
  const PARAGRAPHS_BUNDLES_PB_3D_CAROUSEL_PB_3D_CAROUSEL__RING = `${PARAGRAPHS_BUNDLES_PB_3D_CAROUSEL} .${PB_3D_CAROUSEL__RING}`;

  const PB_3D_CAROUSEL__RING_OWNED_SLIDE = `:scope > .${PB_3D_CAROUSEL__SLIDE}`;

  const ONCE_PB_PARAGRAPH_BUNDLE_3D_CAROUSEL_RING =
    'pb-paragraph-bundle-3d-carousel-ring';

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
  const ringCleanupHandlers = new WeakMap();

  /**
   * Checks if an element is mostly visible in the viewport.
   *
   * @param {HTMLElement} element - The element to check.
   * @return {boolean} True if more than 20% of the element is visible.
   */
  const isMostlyVisible = (element) => {
    const rect = element.getBoundingClientRect();
    if (rect.height <= 0) {
      return false;
    }
    const visibleHeight =
      Math.min(rect.bottom, window.innerHeight) - Math.max(rect.top, 0);
    return visibleHeight / rect.height > 0.2;
  };

  Drupal.behaviors.paragraphBundle3dCarousel = {
    attach(context) {
      const rings = once(
        ONCE_PB_PARAGRAPH_BUNDLE_3D_CAROUSEL_RING,
        PARAGRAPHS_BUNDLES_PB_3D_CAROUSEL_PB_3D_CAROUSEL__RING,
        context,
      );

      rings.forEach((ring) => {
        const abortController = new AbortController();
        const { signal } = abortController;

        const ringId = ring.id;
        const uniqueId = ringId.replace(/^pb-3d-carousel__ring-/, '');
        const cells = ring.querySelectorAll(PB_3D_CAROUSEL__RING_OWNED_SLIDE);
        const cellCount = cells.length;

        if (cellCount === 0) {
          return;
        }

        const root = ring.closest(PARAGRAPHS_BUNDLES_PB_3D_CAROUSEL);
        const slideNumberElement = document.getElementById(`pb-3d-carousel__index-${uniqueId}`);
        const prevButton = document.getElementById(`pb-3d-carousel__prev-${uniqueId}`);
        const nextButton = document.getElementById(`pb-3d-carousel__next-${uniqueId}`);
        const playPauseButton = document.getElementById(`pb-3d-carousel__play-pause-${uniqueId}`);
        const playIcon =
          playPauseButton?.querySelector(`:scope > .${PB_NAV__PLAY_ICON}`) ??
          null;
        const pauseIcon =
          playPauseButton?.querySelector(`:scope > .${PB_NAV__PAUSE_ICON}`) ??
          null;

        let currentIndex = 0;
        let direction = 1;
        let isPaused = false;
        let intervalId = null;
        let previousVisibility = isMostlyVisible(ring);
        let touchStartX = 0;
        let touchEndX = 0;
        let lastKnownWidth = window.innerWidth;
        let resizeTimeout = null;

        const rotationDelay =
          Number.parseInt(ring.getAttribute('data-rotation-delay'), 10) || 0;

        if (rotationDelay <= 0) {
          isPaused = true;
        }

        // Respect prefers-reduced-motion: start paused so autoplay never runs
        // automatically; the play/pause control still lets users opt in.
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
          isPaused = true;
        }

        /**
         * @return {boolean}
         */
        const prefersReducedMotion = () =>
          window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        /**
         * Clears autoplay interval.
         */
        const pauseAutoPlay = () => {
          if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
          }
        };

        /**
         * Updates play/pause button visuals.
         *
         * @param {boolean} paused - Whether autoplay is paused.
         */
        const updatePlayPauseButton = (paused) => {
          if (!playPauseButton) {
            return;
          }

          playPauseButton.classList.toggle(PB_NAV__PAUSE, !paused);
          playPauseButton.classList.toggle(PB_NAV__PLAY, paused);
          playIcon?.toggleAttribute('hidden', !paused);
          pauseIcon?.toggleAttribute('hidden', paused);
          playPauseButton.setAttribute(
            'aria-label',
            paused ? Drupal.t('Play slideshow') : Drupal.t('Pause slideshow'),
          );
          playPauseButton.setAttribute('aria-pressed', String(!paused));
        };

        /**
         * Updates tabindex for focusable elements within a cell.
         *
         * @param {HTMLElement} cell - Slide element.
         * @param {boolean} isActive - Whether the slide is active.
         */
        const updateFocusableElements = (cell, isActive) => {
          cell
            .querySelectorAll('a, button, input, textarea, select, [tabindex]')
            .forEach((el) => {
              el.setAttribute('tabindex', isActive ? '0' : '-1');
            });
        };

        /**
         * Updates prev/next button visibility at ends.
         */
        const updateNavVisibility = () => {
          if (prevButton) {
            const hidden = currentIndex === 0;
            prevButton.classList.toggle('pb-is-hidden', hidden);
            prevButton.setAttribute('tabindex', hidden ? '-1' : '0');
          }

          if (nextButton) {
            const hidden = currentIndex === cellCount - 1;
            nextButton.classList.toggle('pb-is-hidden', hidden);
            nextButton.setAttribute('tabindex', hidden ? '-1' : '0');
          }
        };

        /**
         * Gets stable carousel geometry for the current slide count.
         *
         * @return {{theta: number, radius: number}}
         *   Rotation angle and Z radius.
         */
        const getCarouselGeometry = () => {
          if (cellCount <= 1) {
            return { theta: 0, radius: 0 };
          }

          const theta = 360 / cellCount;
          const radius = Math.round(
            ring.offsetWidth / 2 / Math.tan(Math.PI / cellCount),
          );

          return { theta, radius };
        };

        /**
         * Positions cells in the 3D ring.
         */
        const buildCarousel = () => {
          const { theta, radius } = getCarouselGeometry();
          const reducedMotion = prefersReducedMotion();

          cells.forEach((cell, index) => {
            cell.style.opacity = '1';
            cell.style.transform = `rotateY(${theta * index}deg) translateZ(${radius}px)`;
            cell.style.transition = reducedMotion ? 'none' : '';
          });

          ring.style.transition = reducedMotion ? 'none' : '';
          cells.forEach((cell) => cell.classList.remove(PB_IS_ACTIVE));
          cells[0]?.classList.add(PB_IS_ACTIVE);
          updateNavVisibility();
        };

        /**
         * Updates ring transform and slide state.
         */
        const updateSlide = () => {
          const { theta, radius } = getCarouselGeometry();
          const angle = theta * currentIndex * -1;
          const reducedMotion = prefersReducedMotion();

          ring.style.transition = reducedMotion
            ? 'none'
            : 'transform 0.8s ease-in-out';
          ring.style.transform = `translateZ(${-radius}px) rotateY(${angle}deg)`;

          cells.forEach((cell, index) => {
            const isActive = index === currentIndex;
            cell.setAttribute('aria-hidden', String(!isActive));
            cell.toggleAttribute('inert', !isActive);
            updateFocusableElements(cell, isActive);
            cell.classList.toggle(PB_IS_ACTIVE, isActive);
          });

          if (slideNumberElement) {
            slideNumberElement.setAttribute(
              'aria-label',
              Drupal.t('Slide @current of @total', {
                '@current': currentIndex + 1,
                '@total': cellCount,
              }),
            );
            slideNumberElement.textContent = String(currentIndex + 1);
          }

          updateNavVisibility();
        };

        /**
         * Advances slide with ping-pong behavior.
         */
        const advanceSlide = () => {
          if (cellCount <= 1) {
            currentIndex = 0;
            direction = 1;
            updateSlide();
            return;
          }

          currentIndex += direction;

          if (currentIndex >= cellCount) {
            currentIndex = cellCount - 2;
            direction = -1;
          } else if (currentIndex < 0) {
            currentIndex = 1;
            direction = 1;
          }

          updateSlide();
        };

        /**
         * Starts autoplay when allowed.
         */
        const startAutoPlay = () => {
          pauseAutoPlay();
          if (
            cellCount > 1 &&
            rotationDelay > 0 &&
            !isPaused &&
            isMostlyVisible(ring)
          ) {
            intervalId = setInterval(() => advanceSlide(), rotationDelay);
          }
        };

        /**
         * Restarts autoplay after manual navigation.
         */
        const restartAutoPlay = () => {
          pauseAutoPlay();
          startAutoPlay();
        };

        /**
         * Manual navigation handler.
         *
         * @param {number} step - Direction step (-1 or 1).
         */
        const manualAdvance = (step) => {
          if (cellCount <= 1) {
            return;
          }

          direction = step;
          advanceSlide();
          restartAutoPlay();
        };

        /**
         * Toggles autoplay.
         */
        const togglePlayPause = () => {
          isPaused = !isPaused;
          updatePlayPauseButton(isPaused);
          if (isPaused) {
            pauseAutoPlay();
          } else {
            restartAutoPlay();
          }
        };

        /**
         * Handles swipe gestures.
         */
        const handleSwipe = () => {
          const swipeThreshold = 50;
          if (touchStartX - touchEndX > swipeThreshold) {
            manualAdvance(1);
          } else if (touchEndX - touchStartX > swipeThreshold) {
            manualAdvance(-1);
          }
        };

        /**
         * Pauses or resumes autoplay based on viewport visibility.
         */
        const handleCarouselVisibility = () => {
          const currentlyVisible = isMostlyVisible(ring);
          if (currentlyVisible !== previousVisibility) {
            previousVisibility = currentlyVisible;
            if (currentlyVisible && !isPaused) {
              startAutoPlay();
            } else {
              pauseAutoPlay();
            }
          }
        };

        /**
         * Resets ring layout after significant resize.
         */
        const handleResizeOrRotate = () => {
          clearTimeout(resizeTimeout);
          resizeTimeout = setTimeout(() => {
            const newWidth = window.innerWidth;
            if (Math.abs(newWidth - lastKnownWidth) >= 50) {
              currentIndex = 0;
              direction = 1;
              buildCarousel();
              updateSlide();
              restartAutoPlay();
              lastKnownWidth = newWidth;
            }
          }, 200);
        };

        /**
         * Pauses autoplay when the tab is hidden.
         */
        const handleVisibilityChange = () => {
          if (document.hidden) {
            pauseAutoPlay();
          } else if (isMostlyVisible(ring) && !isPaused) {
            updateSlide();
            startAutoPlay();
          }
        };

        const debouncedHandleVisibility = debounce(
          handleCarouselVisibility,
          200,
        );
        const debouncedResize = debounce(() => {
          handleCarouselVisibility();
          handleResizeOrRotate();
        }, 200);

        buildCarousel();
        updatePlayPauseButton(isPaused);

        if (playPauseButton) {
          playPauseButton.style.display = 'flex';
        }

        updateSlide();

        if (previousVisibility && !isPaused) {
          startAutoPlay();
        }

        nextButton?.addEventListener('click', () => manualAdvance(1), {
          signal,
        });
        prevButton?.addEventListener('click', () => manualAdvance(-1), {
          signal,
        });
        playPauseButton?.addEventListener('click', togglePlayPause, { signal });
        playPauseButton?.addEventListener(
          'keydown',
          (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
              event.preventDefault();
              togglePlayPause();
            }
          },
          { signal },
        );

        ring.addEventListener(
          'keydown',
          (event) => {
            if (event.key === 'ArrowRight') {
              event.preventDefault();
              manualAdvance(1);
            } else if (event.key === 'ArrowLeft') {
              event.preventDefault();
              manualAdvance(-1);
            }
          },
          { signal },
        );

        ring.addEventListener('mouseenter', pauseAutoPlay, { signal });
        ring.addEventListener(
          'mouseleave',
          () => {
            if (!isPaused) {
              startAutoPlay();
            }
          },
          { signal },
        );

        ring.addEventListener(
          'touchstart',
          (event) => {
            touchStartX = event.touches[0].clientX;
          },
          { passive: true, signal },
        );
        ring.addEventListener(
          'touchmove',
          (event) => {
            touchEndX = event.touches[0].clientX;
          },
          { passive: true, signal },
        );
        ring.addEventListener('touchend', handleSwipe, { signal });

        document.addEventListener('scroll', debouncedHandleVisibility, {
          passive: true,
          signal,
        });
        window.addEventListener('orientationchange', debouncedResize, {
          signal,
        });
        window.addEventListener('resize', debouncedResize, { signal });
        document.addEventListener('visibilitychange', handleVisibilityChange, {
          signal,
        });

        ringCleanupHandlers.set(ring, () => {
          abortController.abort();
          pauseAutoPlay();
          clearTimeout(resizeTimeout);
          ring.style.transform = '';
          ring.style.transition = '';
          cells.forEach((cell) => {
            cell.style.opacity = '';
            cell.style.transform = '';
            cell.style.transition = '';
            cell.classList.remove(PB_IS_ACTIVE);
            cell.setAttribute('aria-hidden', 'true');
            cell.removeAttribute('inert');
          });
          if (root instanceof HTMLElement) {
            root
              .querySelectorAll(
                `#prev-${uniqueId}, #next-${uniqueId}, #btn-${uniqueId}`,
              )
              .forEach((button) => {
                button.classList.remove('pb-is-hidden');
                button.setAttribute('tabindex', '0');
              });
          }
        });
      });
    },

    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }

      once
        .remove(
          ONCE_PB_PARAGRAPH_BUNDLE_3D_CAROUSEL_RING,
          PARAGRAPHS_BUNDLES_PB_3D_CAROUSEL_PB_3D_CAROUSEL__RING,
          context,
        )
        .forEach((ring) => {
          ringCleanupHandlers.get(ring)?.();
          ringCleanupHandlers.delete(ring);
        });
    },
  };
})(Drupal, once);
