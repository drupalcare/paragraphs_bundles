/**
 * @file
 * Paragraph Bundle Parallax - Updated Version with Legacy Mode Support.
 *
 * Filename:     paragraph-bundle-parallax.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */

((Drupal, drupalSettings, once) => {
  'use strict';

  /**
   * Check if parallax should be active based on breakpoint.
   */
  function shouldRunParallax(element) {
    const breakpoint = element.getAttribute('data-breakpoint');

    // If "all" or no breakpoint, always run parallax
    if (!breakpoint || breakpoint === 'all') {
      return true;
    }

    // If numeric breakpoint, check if current width is larger
    const bp = parseInt(breakpoint, 10);
    if (Number.isFinite(bp)) {
      return window.innerWidth > bp;
    }

    return true; // Fallback to active
  }

  /**
   * Apply parallax effect and update visibility in one function.
   */
  function updateParallax(element) {
    // Skip if legacy mode is enabled (CSS handles it)
    if (element.classList.contains('parallax-css')) {
      return;
    }

    // Only run if breakpoint allows it
    if (!shouldRunParallax(element)) {
      return;
    }

    const bgEl = element.querySelector('.pb__parallax-bg');
    const inner = element.querySelector('.paragraph__inner');

    if (!bgEl) return;

    const speed = parseFloat(element.getAttribute('data-parallax-speed')) || 0.6;
    const effect = element.getAttribute('data-parallax-effect') || 'translate';

    const scrollPosition = window.pageYOffset;
    const windowHeight = window.innerHeight;
    const elementRect = element.getBoundingClientRect();
    const elementTop = elementRect.top + scrollPosition;
    const elementHeight = element.offsetHeight;

    if (scrollPosition + windowHeight > elementTop && scrollPosition < elementTop + elementHeight) {
      const offset = ((scrollPosition + windowHeight - elementTop) * speed) - (windowHeight * speed);

      // Reset previous transforms
      bgEl.style.transform = '';
      bgEl.style.filter = '';
      bgEl.style.opacity = '';

      switch (effect) {
        case 'translate':
          bgEl.style.transform = `translateY(${offset}px)`;
          break;
        case 'blur':
          const blurAmount = Math.min(Math.abs(offset) / 100, 10);
          bgEl.style.filter = `blur(${blurAmount}px)`;
          break;
        case 'fade':
        case 'opacity':
          const opacity = Math.max(1 - Math.abs(offset) / 1000, 0);
          bgEl.style.opacity = opacity;
          break;
        case 'scale':
          const scaleAmount = Math.max(1 + Math.abs(offset) / 1000, 1);
          bgEl.style.transform = `scale(${scaleAmount})`;
          break;
        case 'rotate':
          const rotateAmount = Math.max(-8, Math.min(8, offset / 10));
          bgEl.style.transform = `rotate(${rotateAmount}deg)`;
          break;
        default:
          bgEl.style.transform = `translateY(${offset}px)`;
          break;
      }

      // Add visible class when in view (same as vvjp.js pattern)
      if (inner) {
        inner.classList.add('visible');
      }
    } else {
      // Remove visible class when out of view (same as vvjp.js pattern)
      if (inner) {
        inner.classList.remove('visible');
      }
    }
  }

  /**
   * Handle scroll events.
   */
  function handleScroll() {
    const elements = document.querySelectorAll('.paragraph--type--parallax-section-bundle');
    elements.forEach(element => {
      updateParallax(element);
    });
  }

  /**
   * Handle resize events.
   */
  function handleResize() {
    // Just run a scroll update to recalculate everything
    handleScroll();
  }

  Drupal.behaviors.ParagraphBundleParallax = {
    attach: function (context, settings) {
      const elements = once('paragraphBundleParallax', '.paragraph--type--parallax-section-bundle', context);

      if (elements.length > 0) {
        // Bind scroll and resize listeners only once
        window.addEventListener('scroll', handleScroll, { passive: true });
        window.addEventListener('resize', handleResize);

        // Initial call
        handleScroll();
      }
    }
  };

})(Drupal, drupalSettings, once);
