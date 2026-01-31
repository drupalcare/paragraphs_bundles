/**
 * @file
 * Lightweight slide helpers for smooth animations without jQuery.
 *
 * Provides reusable slideUp and slideDown utilities for accordion animations.
 *
 * Filename:     paragraph-bundle-accordion-utils.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com
 */

((Drupal) => {
  'use strict';

  /**
   * Initialize the accordion namespace if it doesn't exist.
   *
   * @namespace Drupal.paragraphBundleAccordion
   */
  Drupal.paragraphBundleAccordion = Drupal.paragraphBundleAccordion || {};

  /**
   * CSS styles applied during slide animations to collapse elements.
   *
   * @type {Object<string, string>}
   */
  const cssStyles = {
    overflow: 'hidden',
    height: '0',
    paddingTop: '0',
    paddingBottom: '0',
    marginTop: '0',
    marginBottom: '0'
  };

  /**
   * Removes transition-related inline styles from an element.
   *
   * @param {HTMLElement} target - The element to clean up.
   */
  const removeStyles = (target) => {
    const styles = [
      'height',
      'paddingTop',
      'paddingBottom',
      'marginTop',
      'marginBottom',
      'overflow',
      'transitionDuration',
      'transitionProperty'
    ];
    styles.forEach(style => target.style.removeProperty(style));
  };

  /**
   * Animates an element to collapse (slide up) and hides it.
   *
   * @param {HTMLElement} target - The element to collapse.
   * @param {number} [duration=300] - Animation duration in milliseconds.
   */
  const slideUp = (target, duration = 300) => {
    if (!target) {
      return;
    }

    target.style.transitionProperty = 'height, margin, padding';
    target.style.transitionDuration = `${duration}ms`;
    target.style.transitionTimingFunction = 'ease-in-out';
    target.style.boxSizing = 'border-box';
    target.style.height = `${target.offsetHeight}px`;
    target.offsetHeight; // Force reflow

    target.setAttribute('aria-hidden', 'true');

    Object.keys(cssStyles).forEach(style => {
      target.style[style] = cssStyles[style];
    });

    setTimeout(() => {
      target.style.display = 'none';
      removeStyles(target);
    }, duration);
  };

  /**
   * Animates an element to expand (slide down) and shows it.
   *
   * @param {HTMLElement} target - The element to expand.
   * @param {string} [display='block'] - The display value to use when showing.
   * @param {number} [duration=300] - Animation duration in milliseconds.
   */
  const slideDown = (target, display = 'block', duration = 300) => {
    if (!target) {
      return;
    }

    target.style.removeProperty('display');
    let computedDisplay = window.getComputedStyle(target).display;
    if (computedDisplay === 'none') {
      computedDisplay = display;
    }
    target.style.display = computedDisplay;

    const height = target.offsetHeight;
    Object.keys(cssStyles).forEach(style => {
      target.style[style] = cssStyles[style];
    });

    target.offsetHeight; // Force reflow
    target.style.transitionProperty = 'height, margin, padding';
    target.style.transitionDuration = `${duration}ms`;
    target.style.transitionTimingFunction = 'ease-in-out';
    target.style.boxSizing = 'border-box';
    target.style.height = `${height}px`;

    target.setAttribute('aria-hidden', 'false');

    ['paddingTop', 'paddingBottom', 'marginTop', 'marginBottom'].forEach(prop => {
      target.style.removeProperty(prop);
    });

    setTimeout(() => {
      ['height', 'overflow', 'transitionDuration', 'transitionProperty'].forEach(prop => {
        target.style.removeProperty(prop);
      });
    }, duration);
  };

  // Export utilities to Drupal namespace
  Drupal.paragraphBundleAccordion.slideUp = slideUp;
  Drupal.paragraphBundleAccordion.slideDown = slideDown;

})(Drupal);
