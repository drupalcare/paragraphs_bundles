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
    attach(context) {
      const tabContainers = once('paragraphBundleTabs', '.pb__tabs-bundle', context);

      tabContainers.forEach((tabContainer) => {
        const tabButtons = tabContainer.querySelectorAll('.pb__tab-button');
        const tabPanes = tabContainer.querySelectorAll('.pb__tab-pane');

        if (tabButtons.length === 0 || tabPanes.length === 0) {
          return; // Nothing to do if no buttons/panes.
        }

        /**
         * Hide all panes within this container.
         */
        function hideAllPanes() {
          tabPanes.forEach((pane) => {
            pane.style.display = 'none';
            pane.setAttribute('aria-hidden', 'true');
          });
        }

        /**
         * Remove active class and reset attributes on all buttons.
         */
        function removeActiveClassFromButtons() {
          tabButtons.forEach((button) => {
            button.classList.remove('pb__active');
            button.setAttribute('tabindex', '-1');
            button.setAttribute('aria-selected', 'false');
            button.setAttribute('aria-expanded', 'false');
          });
        }

        /**
         * Activate the given tab and its corresponding pane.
         */
        function activateTab(button) {
          const paneId = `${button.id}-pane`;
          const currentPane = tabContainer.querySelector(`#${paneId}`);

          if (currentPane) {
            hideAllPanes();
            removeActiveClassFromButtons();

            currentPane.style.display = 'flex';
            currentPane.setAttribute('aria-hidden', 'false');

            button.classList.add('pb__active');
            button.removeAttribute('tabindex');
            button.setAttribute('aria-selected', 'true');
            button.setAttribute('aria-expanded', 'true');
            button.focus();

            const slug = button.getAttribute('data-fragment') || button.id;
            history.replaceState(null, '', `#${slug}`);
          }
          else {
            Drupal.debug && Drupal.debug(`Pane with ID ${paneId} not found within this tabContainer.`);
          }
        }

        /**
         * Handle tab button clicks via event delegation.
         */
        tabContainer.addEventListener('click', (event) => {
          const button = event.target.closest('.pb__tab-button');
          if (button && tabContainer.contains(button)) {
            activateTab(button);
          }
        });

        /**
         * Handle keyboard navigation between tabs.
         */
        function handleKeyboardNavigation(event, currentIndex) {
          let newIndex;
          switch (event.key) {
            case 'ArrowRight':
              newIndex = (currentIndex + 1) % tabButtons.length;
              break;
            case 'ArrowLeft':
              newIndex = (currentIndex - 1 + tabButtons.length) % tabButtons.length;
              break;
            case 'Home':
              newIndex = 0;
              break;
            case 'End':
              newIndex = tabButtons.length - 1;
              break;
            default:
              return;
          }
          event.preventDefault();
          tabButtons.forEach((b) => b.setAttribute('tabindex', '-1'));
          tabButtons[newIndex].setAttribute('tabindex', '0');
          tabButtons[newIndex].focus();
        }

        // Initialize tab buttons with ARIA and keyboard support.
        tabButtons.forEach((button, index) => {
          if (index === 0) {
            button.classList.add('pb__active');
            button.setAttribute('aria-selected', 'true');
            button.setAttribute('aria-expanded', 'true');
            button.setAttribute('tabindex', '0');
          } else {
            button.setAttribute('tabindex', '-1');
          }
          button.addEventListener('keydown', (e) => handleKeyboardNavigation(e, index));
        });

        /**
         * Activate tab based on hash in URL if present.
         */
        const hash = window.location.hash.replace('#', '');
        if (hash) {
          let button = tabContainer.querySelector(`#${CSS.escape(hash)}`);
          if (!button) {
            button = Array.from(tabButtons).find(btn => btn.getAttribute('data-fragment') === hash);
          }
          if (button) {
            activateTab(button);
            return; // Skip default activation if hash tab activated.
          }
        }

        // Show the first pane by default if no hash match.
        tabPanes[0].style.display = 'flex';
        tabPanes[0].setAttribute('aria-hidden', 'false');
      });

      /**
       * Global in-page hash link handler for immediate tab activation on in-page clicks,
       * wrapped with `once()` for Drupal best practices.
       */
      once('paragraphBundleTabsGlobal', document).forEach(() => {
        document.addEventListener('click', (event) => {
          const anchor = event.target.closest('a[href^="#"]');
          if (!anchor) return;

          const hash = anchor.getAttribute('href').slice(1);
          if (!hash) return;

          let tabActivated = false;

          tabContainers.forEach((tabContainer) => {
            const tabButtons = tabContainer.querySelectorAll('.pb__tab-button');
            let button = tabContainer.querySelector(`#${CSS.escape(hash)}`);
            if (!button) {
              button = Array.from(tabButtons).find(btn => btn.getAttribute('data-fragment') === hash);
            }
            if (button) {
              event.preventDefault(); // Prevent native jump to hidden content.
              button.click(); // Trigger your existing delegation to activateTab.
              button.scrollIntoView({ behavior: 'smooth', block: 'start' });
              tabActivated = true;
            }
          });

          if (tabActivated) {
            history.replaceState(null, '', `#${hash}`);
          }
        });
      });
    }
  };
})(Drupal, drupalSettings, once);
