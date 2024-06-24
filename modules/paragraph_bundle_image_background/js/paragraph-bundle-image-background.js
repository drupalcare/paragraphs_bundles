/**
 * @file
 * Paragraph Bundle Image Background.
 *
 * Filename:     paragraph-bundle-image-background.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, once) => {
  'use strict';
  Drupal.behaviors.paragraphImageBackground = {
    attach: (context, settings) => {
      once('paragraphImageBackground', '.paragraph--type--image-background-bundle', context).forEach((element) => {
        function checkBreakpoint(paragraph) {
          var windowWidth = window.innerWidth;
          var breakpoint = parseInt(paragraph.dataset.breakpoint || 0, 10);
          var imageBig = paragraph.dataset.imageBig;
          var imageSmall = paragraph.dataset.imageSmall || imageBig; // Fallback to big screen image if small screen image is not provided

          if (windowWidth <= breakpoint && breakpoint !== 0) {
            paragraph.style.backgroundImage = 'url(' + imageSmall + ')';
          } else {
            paragraph.style.backgroundImage = 'url(' + imageBig + ')';
          }
        }

        // Initial check
        checkBreakpoint(element);

        // Check on window resize
        window.addEventListener('resize', () => checkBreakpoint(element));
      });
    }
  };
})(Drupal, once);
