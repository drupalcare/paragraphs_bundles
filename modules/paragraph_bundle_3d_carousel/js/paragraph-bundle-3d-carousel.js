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

      const carousels = once('paragraphBundleCarousel', '.pb-3d-carousel', context);
      if (!carousels.length) {
        return;
      }
      let currentWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
      let autoHelper = [];
      let carouselId;
      let n = 0;

      const getCarInfo = (carouselId) => {
        const carousel = context.getElementById(carouselId);
        const cells = carousel.querySelectorAll(`#${carouselId}>.pb-carousel-item`);
        const cellCount = cells.length;
        const cellWidth = carousel.offsetWidth;
        const theta = 360 / cellCount;
        const radius = Math.round(cellWidth / 2 / Math.tan(Math.PI / cellCount));
        const rawNo = carouselId.split('-')[3];
        return [carousel, cells, cellCount, cellWidth, theta, radius, rawNo];
      };
      const getThisId = th => `pb-3d-carousel-${parseInt(th.slice(5))}`;
      let rotateCarousel = (carouselId, n) => {
        let [carousel, cells, cellCount, , theta, radius] = getCarInfo(carouselId);
        let angle = theta * n * -1;
        carousel.style.transform = `translateZ(${-radius}px) rotateY(${angle}deg)`;
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
        cells[0].classList.add('pb-is-active');
        indexNo.innerHTML = 1;
        nextBtnId.style.display = 'inline';
        prevBtnId.style.display = 'none';
        rotateCarousel(carouselId, n);
      };
      const backward = (carouselId) => {
        const cells = context.querySelectorAll(`#${carouselId}>.pb-carousel-item`);
        const cellCount = cells.length;
        const btnId = carouselId.split('-')[3];
        const prevBtnId = context.getElementById(`prev-${btnId}`);
        const nextBtnId = context.getElementById(`next-${btnId}`);
        const indexNo = context.getElementById(`index-${btnId}`);
        for (let i = 0; i < cellCount; i++) {
          if (!cells[i].classList.contains('pb-is-active')) continue;
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
          nextBtnId.style.display = 'inline';
          cells[i].classList.remove('pb-is-active');
          i -= 1;
          if (!cells[i]) continue;
          if (i === 0) {
            prevBtnId.style.display = 'none';
            nextBtnId.style.display = 'inline';
          }
          cells[i].classList.add('pb-is-active');
          indexNo.innerHTML = i + 1;
          return i;
        }
      };
      let forward = (carouselId) => {
        let cells = context.querySelectorAll(`#${carouselId}>.pb-carousel-item`);
        let cellCount = cells.length;
        let btnId = carouselId.split('-')[3];
        let prevBtnId = context.getElementById(`prev-${btnId}`);
        let nextBtnId = context.getElementById(`next-${btnId}`);
        let indexNo = context.getElementById(`index-${btnId}`);
        for (let i = 0; i < cellCount; i++) {
          if (cells[i].classList.contains('pb-is-active')) {
            if (i == cellCount - 1) {
              let prevOn = parseInt(`${btnId}00`);
              let prevOff = parseInt(`${btnId}01`);
              let nextOn = parseInt(`${btnId}11`);
              let nextOff = parseInt(`${btnId}10`);
              if (autoHelper.includes(nextOn)) {
                autoHelper = autoHelper.map(item => {
                  if (item == nextOn) {
                    return nextOff;
                  } else if (item == prevOff) {
                    return prevOn;
                  } else {
                    return item;
                  }
                });
              }
              break;
            }
            prevBtnId.style.display = 'inline';
            cells[i].classList.remove('pb-is-active');
            i++;
            if (cells[i]) {
              if (i == cellCount - 1) {
                nextBtnId.style.display = 'none';
                prevBtnId.style.display = 'inline';
              }
              cells[i].classList.add('pb-is-active');
              n = i;
              indexNo.innerHTML = n + 1;
              return n;
            }
          }
        }
      };
      let getNumberId = (th) => {
        let idNu = th.split('-')[3];
        parseInt(idNu);
        return idNu;
      };
      let navigateCarousel = (carouselId, directionFunction) => {
        let n = directionFunction(carouselId);
        rotateCarousel(carouselId, n);
      };
      let nextBtn = (carouselId) => navigateCarousel(carouselId, forward);
      let prevBtn = (carouselId) => navigateCarousel(carouselId, backward);
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
          carouselId = carouselC.id;
          let shortId = carouselId.split('-')[3];
          autoHelper.push(parseInt(shortId + 1 + 1));
          autoHelper.push(parseInt(shortId + 0 + 1));
          buildCarousel(carouselId);
        }
      };

      function setAutoPlayInterval(carouselId, cTime) {
        return setInterval(() => {
          autoPlay(carouselId);
        }, cTime);
      }

      carousels.forEach(carouselC => {
        let carouselId = carouselC.id;
        let intervalId = carouselId.replaceAll('-', '');
        let cTimeId = carouselId.split('-')[3];
        let cTime = context.querySelector(`#co-${cTimeId} > .play-stop-value`)
          .textContent;
        cTime = parseInt(cTime);
        if (cTime != 0) {
          intervalId = setAutoPlayInterval(carouselId, cTime);
          let stopOnHover = context.getElementById(carouselId);
          let playPause = context.getElementById(`btn-${cTimeId}`);
          playPause.style.display = 'inline';
          stopOnHover.addEventListener('mouseover', function() {
            if (playPause.classList.contains('play-active-carousel')) {
              clearInterval(intervalId);
            }
          });
          stopOnHover.addEventListener('mouseout', function() {
            if (playPause.classList.contains('play-active-carousel')) {
              intervalId = setAutoPlayInterval(carouselId, cTime);
            }
          });
          playPause.addEventListener('click', function() {
            if (this.classList.contains('play-active-carousel')) {
              this.classList.replace('play-active-carousel', 'pause-active-carousel');
              this.innerHTML = '&#9654;';
              clearInterval(intervalId);
            } else {
              this.classList.replace('pause-active-carousel', 'play-active-carousel');
              this.innerHTML = '&#10073;&nbsp;&#10073;';
              intervalId = setAutoPlayInterval(carouselId, cTime);
            }
          });
        }
      });

      const handleWindowResize = () => {
        n = 0;
        let newWidth = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
        if (Math.abs(newWidth - currentWidth) >= 50) {
          initCarousel(carousels);
          currentWidth = newWidth;
        }
      };

      once('init-next-button', '.carousel-options .next-button', context).forEach(element => {
        element.addEventListener('click', function(event) {
          nextBtn(getThisId(event.target.id));
        });
      });

      once('init-prev-button', '.carousel-options .previous-button', context).forEach(element => {
        element.addEventListener('click', function(event) {
          prevBtn(getThisId(event.target.id));
        });
      });

      window.addEventListener('resize', handleWindowResize);
      initCarousel(carousels);

    }
  };
})(Drupal, drupalSettings, once);
