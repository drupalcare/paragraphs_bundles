/**
 * @file
 * Paragraph Bundle Tabs.
 *
 * Filename:     paragraph-bundle-image-grid.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, once) => {
  'use strict';
  Drupal.behaviors.paragraphBundleImageGridModal = {
    attach: (context, settings) => {
      const currModal = document.querySelector('.pb__grid-lightbox');
      const modalContent = currModal.querySelector('.pb__grid-lightbox-content');
      let currentIndex = -1;
      let items = Array.from(document.querySelectorAll('.paragraph--type--image-grid-section-bundle'));
      let lastFocusedElement;

      const updateItemCountClass = () => {
        const wrappers = document.querySelectorAll('.pb__image-grid-wrapper');
        wrappers.forEach(wrapper => {
          const itemCount = wrapper.children.length - 1;
          wrapper.className = wrapper.className.replace(/\bgrid-count-\d+\b/g, '');
          wrapper.classList.add(`grid-count-${itemCount}`);
        });
      };

      const updateModalContent = (fullImageUrl) => {
        const clonedElement = items[currentIndex].cloneNode(true);
        const imageElement = clonedElement.querySelector('img');
        if (imageElement) {
          imageElement.src = fullImageUrl;
          imageElement.removeAttribute('height');
          imageElement.removeAttribute('width');
        }
        modalContent.innerHTML = '';
        modalContent.appendChild(clonedElement);
        modalContent.offsetHeight;
        modalContent.style.opacity = '1';
        modalContent.classList.remove('fade-in');
        requestAnimationFrame(() => {
          modalContent.classList.add('fade-in');
        });
      };

      const openModal = (event) => {
        lastFocusedElement = document.activeElement;
        const paragraphContent = event.currentTarget.closest('.paragraph--type--image-grid-section-bundle');
        currentIndex = items.indexOf(paragraphContent);
        const fullImageUrl = paragraphContent.getAttribute('data-full-image-url');
        updateModalContent(fullImageUrl);
        event.currentTarget.setAttribute('aria-expanded', 'true');
        currModal.setAttribute('aria-hidden', 'false');
        currModal.style.display = 'block';
        currModal.focus();
        document.addEventListener('keydown', trapFocus);
        event.stopPropagation();
      };

      const closeModal = (event) => {
        currModal.style.display = 'none';
        currModal.setAttribute('aria-hidden', 'true');
        document.querySelectorAll('.pb__expand-grid').forEach(button => button.setAttribute('aria-expanded', 'false'));
        currentIndex = -1;
        if (lastFocusedElement) {
          lastFocusedElement.focus();
        }
        document.removeEventListener('keydown', trapFocus);
        if (event) event.stopPropagation();
      };

      const navigateModal = (direction) => {
        if (direction === 'next') {
          currentIndex = (currentIndex + 1) % items.length;
        } else if (direction === 'prev') {
          currentIndex = (currentIndex - 1 + items.length) % items.length;
        }
        const fullImageUrl = items[currentIndex].getAttribute('data-full-image-url');
        updateModalContent(fullImageUrl);
      };

      const trapFocus = (event) => {
        const focusableElements = currModal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        if (event.key === 'Tab') {
          if (event.shiftKey) {
            if (document.activeElement === firstElement) {
              lastElement.focus();
              event.preventDefault();
            }
          } else {
            if (document.activeElement === lastElement) {
              firstElement.focus();
              event.preventDefault();
            }
          }
        } else if (event.key === 'Escape') {
          closeModal(event);
        }
      };

      once('paragraphBundleExpand', '.pb__expand-grid', context).forEach(button => button.addEventListener('click', openModal));
      once('modalNavigation', '.pb__modal-next, .pb__modal-prev', context).forEach(button => button.addEventListener('click', event => {
        const direction = button.classList.contains('pb__modal-next') ? 'next' : 'prev';
        navigateModal(direction);
        event.stopPropagation();
      }));

      context.addEventListener('click', event => {
        if (event.target.matches('.pb__close-grid') || event.target.closest('.pb__close-grid') || event.target === modalContent) {
          closeModal(event);
        }
      });

      window.addEventListener('load', updateItemCountClass);
    }
  };
})(Drupal, once);



