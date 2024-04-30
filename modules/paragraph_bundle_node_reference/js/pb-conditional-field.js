/**
 * @file
 * Paragraph Bundle Node Reference.
 *
 * Filename:     pb-conditional-fields.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
(function (Drupal) {
  Drupal.behaviors.PBNodeReference = {
    attach: function (context, settings) {
      try {
        const viewModeSelect = context.querySelector('.field--name-pb-content-view-mode select');
        if (!viewModeSelect) {
          console.error('View mode select not found');
          return; // Early exit if the main element is not found
        }

        // Guard against re-processing the same element
        if (viewModeSelect.classList.contains('processed')) {
          return;
        }
        viewModeSelect.classList.add('processed');

        const fieldSelectors = {
          'vm_title': '.field--name-pb-content-nf-title-only',
          'vm_teaser_text': '.field--name-pb-content-nf-teaser-text',
          'vm_slideshow': '.field--name-pb-content-nf-slideshow',
          'vm_3d_carousel': '.field--name-pb-content-nf-3d-carousel'
        };

        function checkFieldsAndSetOption() {
          let hasContent = false;
          let selectedOption = 'vm_title'; // Default option

          Object.entries(fieldSelectors).forEach(([key, selector]) => {
            const fieldContainer = context.querySelector(selector);
            if (fieldContainer) {
              const inputOrSelect = fieldContainer.querySelector('input, select');
              if (inputOrSelect && inputOrSelect.value) {
                hasContent = true;
                selectedOption = key;
              }
            }
          });

          // Set the view mode based on content detection
          viewModeSelect.value = hasContent ? selectedOption : 'vm_title';
          toggleAndClearFields(); // Apply initial visibility and potentially clear fields
        }

        function toggleAndClearFields(initializing = false) {
          const value = viewModeSelect.value;

          Object.values(fieldSelectors).forEach(selector => {
            const fieldContainer = context.querySelector(selector);
            if (!fieldContainer) {
              console.warn(`Field container for selector ${selector} not found.`);
              return; // Skip this iteration if the container is not found
            }

            fieldContainer.style.display = 'none'; // Hide the container directly
            if (initializing) {
              const inputOrSelect = fieldContainer.querySelector('input, select');
              if (inputOrSelect) {
                inputOrSelect.value = ''; // Reset the field value if it exists
              }
            }
          });

          const selectedFieldContainer = context.querySelector(fieldSelectors[value]);
          if (!selectedFieldContainer) {
            console.error(`Selected field container for value ${value} not found.`);
            return; // Exit if no container to show is found
          }
          selectedFieldContainer.style.display = 'block'; // Show the container
        }

        checkFieldsAndSetOption(); // Check fields and set option at initialization
        viewModeSelect.addEventListener('change', () => toggleAndClearFields(true));
      } catch (error) {
        console.error('Error in PBNodeReference behavior:', error);
      }
    }
  };
})(Drupal);

