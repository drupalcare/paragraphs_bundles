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
    label.removeAttribute('for');
    label.setAttribute('id', `${fieldID}-label`);
    textInput.setAttribute('aria-labelledby', `${fieldID}-label`);
    colorInput.setAttribute('aria-labelledby', `${fieldID}-label`);
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

  /**
   * Para Color Picker behavior.
   *
   * @type {Drupal~behavior}
   * @prop {Drupal~behaviorAttach} attach
   *   Initializes color picker fields.
   */
  Drupal.behaviors.paraColorPicker = {
    attach: () => {
      const colorTextInputs = once(
        'para-color-picker',
        '[data-drupal-selector="para-color-picker"] input[type="text"]'
      );
      colorTextInputs.forEach(initColorPicker);
    }
  };
})(Drupal, drupalSettings, once);
