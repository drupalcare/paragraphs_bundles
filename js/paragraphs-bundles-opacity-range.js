/**
 * @file
 * Paragraphs Bundles
 *
 * Filename:     paragraphs-bundles-opacity-range.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
((Drupal, settings, once) => {
  const DATA_PARA_CUSTOM_OPACITY = 'data-para-custom-opacity';
  const PARA_OPACITY_RANGE = 'para-opacity-range';
  const INPUT_TYPE_NUMBER = 'input[type="number"]';

  function synchronizeInputs(changedInput, inputToSync) {
    const value = changedInput.value;
    inputToSync.value = value;
    setAttributeToElement(changedInput, DATA_PARA_CUSTOM_OPACITY, value);
    setAttributeToElement(inputToSync, DATA_PARA_CUSTOM_OPACITY, value);
  }

  function setAttributeToElement(element, attributeName, value) {
    element.setAttribute(attributeName, value);
  }

  function createAndConfigureInput(integerInput) {
    const opcityInput = document.createElement('input');
    opcityInput.type = 'range';
    opcityInput.classList.add(
      'form-range',
      'form-element',
      'form-element--type-range',
      'form-element--api-range',
    );
    opcityInput.value = integerInput.value;
    setAttributeToElement(opcityInput, 'name', `visual_${integerInput.name}`);
    setAttributeToElement(opcityInput, 'min', 1);
    setAttributeToElement(opcityInput, 'max', 100);
    setAttributeToElement(opcityInput, 'value', 100);
    setAttributeToElement(
      opcityInput,
      DATA_PARA_CUSTOM_OPACITY,
      integerInput.getAttribute(DATA_PARA_CUSTOM_OPACITY),
    );
    return opcityInput;
  }

  function initRangeInput(integerInput) {
    const opcityInput = createAndConfigureInput(integerInput);
    integerInput.after(opcityInput);
    const fieldID = integerInput.id;
    const label = document.querySelector(`label[for="${fieldID}"]`);
    label.removeAttribute('for');
    setAttributeToElement(label, 'id', `${fieldID}-label`);
    setAttributeToElement(integerInput, 'aria-labelledby', `${fieldID}-label`);
    setAttributeToElement(opcityInput, 'aria-labelledby', `${fieldID}-label`);
    integerInput.addEventListener('input', () => {
      synchronizeInputs(integerInput, opcityInput);
    });
    opcityInput.addEventListener('input', () => {
      synchronizeInputs(opcityInput, integerInput);
    });
  }

  Drupal.behaviors.paragraphRange = {
    attach: () => {
      const opacityIntegerInputs = once(
        PARA_OPACITY_RANGE,
        `[data-drupal-selector="${PARA_OPACITY_RANGE}"] ${INPUT_TYPE_NUMBER}`,
      );
      opacityIntegerInputs.forEach((integerInput) => {
        initRangeInput(integerInput);
      });
    },
  };
})(Drupal, drupalSettings, once);
