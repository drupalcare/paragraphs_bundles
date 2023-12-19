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



      let slideIndex = 1;
      let slides = context.querySelectorAll('.pb-slides');

      const getNextItemIndex = (totalSlides) => {
        slideIndex = slideIndex % totalSlides + 1;
        return slideIndex;
      };

      let getPreviousItemIndex = (totalSlides) => {
        slideIndex = slideIndex === 1 ? totalSlides : slideIndex - 1;
        return slideIndex;
      };

      let getParentId = (element) => {
        return parseInt(element.split('-')[3]);
      };

      let getElementId = (element) => {
        return parseInt(element.split('-')[2]);
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
        const slidesSelector = `#pb-slides-${parentId}>.pb-s-wr`;
        const currentIdSelector = `#pb-slides-${parentId}>#pb-s-wr-${parentId}-${slideId}`;
        const activeButtonSelector = `#pb-slideshow-${parentId}>.pb-s-nav>#b-nav-${slideId}`;
        const allActiveClassesSelector = `#pb-slideshow-${parentId}>.pb-s-nav>.b-nav`;

        let slides = context.querySelectorAll(slidesSelector);
        let selectedPane = context.querySelector(currentIdSelector);
        let activeButton = context.querySelector(activeButtonSelector);
        let allActiveClasses = context.querySelectorAll(allActiveClassesSelector);

        allActiveClasses.forEach(element => {
          element.classList.remove('pb-is-active');
        });

        slides.forEach(element => {
          element.style.display = 'none';
        });

        return [selectedPane, activeButton];
      };

      const handlePrevNextBtn = (element, itemFunction) => {
        const parentId = getParentId(element);
        const slides = context.querySelectorAll(`#pb-slides-${parentId}>.pb-s-wr`);
        const totalSlides = slides.length;
        const slideId = itemFunction(totalSlides);
        const [nextSlide, isActive] = getNextSlide(parentId, slideId);

        nextSlide.style.display = 'block';
        isActive.classList.add('pb-is-active');
      };

      let handleBottomNav = (e, p) => {
        const slideId = getElementId(e);
        const parentId = getParentId(p);
        const slideAndActiveState = getNextSlide(parentId, slideId);

        if (slideAndActiveState && slideAndActiveState.length >= 2) {
          slideAndActiveState[0].style.display = 'block';
          slideAndActiveState[1].classList.add('pb-is-active');
          slideIndex = slideId;
        } else {
          console.error('getNextSlide did not return an array with at least two elements');
        }
      };

      const autoSlide = (parentId, itemFunction) => {
        const slides = context.querySelectorAll(`#pb-slides-${parentId}>.pb-s-wr`);
        getSlideIndex(slides);
        const totalSlides = slides.length;
        const slideId = itemFunction(totalSlides);
        const [nextSlide, activeSlide] = getNextSlide(parentId, slideId);

        nextSlide.style.display = 'block';
        activeSlide.classList.add('pb-is-active');
      };

      once('init-slides', slides).forEach(slide => {
        let slideId = slide.id;
        let parentId = parseInt(slide.id.split('-')[2]);
        let intervalId = slide.id.replaceAll('-', '');
        let slideTime = parseInt(context.querySelector(`#pb-slideshow-${parentId}>.play-stop-value`)
          .textContent);

        if (slideTime != 0) {
          let stopOnHover = context.getElementById(slideId);
          let playPause = context.getElementById(`btn-${parentId}`);
          let btnClasses = playPause.classList;

          btnClasses.add('no-bottom-nav');

          const autoSlideInterval = () => setInterval(() => autoSlide(parentId, getNextItemIndex), slideTime);

          intervalId = autoSlideInterval();

          const clearAndSetInterval = () => {
            if (playPause.classList.contains('play-active-slideshow')) {
              clearInterval(intervalId);
              intervalId = autoSlideInterval();
            }
          };

          stopOnHover.addEventListener('mouseover', () => {
            if (playPause.classList.contains('play-active-slideshow')) {
              clearInterval(intervalId);
            }
          });

          stopOnHover.addEventListener('mouseout', clearAndSetInterval);

          playPause.addEventListener('click', function() {
            if (this.classList.contains('play-active-slideshow')) {
              this.classList.replace('play-active-slideshow', 'pause-active-slideshow');
              this.innerHTML = '&#9654;';
              clearInterval(intervalId);
            } else {
              this.classList.replace('pause-active-slideshow', 'play-active-slideshow');
              this.innerHTML = '&#10073;&nbsp;&#10073;';
              intervalId = autoSlideInterval();
            }
          });
        }

      });

      once('init-next-button', '.pb-slideshow-inner .pb-s-next', context).forEach(element => {
        element.addEventListener('click', function(event) {
          handlePrevNextBtn(event.target.parentElement.id, getNextItemIndex);
        });
      });

      once('init-prev-button', '.pb-slideshow-inner .pb-s-prev', context).forEach(element => {
        element.addEventListener('click', function(event) {
          handlePrevNextBtn(event.target.parentElement.id, getPreviousItemIndex);
        });
      });

      once('init-bottom-nav', '.pb-slideshow-inner .b-nav', context).forEach(element => {
        element.addEventListener('click', function(event) {
          handleBottomNav(event.target.id, event.target.parentElement.id);
        });
      });

    }
  };
})(Drupal, drupalSettings, once);
