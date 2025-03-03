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
  Drupal.behaviors.paragraphSlideshowBundle = {
    attach(context) {
      const slideshows = once('paragraphSlideshowBundle', '.pb__slides', context);
      if (!slideshows.length) {
        return;
      }
      slideshows.forEach((slideshowContainer) => {
        const parentId = parseInt(slideshowContainer.id.split('-')[1]);
        const slides = slideshowContainer.querySelectorAll('.pb__slide-item');
        const playPauseButton = document.getElementById(`btn-${parentId}`);
        const slideTime = parseInt(slideshowContainer.closest('.pb__slideshow-inner').querySelector('.pb__ps-value')?.dataset.slideTime, 10) || 0;
        const announcer = slideshowContainer.closest('.pb__slideshow-inner').querySelector('.pb__announcer');
        const totalSlides = slides.length;
        let slideIndex = 1;
        let autoSlideIntervalId = null;
        let isPaused = false;
        // Check for reduced motion preference once.
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const updateSlideVisibility = () => {
          slides.forEach((slide, index) => {
            const isActive = (index + 1 === slideIndex);
            slide.style.display = isActive ? 'block' : 'none';
            slide.setAttribute('aria-hidden', String(!isActive));
            slide.classList.toggle('active', isActive);
            const focusableElements = slide.querySelectorAll('a, button, input, textarea, select');
            if (isActive) {
              slide.removeAttribute('inert');
              focusableElements.forEach(el => el.removeAttribute('tabindex'));
              setTimeout(() => {
                slideshowContainer.style.height = `${slide.offsetHeight}px`;
              }, 50);
            } else {
              slide.setAttribute('inert', '');
              focusableElements.forEach(el => el.setAttribute('tabindex', '-1'));
            }
          });
          if (announcer) {
            announcer.textContent = `Slide ${slideIndex} selected`;
          }
          updateNavigationState();
        };
        const updateNavigationState = () => {
          const buttons = slideshowContainer.closest('.pb__slideshow-inner').querySelectorAll('.pb__slide-bottom-btn');
          buttons.forEach((button, index) => {
            button.classList.toggle('pb__active', index + 1 === slideIndex);
            button.setAttribute('aria-selected', String(index + 1 === slideIndex));
          });
        };
        const startAutoSlide = () => {
          stopAutoSlide();
          if (slideTime > 0 && !isPaused) {
            autoSlideIntervalId = setInterval(() => {
              slideIndex = (slideIndex % totalSlides) + 1;
              updateSlideVisibility();
            }, slideTime);
          }
        };
        const stopAutoSlide = () => {
          clearInterval(autoSlideIntervalId);
          autoSlideIntervalId = null;
        };
        const showNextSlide = () => {
          slideIndex = (slideIndex % totalSlides) + 1;
          updateSlideVisibility();
          startAutoSlide();
        };
        const showPreviousSlide = () => {
          slideIndex = (slideIndex === 1) ? totalSlides : slideIndex - 1;
          updateSlideVisibility();
          startAutoSlide();
        };
        const togglePlayPause = (event) => {
          event.preventDefault();
          isPaused = !isPaused;
          if (playPauseButton) {
            playPauseButton.innerHTML = isPaused ? '<span>&#9654;</span>' : '<span>&#10073;&nbsp;&#10073;</span>';
            playPauseButton.setAttribute('aria-label', isPaused ? 'Play slideshow' : 'Pause slideshow');
          }
          if (isPaused) {
            stopAutoSlide();
          } else {
            startAutoSlide();
          }
          playPauseButton?.blur();
        };
        if (playPauseButton) {
          playPauseButton.addEventListener('click', togglePlayPause);
        }
        const nextButton = slideshowContainer.closest('.pb__slideshow-inner').querySelector('.pb__next');
        const prevButton = slideshowContainer.closest('.pb__slideshow-inner').querySelector('.pb__prev');
        if (nextButton) {
          nextButton.addEventListener('click', (event) => {
            event.preventDefault();
            showNextSlide();
            nextButton.blur();
          });
        }
        if (prevButton) {
          prevButton.addEventListener('click', (event) => {
            event.preventDefault();
            showPreviousSlide();
            prevButton.blur();
          });
        }
        slideshowContainer.closest('.pb__slideshow-inner').querySelectorAll('.pb__slide-bottom-btn').forEach((button, index) => {
          button.addEventListener('click', (event) => {
            event.preventDefault();
            slideIndex = index + 1;
            updateSlideVisibility();
            startAutoSlide();
            button.blur();
          });
        });
        slideshowContainer.addEventListener('mouseover', stopAutoSlide);
        slideshowContainer.addEventListener('mouseout', startAutoSlide);
        slideshowContainer.addEventListener('keydown', (event) => {
          if (event.key === 'ArrowRight') {
            showNextSlide();
          } else if (event.key === 'ArrowLeft') {
            showPreviousSlide();
          } else if (event.key === ' ') {
            event.preventDefault();
            togglePlayPause(event);
          }
        });
        let touchStartX = 0;
        slideshowContainer.addEventListener('touchstart', (event) => {
          touchStartX = event.touches[0].clientX;
        });
        slideshowContainer.addEventListener('touchend', (event) => {
          const touchEndX = event.changedTouches[0].clientX;
          if (touchEndX - touchStartX > 50) {
            showPreviousSlide();
          } else if (touchStartX - touchEndX > 50) {
            showNextSlide();
          }
        });
        const resetHeight = () => {
          updateSlideVisibility();
        };
        window.addEventListener('resize', () => {
          clearTimeout(slideshowContainer.resizeTimeout);
          slideshowContainer.resizeTimeout = setTimeout(resetHeight, 200);
        });
        // Apply reduced motion preference (this only affects CSS transitions, not the logic).
        if (prefersReducedMotion) {
          slides.forEach(slide => {
            slide.style.transition = 'none';
          });
          slideshowContainer.style.transition = 'none';
        }
        // Visibility change handling (pause when hidden, resume when visible).
        if (!document.paragraphSlideshowVisibilityAttached) {
          document.paragraphSlideshowVisibilityAttached = true;
          document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
              stopAutoSlide();
            } else if (!isPaused) {
              startAutoSlide();
            }
          });
        }
        updateSlideVisibility();
        startAutoSlide();
      });
    }
  };
})(Drupal, drupalSettings, once);
