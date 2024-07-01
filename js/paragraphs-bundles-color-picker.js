/**
 * @file
 * Paragraphs Bundles
 *
 * Filename:     paragraphs-bundles-color-picker.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, settings, once) => {
  /**
   * input event callback to keep text & color inputs in sync.
   *
   * @param {HTMLElement} changedInput input element changed by user
   * @param {HTMLElement} inputToSync input element to synchronize
   */
  const synchronizeInputs = (changedInput, inputToSync) => {
    const value = changedInput.value;
    inputToSync.value = value;
    changedInput.setAttribute('data-para-custom-color', value);
    inputToSync.setAttribute('data-para-custom-color', value);
  };

  const createColorInput = (textInput) => {
    const colorInput = document.createElement('input');
    colorInput.type = 'color';
    colorInput.classList.add(
      'form-color',
      'form-element',
      'form-element--type-color',
      'form-element--api-color'
    );
    colorInput.value = textInput.value;
    colorInput.setAttribute('name', `visual_${textInput.name}`);
    colorInput.setAttribute(
      'data-para-custom-color',
      textInput.getAttribute('data-para-custom-color')
    );
    return colorInput;
  };

  const updateLabelAttributes = (textInput, colorInput) => {
    const fieldID = textInput.id;
    const label = document.querySelector(`label[for="${fieldID}"]`);
    if (label) {
      label.removeAttribute('for');
      label.setAttribute('id', `${fieldID}-label`);
      textInput.setAttribute('aria-labelledby', `${fieldID}-label`);
      colorInput.setAttribute('aria-labelledby', `${fieldID}-label`);
    }
  };

  const addInputEventListener = (textInput, colorInput) => {
    textInput.addEventListener('input', () => {
      synchronizeInputs(textInput, colorInput);
    });
    colorInput.addEventListener('input', () => {
      synchronizeInputs(colorInput, textInput);
    });
  };

  const initColorPicker = (textInput) => {
    const colorInput = createColorInput(textInput);
    textInput.after(colorInput);
    updateLabelAttributes(textInput, colorInput);
    addInputEventListener(textInput, colorInput);
  };

  const getDisplayWidth = () => {
    const displayTabs = document.querySelectorAll('.pb__tab-display');

    displayTabs.forEach(displayTab => {
      let displayTabWidth;

      if (displayTab.style.display === 'none') {
        // Get the sibling element with class 'pb__tab-content'
        const sibling = displayTab.previousElementSibling;

        if (sibling && sibling.classList.contains('pb__tab-content')) {
          // Use the width of the sibling element
          displayTabWidth = sibling.getBoundingClientRect().width;
        } else {
          // Fallback width if sibling is not found
          displayTabWidth = 500;
        }
      } else {
        // If the element is visible, use its actual width
        displayTabWidth = displayTab.getBoundingClientRect().width;
      }

      // Remove all previous size classes to prevent class duplication
      displayTab.classList.remove('small', 'medium', 'large');

      if (displayTabWidth <= 575) {
        displayTab.classList.add('small'); // Small Devices
      } else if (displayTabWidth >= 576 && displayTabWidth <= 992) {
        displayTab.classList.add('medium'); // Medium Devices
      } else if (displayTabWidth >= 993) {
        displayTab.classList.add('large'); // Large Devices
      }
    });
  };

  /**
   * Para Color Picker behavior.
   *
   * @type {Drupal~behavior}
   * @prop {Drupal~behaviorAttach} attach
   *   Initializes color picker fields.
   */
  Drupal.behaviors.paraColorPicker = {
    attach: function (context, settings) {
      const colorTextInputs = once(
        'para-color-picker',
        '[data-drupal-selector="para-color-picker"] input[type="text"]',
        context
      );
      colorTextInputs.forEach(initColorPicker);

      // Call getDisplayWidth after 1/2 second
      setTimeout(() => {
        getDisplayWidth();
      }, 500);
      window.addEventListener('resize', getDisplayWidth);
    }
  };


})(Drupal, drupalSettings, once);

