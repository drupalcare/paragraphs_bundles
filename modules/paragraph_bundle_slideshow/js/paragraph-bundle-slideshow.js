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
    attach: function(context, settings) {

        const slides = once('paragraphSlideshowBundle', '.pb__slides', context);
        if (!slides.length) {
          return;
        }

      let slideIndex = 1;

      const getNextItemIndex = (totalSlides) => {
        slideIndex = slideIndex % totalSlides + 1;
        return slideIndex;
      };

      let getPreviousItemIndex = (totalSlides) => {
        slideIndex = slideIndex === 1 ? totalSlides : slideIndex - 1;
        return slideIndex;
      };

      let getParentId = (element) => {
        return parseInt(element.split('-')[2]);
      };

      let getElementId = (element) => {
        return parseInt(element.split('-')[3]);
      };

      const getSlideIndex = (slides) => {
        for (let i = 0; i < slides.length; i++) {
          const slide = context.getElementById(slides[i].id);
          if (window.getComputedStyle(slide)
            .display === 'block') {
            slideIndex = i + 1;
            break;
          }
        }
        return slideIndex;
      };

      let getNextSlide = (parentId, slideId) => {
        const slidesSelector = `#pb__slides-${parentId}>.pb__slide-item`;
        const currentIdSelector = `#pb__slides-${parentId}>#pb__slide-item-${parentId}-${slideId}`;
        const activeButtonSelector = `#pb__slideshow-${parentId}>.pb__slide-nav>#pb__slide-bottom-btn-${slideId}`;
        const allActiveClassesSelector = `#pb__slideshow-${parentId}>.pb__slide-nav>.pb__slide-bottom-btn`;

        let slides = context.querySelectorAll(slidesSelector);
        let selectedPane = context.querySelector(currentIdSelector);
        let activeButton = context.querySelector(activeButtonSelector);
        let allActiveClasses = context.querySelectorAll(allActiveClassesSelector);

        allActiveClasses.forEach(element => {
          element.classList.remove('pb__active');
        });

        slides.forEach(element => {
          element.style.display = 'none';
        });

        return [selectedPane, activeButton];
      };

      const handlePrevNextBtn = (element, itemFunction) => {
        const parentId = getParentId(element);
        const slides = context.querySelectorAll(`#pb__slides-${parentId}>.pb__slide-item`);
        const totalSlides = slides.length;
        const slideId = itemFunction(totalSlides);
        const [nextSlide, isActive] = getNextSlide(parentId, slideId);

        nextSlide.style.display = 'block';
        isActive.classList.add('pb__active');
      };

      let handleBottomNav = (e, p) => {
        const slideId = getElementId(e);
        const parentId = getParentId(p);
        const slideAndActiveState = getNextSlide(parentId, slideId);

        if (slideAndActiveState && slideAndActiveState.length >= 2) {
          slideAndActiveState[0].style.display = 'block';
          slideAndActiveState[1].classList.add('pb__active');
          slideIndex = slideId;
        } else {
          console.error('getNextSlide did not return an array with at least two elements');
        }
      };

      const autoSlide = (parentId, itemFunction) => {
        const slides = context.querySelectorAll(`#pb__slides-${parentId}>.pb__slide-item`);
        getSlideIndex(slides);
        const totalSlides = slides.length;
        const slideId = itemFunction(totalSlides);
        const [nextSlide, activeSlide] = getNextSlide(parentId, slideId);

        nextSlide.style.display = 'block';
        activeSlide.classList.add('pb__active');
      };

      once('init-slides', slides).forEach(slide => {
        let slideId = slide.id;
        console.log(slideId);
        let parentId = parseInt(slide.id.split('-')[1]);
        let intervalId = slide.id.replaceAll('-', '');
        let slideTime = parseInt(context.querySelector(`#pb__slideshow-${parentId}>.pb__ps-value`)
          .textContent);

        if (slideTime != 0) {
          let stopOnHover = context.getElementById(slideId);
          let playPause = context.getElementById(`btn-${parentId}`);
          let btnClasses = playPause.classList;

          btnClasses.add('pb__no-bottom-nav');

          const autoSlideInterval = () => setInterval(() => autoSlide(parentId, getNextItemIndex), slideTime);

          intervalId = autoSlideInterval();

          const clearAndSetInterval = () => {
            if (playPause.classList.contains('pb__play')) {
              clearInterval(intervalId);
              intervalId = autoSlideInterval();
            }
          };

          stopOnHover.addEventListener('mouseover', () => {
            if (playPause.classList.contains('pb__play')) {
              clearInterval(intervalId);
            }
          });

          stopOnHover.addEventListener('mouseout', clearAndSetInterval);

          playPause.addEventListener('click', function() {
            if (this.classList.contains('pb__play')) {
              this.classList.replace('pb__play', 'pb__pause');
              this.innerHTML = '&#9654;';
              clearInterval(intervalId);
            } else {
              this.classList.replace('pb__pause', 'pb__play');
              this.innerHTML = '&#10073;&nbsp;&#10073;';
              intervalId = autoSlideInterval();
            }
          });
        }

      });

      once('init-pb__next', '.pb__slideshow-inner .pb__next', context).forEach(element => {
        element.addEventListener('click', function(event) {
          handlePrevNextBtn(event.target.parentElement.id, getNextItemIndex);
        });
      });

      once('init-pb__prev', '.pb__slideshow-inner .pb__prev', context).forEach(element => {
        element.addEventListener('click', function(event) {
          handlePrevNextBtn(event.target.parentElement.id, getPreviousItemIndex);
        });
      });

      once('init-bottom-nav', '.pb__slideshow-inner .pb__slide-bottom-btn', context).forEach(element => {
        element.addEventListener('click', function(event) {
          handleBottomNav(event.target.id, event.target.parentElement.id);
        });
      });

    }
  };
})(Drupal, drupalSettings, once);
