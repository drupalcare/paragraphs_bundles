/**
 * @file
 * Paragraph Bundle Carousel.
 *
 * Filename:     paragraph-bundle-carousel.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, drupalSettings, once) => {
  'use strict';

  function debounce(func, delay) {
    let timer;
    return function() {
      clearTimeout(timer);
      timer = setTimeout(func, delay);
    };
  }
  Drupal.behaviors.paragraphCarouselBundle = {
    attach(context) {
      const carousels = once('paragraphCarouselBundle', '.pb__carousel__inner', context);
      carousels.forEach(initCarousel);
    }
  };

  function initCarousel(carouselInner) {
    const state = createState(carouselInner);
    bindNavigation(state);
    bindTouch(state);
    bindAutoSlide(state);
    bindKeyboard(state);
    requestAnimationFrame(() => {
      updateLayout(state);
      state.container.removeAttribute('hidden');
    });
    if ('ResizeObserver' in window && state.items.length > 0) {
      const observer = new ResizeObserver(() => updateLayout(state));
      observer.observe(state.items[0]);
    }
    window.addEventListener('resize', debounce(() => {
      clampPageIndex(state);
      updateLayout(state);
    }, 300));
  }

  function createState(container) {
    const wrapper = container.querySelector('.pb__carousel-wrapper');
    const itemsContainer = wrapper.querySelector('.pb__carousel-items');
    if (!wrapper || !itemsContainer) return;
    const items = itemsContainer.querySelectorAll('.pb__carousel-item');
    const nextButton = container.querySelector('.pb__next');
    const prevButton = container.querySelector('.pb__prev');
    const announcer = container.querySelector('.pb__carousel-announcer');
    const dataset = itemsContainer.dataset;
    const breakpoints = parseInt(dataset.breakpoints, 10);
    const config = {
      rawOrientation: dataset.orientation,
      orientation: 'horizontal',
      breakpoints,
      itemsSmall: parseInt(dataset.smallScreen, 10),
      itemsBig: parseInt(dataset.bigScreen, 10),
      totalSlides: parseInt(dataset.totalSlides, 10),
      slideTime: parseInt(dataset.slideTime, 10),
      gap: parseInt(dataset.gap, 10),
      looping: dataset.carouselLoop === '1'
    };
    return {
      container,
      wrapper,
      itemsContainer,
      items,
      nextButton,
      prevButton,
      announcer,
      config,
      pageIndex: 0,
      autoSlideTimer: null
    };
  }

  function resolveOrientation(state) {
    const raw = state.config.rawOrientation;
    const bp = state.config.breakpoints;
    if (raw === 'vertical') return 'vertical';
    if (raw === 'hybrid' && window.innerWidth <= bp) return 'vertical';
    return 'horizontal';
  }

  function getItemsVisible(state) {
    return window.innerWidth <= state.config.breakpoints ? state.config.itemsSmall : state.config.itemsBig;
  }

  function getVisibleItems(state) {
    const itemsVisible = getItemsVisible(state);
    const start = state.pageIndex * itemsVisible;
    return Array.from(state.items).slice(start, start + itemsVisible);
  }

  function getMaxItemSize(state) {
    const isVertical = state.config.orientation === 'vertical';
    return getVisibleItems(state).reduce((max, item) => {
      const size = isVertical ? item.getBoundingClientRect().height : item.getBoundingClientRect().width;
      return Math.max(max, size);
    }, 0);
  }

  function getGroupOffset(state) {
    const itemsVisible = getItemsVisible(state);
    const isVertical = state.config.orientation === 'vertical';
    const gap = state.config.gap || 0;
    let offset = 0;
    for (let page = 0; page < state.pageIndex; page++) {
      const start = page * itemsVisible;
      const end = start + itemsVisible;
      for (let i = start; i < end && i < state.items.length; i++) {
        const size = isVertical ?
          state.items[i].getBoundingClientRect().height :
          state.items[i].getBoundingClientRect().width;
        offset += size;
        if (i < end - 1) offset += gap;
      }
      if (end < state.items.length) {
        offset += gap;
      }
    }
    return offset;
  }

  function setWrapperSize(state) {
    const itemsVisible = getItemsVisible(state);
    if (!state.items.length) return;
    const visibleItems = getVisibleItems(state);
    const gap = state.config.gap || 0;
    const totalGap = (itemsVisible - 1) * gap;
    if (state.config.orientation === 'vertical') {
      const totalHeight = visibleItems.reduce((sum, item) => {
        return sum + item.getBoundingClientRect().height;
      }, 0);
      state.wrapper.style.height = `${totalHeight + totalGap}px`;
      state.wrapper.style.width = '';
    } else {
      const totalWidth = visibleItems.reduce((sum, item) => {
        return sum + item.getBoundingClientRect().width;
      }, 0);
      state.wrapper.style.width = `${totalWidth + totalGap}px`;
      state.wrapper.style.height = '';
    }
    if (state.wrapper.style.visibility !== 'visible') {
      state.wrapper.style.visibility = 'visible';
      state.wrapper.classList.add('is-visible');
    }
  }

  function scrollToPage(state) {
    const offset = getGroupOffset(state);
    const transform = `-${offset}px`;
    if (state.config.orientation === 'vertical') {
      state.itemsContainer.style.transform = `translateY(${transform})`;
    } else {
      state.itemsContainer.style.transform = `translateX(${transform})`;
    }
  }

  function updateLayout(state) {
    state.config.orientation = resolveOrientation(state);
    setWrapperSize(state);
    const itemsVisible = getItemsVisible(state);
    const start = state.pageIndex * itemsVisible;
    const end = start + itemsVisible;
    state.items.forEach((item, i) => {
      item.classList.toggle('active-slide', i >= start && i < end);
      item.classList.toggle('non-active-slide', i < start || i >= end);
    });
    updateNavVisibility(state);
    announceVisible(state, start, end);
    scrollToPage(state);
  }

  function updateNavVisibility(state) {
    const itemsVisible = getItemsVisible(state);
    const maxPage = Math.ceil(state.config.totalSlides / itemsVisible) - 1;
    if (!state.config.looping) {
      state.prevButton.style.visibility = state.pageIndex === 0 ? 'hidden' : 'visible';
      state.nextButton.style.visibility = state.pageIndex >= maxPage ? 'hidden' : 'visible';
    } else {
      state.prevButton.style.visibility = 'visible';
      state.nextButton.style.visibility = 'visible';
    }
  }

  function announceVisible(state, start, end) {
    const total = state.config.totalSlides;
    state.announcer.textContent = `Showing slides ${start + 1} to ${Math.min(end, total)} of ${total}`;
  }

  function nextSlide(state) {
    const itemsVisible = getItemsVisible(state);
    const totalPages = Math.ceil(state.config.totalSlides / itemsVisible);
    state.pageIndex = (state.pageIndex >= totalPages - 1) ?
      (state.config.looping ? 0 : totalPages - 1) :
      state.pageIndex + 1;
    updateLayout(state);
  }

  function prevSlide(state) {
    const itemsVisible = getItemsVisible(state);
    const maxPage = Math.ceil(state.config.totalSlides / itemsVisible) - 1;
    state.pageIndex = (state.pageIndex > 0) ?
      state.pageIndex - 1 :
      (state.config.looping ? maxPage : 0);
    updateLayout(state);
  }

  function bindNavigation(state) {
    const shouldAutoplay = state.config.slideTime > 0 &&
      state.config.slideTime >= 1000 &&
      state.config.slideTime <= 15000;
    const resetAutoSlide = () => {
      if (shouldAutoplay && state.autoSlideTimer) {
        clearInterval(state.autoSlideTimer);
        bindAutoSlide(state);
      }
    };
    state.nextButton?.addEventListener('click', () => {
      nextSlide(state);
      resetAutoSlide();
    });
    state.prevButton?.addEventListener('click', () => {
      prevSlide(state);
      resetAutoSlide();
    });
  }

  function bindTouch(state) {
    let startX = 0,
      startY = 0;
    state.wrapper.addEventListener('touchstart', e => {
      startX = e.touches[0].clientX;
      startY = e.touches[0].clientY;
    });
    state.wrapper.addEventListener('touchend', e => {
      const deltaX = e.changedTouches[0].clientX - startX;
      const deltaY = e.changedTouches[0].clientY - startY;
      if (Math.abs(deltaX) > Math.abs(deltaY)) {
        deltaX < -50 ? nextSlide(state) : deltaX > 50 && prevSlide(state);
      } else if (state.config.orientation === 'vertical') {
        deltaY < -50 ? nextSlide(state) : deltaY > 50 && prevSlide(state);
      }
    });
  }

  function bindAutoSlide(state) {
    let interval = parseInt(state.config.slideTime, 10);
    if (interval === 0) return;
    if (isNaN(interval) || interval < 1000 || interval > 15000) {
      console.warn('Invalid slideTime detected. Falling back to 5000ms.');
      interval = 5000;
    }
    const start = () => {
      clearInterval(state.autoSlideTimer);
      state.autoSlideTimer = setInterval(() => nextSlide(state), interval);
    };
    const stop = () => clearInterval(state.autoSlideTimer);
    start();
    state.wrapper.addEventListener('mouseenter', stop);
    state.wrapper.addEventListener('mouseleave', start);
    document.addEventListener('visibilitychange', () => {
      document.hidden ? stop() : start();
    });
  }

  function bindKeyboard(state) {
    state.container.setAttribute('tabindex', '0'); // Make focusable
    state.container.addEventListener('keydown', (e) => {
      const orientation = state.config.orientation;
      if (
        (orientation === 'horizontal' && e.key === 'ArrowRight') ||
        (orientation === 'vertical' && e.key === 'ArrowDown')
      ) {
        e.preventDefault();
        nextSlide(state);
      }
      if (
        (orientation === 'horizontal' && e.key === 'ArrowLeft') ||
        (orientation === 'vertical' && e.key === 'ArrowUp')
      ) {
        e.preventDefault();
        prevSlide(state);
      }
    });
  }

  function clampPageIndex(state) {
    const itemsVisible = getItemsVisible(state);
    const maxPage = Math.ceil(state.config.totalSlides / itemsVisible) - 1;
    state.pageIndex = Math.min(state.pageIndex, maxPage);
  }
})(Drupal, drupalSettings, once);
