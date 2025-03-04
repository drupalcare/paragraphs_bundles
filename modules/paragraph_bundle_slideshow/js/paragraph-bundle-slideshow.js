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
        const slideTime = parseInt(slideshowInner.querySelector('.pb__ps-value')?.dataset.slideTime, 10) || 0;
        const totalSlides = slides.length;
        let slideIndex = 1;
        let isPaused = slideTime === 0;
        let autoSlideIntervalId = null;
        let resizeTimeout;
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
          slideshow.style.height = `${slides[slideIndex - 1].offsetHeight}px`;
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
          if (slideTime > 0 && !isPaused) {
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
          playPauseButton.innerHTML = isPaused ? '<span>&#9654;</span>' : '<span>&#10073;&nbsp;&#10073;</span>';
          playPauseButton.setAttribute('aria-label', isPaused ? 'Play slideshow' : 'Pause slideshow');
          isPaused ? stopAutoSlide() : startAutoSlide();
        };
        const handleDotClick = (index) => {
          slideIndex = index + 1;
          updateSlideVisibility();
          startAutoSlide();
        };
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
        window.addEventListener('resize', () => {
          clearTimeout(resizeTimeout);
          resizeTimeout = setTimeout(adjustHeight, 200);
        });
        document.addEventListener('visibilitychange', () => {
          document.hidden ? stopAutoSlide() : startAutoSlide();
        });
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
          isPaused = true;
          stopAutoSlide();
          slideshow.classList.add('reduced-motion');
          playPauseButton.innerHTML = '<span>&#9654;</span>';
        }
        // Initialize
        updateSlideVisibility();
        startAutoSlide();
      });
    }
  };
})(Drupal, drupalSettings, once);
