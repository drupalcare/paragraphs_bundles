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
      const carousels = once('paragraphCarouselBundle',
        '.pb__carousel__inner', context);
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
      requestAnimationFrame(() => {
        updateLayout(state);
        createDots(state);
        bindDots(state);
        state.container.removeAttribute('hidden');
        setTimeout(() => {
          updateLayout(state);
        }, 100);
      });
    });
    if ('ResizeObserver' in window && state.wrapper) {
      const observer = new ResizeObserver(() => updateLayout(state));
      observer.observe(state.wrapper);
    }
    const refreshDots = debounce(() => {
      createDots(state);
      bindDots(state);
    }, 300);
    window.addEventListener('resize', () => {
      clampPageIndex(state);
      updateLayout(state);
      refreshDots();
    });
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
      looping: dataset.carouselLoop === '1',
      navigation: dataset.navigation || 'arrows',
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

  function getItemSize(item, isVertical) {
    return isVertical ? item.offsetHeight : item.offsetWidth;
  }

  function getItemsVisible(state) {
    const isVertical = state.config.orientation === 'vertical';
    const isHybrid = state.config.rawOrientation === 'hybrid';
    const isSmallScreen = window.innerWidth <= state.config.breakpoints;

    const max = isSmallScreen
      ? state.config.itemsSmall
      : state.config.itemsBig;

    // For vertical layout, always return max
    if (isVertical) {
      return max;
    }

    // For hybrid layout, act as vertical on small screens (return max),
    // but act as horizontal (apply fitting) on large screens.
    if (isHybrid && isSmallScreen) {
      return max;
    }

    // Horizontal (or hybrid in horizontal layout): smart fit logic
    const containerSize = state.wrapper.offsetWidth;
    const gap = state.config.gap || 0;
    let total = 0;
    let count = 0;

    for (let i = 0; i < state.items.length; i++) {
      const itemSize = state.items[i].offsetWidth;
      if (i > 0) total += gap;
      total += itemSize;

      if (total > containerSize) break;
      count++;
      if (count >= max) break;
    }

    return Math.max(1, count);
  }

  function getVisibleItems(state) {
    const itemsVisible = getItemsVisible(state);
    const start = state.pageIndex * itemsVisible;
    return Array.from(state.items).slice(start, start + itemsVisible);
  }

  function getMaxItemSize(state) {
    const isVertical = state.config.orientation === 'vertical';
    return getVisibleItems(state).reduce((max, item) => {
      const size = getItemSize(item, isVertical);
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
        const size = getItemSize(state.items[i], isVertical);
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
        return sum + getItemSize(item, true);
      }, 0);
      state.wrapper.style.maxHeight = `${totalHeight + totalGap}px`;
      state.wrapper.style.height = '';
      state.wrapper.style.maxWidth = '';
      state.centerOffset = 0;
    } else {
      const totalWidth = visibleItems.reduce((sum, item, idx) => {
        return sum + getItemSize(item, false) + (idx > 0 ? gap : 0);
      }, 0);
      const wrapperWidth = state.wrapper.offsetWidth;
      const centerOffset = (wrapperWidth - totalWidth) / 2;
      state.centerOffset = centerOffset > 0 ? centerOffset : 0;
      state.wrapper.style.maxWidth = '';
      state.wrapper.style.width = '';
      state.wrapper.style.maxHeight = '';
    }
    if (state.wrapper.style.visibility !== 'visible') {
      state.wrapper.style.visibility = 'visible';
      state.wrapper.classList.add('is-visible');
    }
  }

  function scrollToPage(state) {
    const offset = getGroupOffset(state);
    const centering = state.centerOffset || 0;
    const transform = centering - offset;
    if (state.config.orientation === 'vertical') {
      state.itemsContainer.style.transform = `translateY(${-offset}px)`;
    } else {
      state.itemsContainer.style.transform = `translateX(${transform}px)`;
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
    // Update dots active state
    if (state.config.navigation === 'dots' || state.config.navigation ===
      'both') {
      const dots = state.container.querySelectorAll('.pb__carousel-dot');
      dots.forEach((dot, index) => {
        const isActive = index === state.pageIndex;
        dot.classList.toggle('pb__active', isActive);
        dot.setAttribute('aria-current', isActive ? 'true' : 'false');
      });
    }
    updateNavVisibility(state);
    announceVisible(state, start, end);
    scrollToPage(state);
  }

  function updateNavVisibility(state) {
    if (state.config.navigation !== 'arrows' && state.config.navigation !==
      'both') {
      // No arrows used, do nothing.
      return;
    }
    const itemsVisible = getItemsVisible(state);
    const maxPage = Math.ceil(state.config.totalSlides / itemsVisible) - 1;
    if (!state.config.looping) {
      if (state.prevButton) {
        state.prevButton.style.visibility = state.pageIndex === 0 ? 'hidden' :
          'visible';
      }
      if (state.nextButton) {
        state.nextButton.style.visibility = state.pageIndex >= maxPage ?
          'hidden' : 'visible';
      }
    } else {
      if (state.prevButton) {
        state.prevButton.style.visibility = 'visible';
      }
      if (state.nextButton) {
        state.nextButton.style.visibility = 'visible';
      }
    }
  }

  function announceVisible(state, start, end) {
    const total = state.config.totalSlides;
    state.announcer.textContent =
      `Showing slides ${start + 1} to ${Math.min(end, total)} of ${total}`;
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

  function createDots(state) {
    const navigationType = state.config.navigation;
    const dotsContainer = state.container.querySelector('.pb__carousel-dots');
    if (!dotsContainer) return;
    if (navigationType !== 'dots' && navigationType !== 'both') {
      // If user chose arrows only or none, do not create dots
      dotsContainer.innerHTML = '';
      return;
    }
    dotsContainer.innerHTML = '';
    const itemsVisible = getItemsVisible(state);
    const totalPages = Math.ceil(state.config.totalSlides / itemsVisible);
    for (let i = 0; i < totalPages; i++) {
      const dot = document.createElement('button');
      dot.className = 'pb__carousel-dot';
      dot.setAttribute('aria-label', `Go to slide group ${i + 1}`);
      dot.dataset.slideGroup = i;
      dotsContainer.appendChild(dot);
    }
  }

  function bindDots(state) {
    if (state.config.navigation !== 'dots' && state.config.navigation !==
      'both') {
      return;
    }
    const dotsContainer = state.container.querySelector('.pb__carousel-dots');
    if (!dotsContainer) return;
    dotsContainer.querySelectorAll('.pb__carousel-dot').forEach(dot => {
      dot.addEventListener('click', () => {
        const groupIndex = parseInt(dot.dataset.slideGroup, 10);
        state.pageIndex = groupIndex;
        updateLayout(state);
      });
    });
  }

  function bindAutoSlide(state) {
    let interval = parseInt(state.config.slideTime, 10);
    if (interval === 0) return;
    if (isNaN(interval) || interval < 1000 || interval > 15000) {
      interval = 5000;
    }
    let isVisible = true;
    const start = () => {
      clearInterval(state.autoSlideTimer);
      state.autoSlideTimer = setInterval(() => nextSlide(state), interval);
    };
    const stop = () => clearInterval(state.autoSlideTimer);
    start();
    state.wrapper.addEventListener('mouseenter', stop);
    state.wrapper.addEventListener('mouseleave', () => {
      if (isVisible) start();
    });
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        stop();
      } else if (isVisible) {
        start();
      }
    });
    const observer = new IntersectionObserver((entries) => {
      isVisible = entries[0].isIntersecting;
      if (isVisible) {
        start();
      } else {
        stop();
      }
    }, {
      threshold: 0.3
    });
    observer.observe(state.container);
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
