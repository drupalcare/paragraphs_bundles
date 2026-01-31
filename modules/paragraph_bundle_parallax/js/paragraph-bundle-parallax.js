/**
 * @file
 * Paragraph Bundle Parallax - Production Ready.
 *
 * Provides parallax scrolling effects with multiple animation types,
 * breakpoint support, reduced motion preferences, and visibility detection.
 *
 * Filename:     paragraph-bundle-parallax.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */

((Drupal, drupalSettings, once) => {
  'use strict';

  /**
   * Track if global listeners have been attached.
   *
   * @type {boolean}
   */
  let listenersAttached = false;

  /**
   * Throttle flag for scroll performance.
   *
   * @type {boolean}
   */
  let ticking = false;

  /**
   * Check if user prefers reduced motion.
   *
   * @returns {boolean} True if user prefers reduced motion.
   */
  function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  /**
   * Check if parallax should be active based on breakpoint.
   *
   * @param {HTMLElement} element - The parallax section element.
   * @returns {boolean} True if parallax should run.
   */
  function shouldRunParallax(element) {
    // Respect reduced motion preference
    if (prefersReducedMotion()) {
      return false;
    }

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

    return true;
  }

  /**
   * Check if element is in viewport.
   *
   * @param {HTMLElement} element - The element to check.
   * @returns {boolean} True if element is in viewport.
   */
  function isInViewport(element) {
    const rect = element.getBoundingClientRect();
    const windowHeight = window.innerHeight || document.documentElement.clientHeight;

    return rect.bottom > 0 && rect.top < windowHeight;
  }

  /**
   * Update visibility class on element.
   *
   * @param {HTMLElement} element - The parallax section element.
   */
  function updateVisibility(element) {
    const inner = element.querySelector('.paragraph__inner');
    if (!inner) {
      return;
    }

    if (isInViewport(element)) {
      inner.classList.add('visible');
    } else {
      inner.classList.remove('visible');
    }
  }

  /**
   * Apply parallax effect and update visibility.
   *
   * @param {HTMLElement} element - The parallax section element.
   */
  function updateParallax(element) {
    // Always update visibility for text animations
    updateVisibility(element);

    // Handle legacy CSS mode - no JS transforms needed
    if (element.classList.contains('parallax-css')) {
      return;
    }

    // For JS mode, check if breakpoint and motion preferences allow parallax
    if (!shouldRunParallax(element)) {
      return;
    }

    const bgEl = element.querySelector('.pb__parallax-bg');
    if (!bgEl) {
      return;
    }

    // Only apply background transforms if element is in viewport
    if (!isInViewport(element)) {
      return;
    }

    const speed = parseFloat(element.getAttribute('data-parallax-speed')) || 0.6;
    const effect = element.getAttribute('data-parallax-effect') || 'translate';

    const scrollPosition = window.pageYOffset;
    const windowHeight = window.innerHeight;
    const elementRect = element.getBoundingClientRect();
    const elementTop = elementRect.top + scrollPosition;

    const offset = ((scrollPosition + windowHeight - elementTop) * speed) - (windowHeight * speed);

    // Reset previous transforms
    bgEl.style.transform = '';
    bgEl.style.filter = '';
    bgEl.style.opacity = '';

    switch (effect) {
      case 'translate':
        bgEl.style.transform = `translateY(${offset}px)`;
        break;

      case 'blur': {
        const blurAmount = Math.min(Math.abs(offset) / 100, 10);
        bgEl.style.filter = `blur(${blurAmount}px)`;
        break;
      }

      case 'fade':
      case 'opacity': {
        const opacity = Math.max(1 - Math.abs(offset) / 1000, 0);
        bgEl.style.opacity = opacity;
        break;
      }

      case 'scale': {
        const scaleAmount = Math.max(1 + Math.abs(offset) / 1000, 1);
        bgEl.style.transform = `scale(${scaleAmount})`;
        break;
      }

      case 'rotate': {
        const rotateAmount = Math.max(-8, Math.min(8, offset / 10));
        bgEl.style.transform = `rotate(${rotateAmount}deg)`;
        break;
      }

      case 'legacy':
        // Legacy mode uses CSS only, no JS transform
        break;

      default:
        bgEl.style.transform = `translateY(${offset}px)`;
        break;
    }
  }

  /**
   * Process all parallax elements.
   */
  function processAllElements() {
    const elements = document.querySelectorAll('.paragraph--type--parallax-section-bundle');
    elements.forEach(element => {
      updateParallax(element);
    });
  }

  /**
   * Handle scroll events with throttling via requestAnimationFrame.
   */
  function handleScroll() {
    if (!ticking) {
      window.requestAnimationFrame(() => {
        processAllElements();
        ticking = false;
      });
      ticking = true;
    }
  }

  /**
   * Handle resize events with throttling.
   */
  function handleResize() {
    handleScroll();
  }

  /**
   * Drupal behavior for Paragraph Bundle Parallax.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.ParagraphBundleParallax = {
    attach: function(context, settings) {
      const elements = once('paragraphBundleParallax', '.paragraph--type--parallax-section-bundle', context);

      if (elements.length > 0) {
        // Bind scroll and resize listeners only once globally
        if (!listenersAttached) {
          window.addEventListener('scroll', handleScroll, { passive: true });
          window.addEventListener('resize', handleResize, { passive: true });
          listenersAttached = true;
        }

        // Initial call to set up visibility states
        processAllElements();
      }
    },

    detach: function(context, settings, trigger) {
      // Only clean up on 'unload' trigger to prevent issues with AJAX
      if (trigger === 'unload') {
        once.remove('paragraphBundleParallax', '.paragraph--type--parallax-section-bundle', context);

        // Check if any parallax elements remain on the page
        const remainingElements = document.querySelectorAll('.paragraph--type--parallax-section-bundle');
        if (remainingElements.length === 0 && listenersAttached) {
          window.removeEventListener('scroll', handleScroll);
          window.removeEventListener('resize', handleResize);
          listenersAttached = false;
        }
      }
    }
  };

})(Drupal, drupalSettings, once);
