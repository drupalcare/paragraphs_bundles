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

            currentPane.style.display = 'block';
            currentPane.setAttribute('aria-hidden', 'false');

            button.classList.add('pb__active');
            button.removeAttribute('tabindex');
            button.setAttribute('aria-selected', 'true');
            button.setAttribute('aria-expanded', 'true');
            button.focus();

            // ADDED: Update hash in URL to use data-fragment if available
            const slug = button.getAttribute('data-fragment');
            if (slug) {
              history.replaceState(null, '', `#${slug}`);
            } else {
              history.replaceState(null, '', `#${button.id}`);
            }
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
              return; // Ignore keys that aren't navigation-related.
          }

          event.preventDefault();

          tabButtons.forEach((button) => button.setAttribute('tabindex', '-1'));
          tabButtons[newIndex].setAttribute('tabindex', '0');
          tabButtons[newIndex].focus();
        }

        // Initialize tab buttons.
        tabButtons.forEach((button, index) => {
          if (index === 0) {
            button.classList.add('pb__active');
            button.setAttribute('aria-selected', 'true');
            button.setAttribute('aria-expanded', 'true');
            button.setAttribute('tabindex', '0');
          }
          else {
            button.setAttribute('tabindex', '-1');
          }

          button.addEventListener('keydown', (event) => handleKeyboardNavigation(event, index));
        });

        // ADDED: Hash-based tab activation using ID or data-fragment
        const hash = window.location.hash.replace('#', '');
        if (hash) {
          // Try matching by ID
          let button = tabContainer.querySelector(`#${CSS.escape(hash)}`);
          // Fallback to matching by data-fragment
          if (!button) {
            button = Array.from(tabButtons).find(btn => btn.getAttribute('data-fragment') === hash);
          }
          if (button) {
            activateTab(button);
            return; // Skip default activation
          }
        }

        // Ensure first pane is visible by default.
        tabPanes[0].style.display = 'block';
        tabPanes[0].setAttribute('aria-hidden', 'false');
      });
    }
  };
})(Drupal, drupalSettings, once);
