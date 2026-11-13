/**
 * @param Drupal
 * @param once
 * @file
 * Pattern Portfolio: writes the total for the vertical counter.
 *
 * The counter's current number and its accessible name stay PB's own; the
 * script only writes the slide total into data-pb-pattern-total, which CSS
 * prints under a short rule, as Orbit dock does. PB's script is untouched.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */

((Drupal, once) => {
  Drupal.behaviors.pbPattern3dCarouselPortfolio = {
    attach(context) {
      once(
        'pb-pattern-3d-carousel-portfolio',
        '.pb-pattern--3d-carousel-portfolio',
        context,
      ).forEach((root) => {
        const ring = root.querySelector('.pb-3d-carousel__ring');
        const number = root.querySelector('.pb-slideshow__number');
        if (!ring || !number) {
          return;
        }
        const slides = ring.querySelectorAll(':scope > .pb-3d-carousel__slide');
        number.setAttribute('data-pb-pattern-total', String(slides.length));
      });
    },
    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once
        .remove(
          'pb-pattern-3d-carousel-portfolio',
          '.pb-pattern--3d-carousel-portfolio',
          context,
        )
        .forEach((root) => {
          root
            .querySelector('.pb-slideshow__number')
            ?.removeAttribute('data-pb-pattern-total');
        });
    },
  };
})(Drupal, once);
