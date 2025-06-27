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

  function debounce(func, delay) {
    let timer;
    return function() {
      clearTimeout(timer);
      timer = setTimeout(func, delay);
    };
  }
  const playIcon = `
    <span class="visually-hidden">Play and Stop Slideshow</span>
    <span><svg class="svg-play" xmlns="http://www.w3.org/2000/svg" viewBox="80 -880 800 800" fill="currentColor">
      <path d="m380-300 280-180-280-180v360ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"></path>
    </svg></span>`;
  const pauseIcon = `
    <span class="visually-hidden">Play and Stop Slideshow</span>
    <span><svg class="svg-pause" xmlns="http://www.w3.org/2000/svg" viewBox="80 -880 800 800" fill="currentColor"><path d="M360-320h80v-320h-80v320Zm160 0h80v-320h-80v320ZM480-80q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q134 0 227-93t93-227q0-134-93-227t-227-93q-134 0-227 93t-93 227q0 134 93 227t227 93Zm0-320Z"></path></svg></span>`;

  Drupal.behaviors.paragraphSlideshowBundle = {
    attach(context) {
      const slideshows = once('paragraphSlideshowBundle', '.pb__slideshow-inner', context);
      if (!slideshows.length) {
        return;
      }
      slideshows.forEach((slideshowInner) => {
        const slideshow = slideshowInner.querySelector('.pb__slides');
        const slides = slideshow.querySelectorAll('.pb__slide-item');
        const announcer = slideshowInner.querySelector('.pb__announcer');
        const playPauseButton = slideshowInner.querySelector(`#btn-${slideshowInner.id.split('-').pop()}`);
        const nextButton = slideshowInner.querySelector('.pb__next');
        const prevButton = slideshowInner.querySelector('.pb__prev');
        const dots = slideshowInner.querySelectorAll('.pb__slide-bottom-btn');
        const slideTime = parseInt(slideshowInner.dataset.slideTime, 10) || 0;
        const totalSlides = slides.length;
        let slideIndex = 1;
        let isPaused = slideTime === 0;
        let autoSlideIntervalId = null;
        let previousVisibility = false;
        let isVisible = true;
        const updateSlideVisibility = () => {
          slides.forEach((slide, index) => {
            const isActive = (index + 1 === slideIndex);
            slide.style.display = isActive ? 'block' : 'none';
            slide.setAttribute('aria-hidden', !isActive);
            slide.toggleAttribute('inert', !isActive);
            slide.classList.toggle('active', isActive);
            slide.querySelectorAll('a, button, input').forEach(el => {
              el.setAttribute('tabindex', isActive ? '0' : '-1');
            });
          });
          updateDots();
          announceSlide();
          adjustHeight();
        };
        const adjustHeight = () => {
          const computedStyle = window.getComputedStyle(slideshow);
          const contentHeight = slides[slideIndex - 1].offsetHeight;
          const paddingTop = parseFloat(computedStyle.paddingTop) || 0;
          const paddingBottom = parseFloat(computedStyle.paddingBottom) || 0;
          const borderTop = parseFloat(computedStyle.borderTopWidth) || 0;
          const borderBottom = parseFloat(computedStyle.borderBottomWidth) || 0;
          const totalHeight = contentHeight + paddingTop + paddingBottom + borderTop + borderBottom;
          slideshow.style.height = `${totalHeight}px`;
        };
        const announceSlide = () => {
          announcer.textContent = `Slide ${slideIndex} of ${totalSlides}`;
        };
        const updateDots = () => {
          dots.forEach((dot, index) => {
            dot.classList.toggle('pb__active', index + 1 === slideIndex);
            dot.setAttribute('aria-selected', index + 1 === slideIndex);
          });
        };
        const startAutoSlide = () => {
          stopAutoSlide();
          if (slideTime > 0 && !isPaused && isVisible) {
            autoSlideIntervalId = setInterval(nextSlide, slideTime);
          }
        };
        const stopAutoSlide = () => {
          clearInterval(autoSlideIntervalId);
        };
        const nextSlide = () => {
          slideIndex = (slideIndex % totalSlides) + 1;
          updateSlideVisibility();
        };
        const prevSlide = () => {
          slideIndex = (slideIndex === 1) ? totalSlides : slideIndex - 1;
          updateSlideVisibility();
        };

        const togglePlayPause = () => {
          isPaused = !isPaused;
          playPauseButton.innerHTML = isPaused ? playIcon : pauseIcon;
          playPauseButton.setAttribute('aria-label', isPaused ? 'Play slideshow' : 'Pause slideshow');
          playPauseButton.classList.toggle('is-paused', isPaused);
          playPauseButton.classList.toggle('is-playing', !isPaused);
          if (slideTime > 0) {
            isPaused ? stopAutoSlide() : startAutoSlide();
          }
        };

        const handleDotClick = (index) => {
          slideIndex = index + 1;
          updateSlideVisibility();
          startAutoSlide();
        };
        const applyReducedMotion = () => {
          if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            isPaused = true;
            stopAutoSlide();
            slideshow.classList.add('reduced-motion');
            playPauseButton.innerHTML = playIcon;
          }
        };
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
        playPauseButton?.addEventListener('click', togglePlayPause);
        nextButton?.addEventListener('click', nextSlide);
        prevButton?.addEventListener('click', prevSlide);
        dots.forEach((dot, index) => {
          dot.addEventListener('click', () => handleDotClick(index));
        });
        slideshow.addEventListener('mouseover', stopAutoSlide);
        slideshow.addEventListener('mouseout', startAutoSlide);
        slideshow.addEventListener('touchstart', (e) => {
          slideshow._touchStartX = e.touches[0].clientX;
        });
        slideshow.addEventListener('touchend', (e) => {
          const delta = e.changedTouches[0].clientX - slideshow._touchStartX;
          delta > 50 ? prevSlide() : delta < -50 ? nextSlide() : null;
          startAutoSlide();
        });
        document.addEventListener('keydown', (e) => {
          if (e.key === 'ArrowRight') nextSlide();
          else if (e.key === 'ArrowLeft') prevSlide();
          else if (e.key === ' ') {
            e.preventDefault();
            togglePlayPause();
          }
        });

        function handleSlideshowVisibilityChange() {
          if (document.hidden) {
            stopAutoSlide();
          } else {
            isVisible = isMostlyVisible(slideshowInner);
            if (isVisible && !isPaused) {
              startAutoSlide();
            }
          }
        }
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
          isPaused = true;
          stopAutoSlide();
          slideshow.classList.add('reduced-motion');
          playPauseButton.innerHTML = playIcon;
        }
        const isMostlyVisible = (element) => {
          const rect = element.getBoundingClientRect();
          const visibleHeight = Math.min(rect.bottom, window.innerHeight) - Math.max(rect.top, 0);
          return visibleHeight / rect.height > 0.2;
        };

        function handleVisibility() {
          const currentlyVisible = isMostlyVisible(slideshowInner);
          if (currentlyVisible !== previousVisibility) {
            previousVisibility = currentlyVisible;
            if (currentlyVisible && !isPaused) {
              startAutoSlide();
            } else {
              stopAutoSlide();
            }
          }
        }

        function setupListeners() {
          document.addEventListener('scroll', debounce(handleVisibility, 200));
          window.addEventListener('resize', debounce(() => {
            adjustHeight();
            handleVisibility();
          }, 200));
          document.addEventListener('visibilitychange', handleSlideshowVisibilityChange);
        }
        // Initialize
        applyReducedMotion();
        updateSlideVisibility();
        startAutoSlide();
        setupListeners();
      });
    }
  };
})(Drupal, drupalSettings, once);
