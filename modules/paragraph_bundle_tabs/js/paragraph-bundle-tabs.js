/**
 * @file
 * Paragraph Bundle Tabs.
 *
 * Filename:     paragraph-bundle-tabs.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, drupalSettings, once) => {
  'use strict';

  Drupal.behaviors.paragraphBundleTabs = {
    attach: function(context, settings) {
      // Function to hide all panes
      function hideAllPanes(panes) {
        panes.forEach(pane => {
          if (pane) {
            pane.style.display = 'none';
            pane.setAttribute('aria-hidden', 'true');
          }
        });
      }

      // Function to remove active class from buttons
      function removeActiveClassFromButtons(buttons) {
        buttons.forEach(button => {
          if (button) {
            button.classList.remove('pb__active');
            button.setAttribute('tabindex', '-1');
            button.setAttribute('aria-selected', 'false');
          }
        });
      }

      // Function to handle tab clicks with event delegation
      function handleTabClicks(tabContainer) {
        tabContainer.addEventListener('click', event => {
          const button = event.target.closest('.pb__tab-button');
          if (!button) return;

          const btnId = button.id;
          const paneId = `${btnId}-pane`;
          const currentPane = document.getElementById(paneId);
          const parentId = `#pb__tab-${btnId.split('-')[1]}`;
          const tabPanes = document.querySelectorAll(`${parentId} .pb__tab-pane`);
          const tabButtons = document.querySelectorAll(`${parentId} .pb__tab-button`);

          if (currentPane) {
            hideAllPanes(tabPanes);
            removeActiveClassFromButtons(tabButtons);
            currentPane.style.display = 'block';
            currentPane.setAttribute('aria-hidden', 'false');
            button.classList.add('pb__active');
            button.removeAttribute('tabindex');
            button.setAttribute('aria-selected', 'true');
            button.focus();
          } else {
            console.warn(`Pane with ID ${paneId} not found.`);
          }
        });
      }

      // Function to handle keyboard navigation
      function handleKeyboardNavigation(tabs) {
        tabs.forEach(tab => {
          tab.addEventListener('keydown', (event) => {
            const currentIndex = Array.prototype.indexOf.call(tabs, tab);
            let newIndex;

            switch (event.key) {
              case 'ArrowRight':
                newIndex = (currentIndex + 1) % tabs.length;
                break;
              case 'ArrowLeft':
                newIndex = (currentIndex - 1 + tabs.length) % tabs.length;
                break;
              case 'Home':
                newIndex = 0;
                break;
              case 'End':
                newIndex = tabs.length - 1;
                break;
              case 'Tab':
                if (event.shiftKey) {
                  newIndex = (currentIndex - 1 + tabs.length) % tabs.length;
                } else {
                  newIndex = (currentIndex + 1) % tabs.length;
                }
                break;
              default:
                return;
            }

            tabs.forEach(t => t.setAttribute('tabindex', '-1'));
            tabs[newIndex].setAttribute('tabindex', '0');
            tabs[newIndex].focus();
            event.preventDefault();
          });
        });
      }

      const tabContainers = context.querySelectorAll('.pb__tabs-button > .pb__tab-button');
      tabContainers.forEach(container => {
        if (container) {
          handleTabClicks(container);
        }
      });

      // Activating the first child buttons
      const firstChildButtons = context.querySelectorAll('.pb__tabs-button > button:first-child');
      firstChildButtons.forEach(button => {
        if (button) {
          button.classList.add('pb__active');
          button.setAttribute('aria-selected', 'true');
          button.setAttribute('tabindex', '0');
        }
      });

      // Initialize tabindex for other buttons
      const tabButtons = context.querySelectorAll('.pb__tabs-button > .pb__tab-button');
      tabButtons.forEach((button, index) => {
        if (!button.classList.contains('pb__active')) {
          button.setAttribute('tabindex', '-1');
        }
      });

      // Handle keyboard navigation
      handleKeyboardNavigation(tabButtons);
    }
  };
})(Drupal, drupalSettings, once);

