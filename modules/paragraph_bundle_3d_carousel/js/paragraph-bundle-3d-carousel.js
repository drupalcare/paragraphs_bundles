/**
 * @file
 * Paragraph Bundle 3D Carousle
 *
 * Filename:     paragraph-bundle-3d-carousel.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, drupalSettings, once) => {
  'use strict';

  Drupal.behaviors.paragraphBundleCarousel = {
    attach: function(context, settings) {
      let intervalIds = new Map();
      const carousels = once('paragraphBundleCarousel', '.pb__3d-carousel', context);
      if (!carousels.length) {
        return;
      }

      let currentWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
      let autoHelper = [];
      let n = 0;
      let isPaused = false;

      // Utility function to update tabindex based on parent element's attributes
      const updateTabIndexForElements = (parentElement) => {
        const focusableElements = parentElement.querySelectorAll('button, a');
        focusableElements.forEach(el => {
          if (parentElement.getAttribute('aria-hidden') === 'true') {
            el.setAttribute('tabindex', '-1');
          } else if (parentElement.getAttribute('tabindex') === '0') {
            el.setAttribute('tabindex', '0');
          } else {
            el.removeAttribute('tabindex');
          }
        });
      };

      // Centralized Interval Management
      const manageAutoSlideInterval = (carouselId, action, cTime) => {
        let intervalId = intervalIds.get(carouselId);
        if (action === 'clear' && intervalId) {
          clearInterval(intervalId);
          intervalIds.delete(carouselId);
        }
        else if (action === 'start' && cTime > 0 && !isPaused) {
          intervalId = setInterval(() => autoPlay(carouselId), cTime);
          intervalIds.set(carouselId, intervalId);
        }
      };

      const getCarInfo = (carouselId) => {
        const carousel = context.getElementById(carouselId);
        const cells = carousel.querySelectorAll(`#${carouselId}>.pb__caro-item`);
        const cellCount = cells.length;
        const cellWidth = carousel.offsetWidth;
        const theta = 360 / cellCount;
        const radius = Math.round(cellWidth / 2 / Math.tan(Math.PI / cellCount));
        const rawNo = carouselId.split('-')[2];
        return [carousel, cells, cellCount, cellWidth, theta, radius, rawNo];
      };

      const getThisId = th => `pb__3d-carousel-${parseInt(th.slice(5))}`;

      let rotateCarousel = (carouselId, n) => {
        let [carousel, cells, cellCount, , theta, radius] = getCarInfo(carouselId);
        let angle = theta * n * -1;
        carousel.style.transition = 'transform 0.8s ease-in-out';
        carousel.style.transform = `translateZ(${-radius}px) rotateY(${angle}deg)`;

        // Ensure index wraps correctly
        if (n >= cellCount) n = 0;
        if (n < 0) n = cellCount - 1;

        // Update aria-hidden and tabindex for each cell
        cells.forEach((cell, index) => {
          if (index === n) {
            cell.setAttribute('aria-hidden', 'false');
            cell.setAttribute('tabindex', '0');
          } else {
            cell.setAttribute('aria-hidden', 'true');
            cell.setAttribute('tabindex', '-1');
          }

          // Update tabindex for children
          updateTabIndexForElements(cell);
        });

        // Update the aria-label for the current slide number
        const slideNumberElement = context.querySelector(`#index-${carouselId.split('-')[2]}`);
        if (slideNumberElement) {
          slideNumberElement.setAttribute('aria-label', `Slide ${n + 1} of ${cellCount}`);
        }
      };

      let buildCarousel = (carouselId) => {
        let [_, cells, cellCount, , theta, radius, rawNo] = getCarInfo(carouselId);
        let indexNo = context.getElementById('index-' + rawNo);
        let prevBtnId = context.getElementById('prev-' + rawNo);
        let nextBtnId = context.getElementById('next-' + rawNo);
        for (let i = 0; i < cellCount; i++) {
          cells[i].style.opacity = i < cellCount ? 1 : 0;
          cells[i].style.transform = i < cellCount ? `rotateY(${theta * i}deg) translateZ(${radius}px)` : "none";
        }
        cells[0].classList.add('pb__active');
        indexNo.innerHTML = 1;
        nextBtnId.classList.remove('hidden');
        nextBtnId.removeAttribute('tabindex');
        prevBtnId.classList.add('hidden');
        prevBtnId.setAttribute('tabindex', '-1');
        rotateCarousel(carouselId, 0);
      };

      const backward = (carouselId) => {
        const cells = context.querySelectorAll(`#${carouselId}>.pb__caro-item`);
        const cellCount = cells.length;
        const btnId = carouselId.split('-')[2];
        const prevBtnId = context.getElementById(`prev-${btnId}`);
        const nextBtnId = context.getElementById(`next-${btnId}`);
        const indexNo = context.getElementById(`index-${btnId}`);
        for (let i = 0; i < cellCount; i++) {
          if (!cells[i].classList.contains('pb__active')) continue;
          if (i === 0) {
            const prevOn = parseInt(`${btnId}00`);
            const prevOff = parseInt(`${btnId}01`);
            const nextOn = parseInt(`${btnId}11`);
            const nextOff = parseInt(`${btnId}10`);
            if (autoHelper.includes(prevOn)) {
              autoHelper = autoHelper.map((item) => {
                if (item === prevOn) return prevOff;
                if (item === nextOff) return nextOn;
                return item;
              });
            }
            break;
          }
          nextBtnId.classList.remove('hidden');
          nextBtnId.removeAttribute('tabindex');
          cells[i].classList.remove('pb__active');
          i -= 1;
          if (!cells[i]) continue;
          if (i === 0) {
            prevBtnId.classList.add('hidden');
            prevBtnId.setAttribute('tabindex', '-1');
            nextBtnId.classList.remove('hidden');
          }
          cells[i].classList.add('pb__active');
          indexNo.innerHTML = i + 1;
          return i;
        }
      };

      let forward = (carouselId) => {
        let cells = context.querySelectorAll(`#${carouselId}>.pb__caro-item`);
        let cellCount = cells.length;
        let btnId = carouselId.split('-')[2];
        let prevBtnId = context.getElementById(`prev-${btnId}`);
        let nextBtnId = context.getElementById(`next-${btnId}`);
        let indexNo = context.getElementById(`index-${btnId}`);
        for (let i = 0; i < cellCount; i++) {
          if (cells[i].classList.contains('pb__active')) {
            if (i == cellCount - 1) {
              let prevOn = parseInt(`${btnId}00`);
              let prevOff = parseInt(`${btnId}01`);
              let nextOn = parseInt(`${btnId}11`);
              let nextOff = parseInt(`${btnId}10`);
              if (autoHelper.includes(nextOn)) {
                autoHelper = autoHelper.map(item => {
                  if (item == nextOn) {
                    return nextOff;
                  }
                  else if (item == prevOff) {
                    return prevOn;
                  }
                  else {
                    return item;
                  }
                });
              }
              break;
            }
            prevBtnId.classList.remove('hidden');
            prevBtnId.removeAttribute('tabindex');
            cells[i].classList.remove('pb__active');
            i++;
            if (cells[i]) {
              if (i == cellCount - 1) {
                nextBtnId.classList.add('hidden');
                nextBtnId.setAttribute('tabindex', '-1');
                prevBtnId.classList.remove('hidden');
              }
              cells[i].classList.add('pb__active');
              n = i;
              indexNo.innerHTML = n + 1;
              return n;
            }
          }
        }
      };

      let getNumberId = (th) => {
        let idNu = th.split('-')[2];
        parseInt(idNu);
        return idNu;
      };

      let navigateCarousel = (carouselId, directionFunction) => {
        let n = directionFunction(carouselId);
        rotateCarousel(carouselId, n);
      };

      let nextBtn = (carouselId) => {
        let cTimeId = carouselId.split('-')[2];
        let cTime = parseInt(context.querySelector(`#co-${cTimeId} > .pb__ps-value`).textContent);
        navigateCarousel(carouselId, forward);
        manageAutoSlideInterval(carouselId, 'clear');
        if (!isPaused) {
          manageAutoSlideInterval(carouselId, 'start', cTime);
        }
      };

      let prevBtn = (carouselId) => {
        let cTimeId = carouselId.split('-')[2];
        let cTime = parseInt(context.querySelector(`#co-${cTimeId} > .pb__ps-value`).textContent);
        navigateCarousel(carouselId, backward);
        manageAutoSlideInterval(carouselId, 'clear');
        if (!isPaused) {
          manageAutoSlideInterval(carouselId, 'start', cTime);
        }
      };

      let autoPlay = (carouselId) => {
        let d = getNumberId(carouselId);
        let prevOn = parseInt(d + 0 + 0);
        let nextOn = parseInt(d + 1 + 1);
        if ((autoHelper.indexOf(nextOn) >= 0)) {
          n = forward(carouselId);
          rotateCarousel(carouselId, n);
        }
        if ((autoHelper.indexOf(prevOn) >= 0)) {
          n = backward(carouselId);
          rotateCarousel(carouselId, n);
        }
      };

      let initCarousel = (carousels) => {
        autoHelper = [];
        for (let j = 0; j < carousels.length; j++) {
          let carouselC = carousels[j];
          let carouselId = carouselC.id;
          let shortId = carouselId.split('-')[2];
          autoHelper.push(parseInt(shortId + 1 + 1));
          autoHelper.push(parseInt(shortId + 0 + 1));
          buildCarousel(carouselId);
        }
      };

      let setAutoPlayInterval = (carouselId, cTime) => {
        manageAutoSlideInterval(carouselId, 'clear');
        if (cTime > 0) {
          let intervalId = setInterval(() => {
            autoPlay(carouselId);
          }, cTime);
          intervalIds.set(carouselId, intervalId);
          return intervalId;
        }
      };

      carousels.forEach(carouselC => {
        let carouselId = carouselC.id;
        let cTimeId = carouselId.split('-')[2];
        let cTime = parseInt(context.querySelector(`#co-${cTimeId} > .pb__ps-value`).textContent);

        cTime = parseInt(cTime);
        if (cTime != 0) {
          let intervalId = setAutoPlayInterval(carouselId, cTime); // Create and store interval

          let stopOnHover = context.getElementById(carouselId);
          let playPause = context.getElementById(`btn-${cTimeId}`);
          playPause.style.display = 'inline';

          stopOnHover.addEventListener('mouseover', function() {
            if (playPause.classList.contains('pb__play')) {
              clearInterval(intervalIds.get(carouselId)); // Use interval from the Map
            }
          });

          stopOnHover.addEventListener('mouseout', function() {
            if (playPause.classList.contains('pb__play')) {
              intervalIds.set(carouselId, setAutoPlayInterval(carouselId, cTime)); // Reset the interval
            }
          });

          playPause.addEventListener('click', function() {
            if (this.classList.contains('pb__play')) {
              this.classList.replace('pb__play', 'pb__pause');
              this.innerHTML = '&#9654;';
              isPaused = true;
              manageAutoSlideInterval(carouselId, 'clear');
            }
            else {
              this.classList.replace('pb__pause', 'pb__play');
              this.innerHTML = '&#10073;&nbsp;&#10073;';
              isPaused = false;
              manageAutoSlideInterval(carouselId, 'start', cTime);
            }
          });

        }

        // Add keyboard navigation support
        carouselC.addEventListener('keydown', (event) => {
          if (event.key === 'ArrowRight') {
            nextBtn(carouselId);
          } else if (event.key === 'ArrowLeft') {
            prevBtn(carouselId);
          }
        });

      });

      const handleWindowResize = () => {
        n = 0;
        let newWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
        if (Math.abs(newWidth - currentWidth) >= 50) {
          initCarousel(carousels);
          currentWidth = newWidth;
        }
      };

      once('init-pb__next', '.pb__caro-options .pb__next', context)
        .forEach(element => {
          element.addEventListener('click', function(event) {
            nextBtn(getThisId(event.target.id));
          });
        });

      once('init-pb__prev', '.pb__caro-options .pb__prev', context)
        .forEach(element => {
          element.addEventListener('click', function(event) {
            prevBtn(getThisId(event.target.id));
          });
        });

      window.addEventListener('resize', handleWindowResize);
      initCarousel(carousels);

    }
  };
})(Drupal, drupalSettings, once);
