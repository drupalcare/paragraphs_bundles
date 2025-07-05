/**
 * @file
 * Lightweight slide helpers for smooth animations without jQuery.
 *
 * Provides reusable slideUp and slideDown utilities.
 *
 * Filename:     paragraph-bundle-accordion-utils.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com
 */

((Drupal) => {
  'use strict';

  Drupal.paragraphBundleAccordion = Drupal.paragraphBundleAccordion || {};

  const cssStyles = {
    overflow: 'hidden',
    height: '0',
    paddingTop: '0',
    paddingBottom: '0',
    marginTop: '0',
    marginBottom: '0'
  };

  const removeStyles = (target) => {
    const styles = ['height', 'paddingTop', 'paddingBottom', 'marginTop', 'marginBottom', 'overflow', 'transitionDuration', 'transitionProperty'];
    styles.forEach(style => target.style.removeProperty(style));
  };

  const slideUp = (target, duration = 300) => {
    target.style.transitionProperty = 'height, margin, padding';
    target.style.transitionDuration = `${duration}ms`;
    target.style.transitionTimingFunction = 'ease-in-out';
    target.style.boxSizing = 'border-box';
    target.style.height = `${target.offsetHeight}px`;
    target.offsetHeight; // force reflow

    target.setAttribute('aria-hidden', 'true');

    Object.keys(cssStyles).forEach(style => {
      target.style[style] = cssStyles[style];
    });

    setTimeout(() => {
      target.style.display = 'none';
      removeStyles(target);
    }, duration);
  };

  const slideDown = (target, display = 'block', duration = 300) => {
    target.style.removeProperty('display');
    let computedDisplay = window.getComputedStyle(target).display;
    if (computedDisplay === 'none') computedDisplay = display;
    target.style.display = computedDisplay;

    const height = target.offsetHeight;
    Object.keys(cssStyles).forEach(style => {
      target.style[style] = cssStyles[style];
    });

    target.offsetHeight; // force reflow
    target.style.transitionProperty = 'height, margin, padding';
    target.style.transitionDuration = `${duration}ms`;
    target.style.transitionTimingFunction = 'ease-in-out';
    target.style.boxSizing = 'border-box';
    target.style.height = `${height}px`;

    target.setAttribute('aria-hidden', 'false');

    ['paddingTop', 'paddingBottom', 'marginTop', 'marginBottom'].forEach(prop => target.style.removeProperty(prop));

    setTimeout(() => {
      ['height', 'overflow', 'transitionDuration', 'transitionProperty'].forEach(prop => target.style.removeProperty(prop));
    }, duration);
  };

  Drupal.paragraphBundleAccordion.slideUp = slideUp;
  Drupal.paragraphBundleAccordion.slideDown = slideDown;

})(Drupal);
