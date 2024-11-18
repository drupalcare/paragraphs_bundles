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
      let autoSlideIntervalId = null;
      let isPaused = false;

      const manageAutoSlideInterval = (action, parentId, slideTime) => {
        if (action === 'clear') {
          if (autoSlideIntervalId) {
            clearInterval(autoSlideIntervalId);
            autoSlideIntervalId = null;
          }
        } else if (action === 'start') {
          if (!autoSlideIntervalId && slideTime > 0) {
            autoSlideIntervalId = setInterval(() => autoSlide(parentId, getNextItemIndex), slideTime);
          }
        }
      };

      const getNextItemIndex = (totalSlides) => {
        slideIndex = slideIndex % totalSlides + 1;
        return slideIndex;
      };

      const getPreviousItemIndex = (totalSlides) => {
        slideIndex = slideIndex === 1 ? totalSlides : slideIndex - 1;
        return slideIndex;
      };

      const getParentId = (element) => {
        return parseInt(element.split('-')[2]);
      };

      const getElementId = (element) => {
        return parseInt(element.split('-')[3]);
      };

      const getSlideIndex = (slides) => {
        for (let i = 0; i < slides.length; i++) {
          const slide = context.getElementById(slides[i].id);
          if (window.getComputedStyle(slide).display === 'block') {
            slideIndex = i + 1;
            break;
          }
        }
        return slideIndex;
      };

      const updateNavigationState = (parentId, activeSlideId) => {
        const allButtons = context.querySelectorAll(`#pb__slideshow-${parentId}>.pb__slide-nav>.pb__slide-bottom-btn`);
        allButtons.forEach(button => {
          const buttonId = getElementId(button.id);
          if (buttonId === activeSlideId) {
            button.classList.add('pb__active');
            button.removeAttribute('tabindex');
            button.setAttribute('aria-selected', 'true');
          } else {
            button.classList.remove('pb__active');
            button.setAttribute('tabindex', '-1');
            button.setAttribute('aria-selected', 'false');
          }
        });
      };

      const updateSlideVisibility = (parentId, activeSlideId) => {
        const slides = context.querySelectorAll(`#pb__slides-${parentId}>.pb__slide-item`);
        const slideshowContainer = document.querySelector(`#pb__slides-${parentId}`);

        let activeSlideHeight = 0; // Variable to store the active slide's height

        slides.forEach((slide, index) => {
          if (index + 1 === activeSlideId) {
            slide.style.display = 'block';
            slide.setAttribute('aria-hidden', 'false');
            slide.focus();
            activeSlideHeight = slide.offsetHeight; // Capture height of the active slide
          } else {
            slide.style.display = 'none';
            slide.setAttribute('aria-hidden', 'true');
          }
        });

        // Smooth height adjustment
        slideshowContainer.style.height = `${activeSlideHeight}px`;
      };

      const getNextSlide = (parentId, slideId) => {
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
        manageAutoSlideInterval('clear');
        const slides = context.querySelectorAll(`#pb__slides-${parentId}>.pb__slide-item`);
        const totalSlides = slides.length;
        const slideId = itemFunction(totalSlides);

        updateSlideVisibility(parentId, slideId);
        updateNavigationState(parentId, slideId);

        if (!isPaused) {
          let slideTime = parseInt(context.querySelector(`#pb__slideshow-${parentId}>.pb__ps-value`).textContent);
          manageAutoSlideInterval('start', parentId, slideTime);
        }
      };

      const handleBottomNav = (e, p) => {
        const slideId = getElementId(e);
        const parentId = getParentId(p);
        manageAutoSlideInterval('clear');

        const slides = context.querySelectorAll(`#pb__slides-${parentId}>.pb__slide-item`);
        slides.forEach(slide => {
          if (parseInt(slide.id.split('-').pop()) === slideId) {
            slide.style.display = 'block';
            slide.setAttribute('aria-hidden', 'false');
            slide.focus();
          } else {
            slide.style.display = 'none';
            slide.setAttribute('aria-hidden', 'true');
          }
        });

        updateNavigationState(parentId, slideId);
        slideIndex = slideId;

        if (!isPaused) {
          const slideTime = parseInt(context.querySelector(`#pb__slideshow-${parentId}>.pb__ps-value`).textContent);
          manageAutoSlideInterval('start', parentId, slideTime);
        }
      };

      const autoSlide = (parentId, itemFunction) => {
        const slides = context.querySelectorAll(`#pb__slides-${parentId}>.pb__slide-item`);
        getSlideIndex(slides);
        const totalSlides = slides.length;
        const slideId = itemFunction(totalSlides);

        slides.forEach(slide => {
          const currentSlideId = parseInt(slide.id.split('-').pop());
          if (currentSlideId === slideId) {
            slide.style.display = 'block';
            slide.setAttribute('aria-hidden', 'false');
          } else {
            slide.style.display = 'none';
            slide.setAttribute('aria-hidden', 'true');
          }
        });

        updateNavigationState(parentId, slideId);
        updateSlideVisibility(parentId, slideId); // Ensure dynamic height adjustment
      };

      once('init-slides', slides).forEach(slide => {
        let slideId = slide.id;
        let parentId = parseInt(slide.id.split('-')[1]);
        let slideTime = parseInt(context.querySelector(`#pb__slideshow-${parentId}>.pb__ps-value`).textContent);

        updateSlideVisibility(parentId, slideIndex); // Initial height adjustment
        manageAutoSlideInterval('start', parentId, slideTime);

        if (slideTime != 0) {
          let stopOnHover = context.getElementById(slideId);
          let playPause = context.getElementById(`btn-${parentId}`);
          let btnClasses = playPause.classList;
          btnClasses.add('pb__no-bottom-nav');

          const togglePlayPause = () => {
            isPaused = !isPaused;
            if (isPaused) {
              playPause.classList.replace('pb__play', 'pb__pause');
              playPause.innerHTML = '&#9654;';
              playPause.setAttribute('aria-label', 'Start automatic slide show');
              manageAutoSlideInterval('clear');
            } else {
              playPause.classList.replace('pb__pause', 'pb__play');
              playPause.innerHTML = '&#10073;&nbsp;&#10073;';
              playPause.setAttribute('aria-label', 'Stop automatic slide show');
              manageAutoSlideInterval('start', parentId, slideTime);
            }
          };

          stopOnHover.addEventListener('mouseover', () => {
            if (!isPaused) {
              manageAutoSlideInterval('clear');
            }
          });

          stopOnHover.addEventListener('mouseout', () => {
            if (!isPaused) {
              manageAutoSlideInterval('start', parentId, slideTime);
            }
          });

          playPause.addEventListener('click', togglePlayPause);
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

      once('init-bottom-nav', '.pb__slideshow-inner .pb__slide-nav .pb__slide-bottom-btn', context).forEach(element => {
        element.addEventListener('click', function(event) {
          handleBottomNav(event.target.id, event.target.parentElement.id);
        });

        element.addEventListener('keydown', function(event) {
          if (event.key === 'Tab') {
            const parentSlideshow = element.closest('.pb__slideshow-inner');
            const allButtons = Array.from(parentSlideshow.querySelectorAll('.pb__slide-nav .pb__slide-bottom-btn'));
            const currentIndex = allButtons.indexOf(event.target);

            if (event.shiftKey) {
              if (currentIndex > 0) {
                allButtons[currentIndex - 1].focus();
                event.preventDefault();
              }
            } else {
              if (currentIndex < allButtons.length - 1) {
                allButtons[currentIndex + 1].focus();
                event.preventDefault();
              }
            }
          }
        });
      });
    }
  };
})(Drupal, drupalSettings, once);
