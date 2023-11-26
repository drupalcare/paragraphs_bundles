((Drupal, settings, once) => {


  /**
   * Announces the text value of the field's label.
   *
   * @param {HTMLElement} changedInput
   *  The form element that was changed.
   */
  function announceFieldChange(changedInput) {
    const fieldTitle =
      changedInput.parentElement.querySelector('label').innerText;
    const fieldValue = changedInput.value;
    const announcement = Drupal.t('@fieldName has changed to @fieldValue', {
      '@fieldName': fieldTitle,
      '@fieldValue': fieldValue,
    });
    Drupal.announce(announcement);
  }

  /**
   * `input` event callback to keep text & color inputs in sync.
   *
   * @param {HTMLElement} changedInput input element changed by user
   * @param {HTMLElement} inputToSync input element to synchronize
   */
  function synchronizeInputs(changedInput, inputToSync) {
    inputToSync.value = changedInput.value;
    changedInput.setAttribute('data-para-custom-opacity', changedInput.value);
    inputToSync.setAttribute('data-para-custom-opacity', changedInput.value);
  }


  function initColorPicker(integerInput) {
    // Create input element.
    const opcityInput = document.createElement('input');

    // Set new input's attributes.
    opcityInput.type = 'range';
    opcityInput.classList.add(
      'form-range',
      'form-element',
      'form-element--type-range',
      'form-element--api-range',
    );
    opcityInput.value = integerInput.value;
    opcityInput.setAttribute('name', `visual_${integerInput.name}`);
    opcityInput.setAttribute('min', 1);
    opcityInput.setAttribute('max', 100);
    opcityInput.setAttribute('value', 100);
    opcityInput.setAttribute(
      'data-para-custom-opacity',
      integerInput.getAttribute('data-para-custom-opacity'),
    );

    // Insert new input into DOM.
    integerInput.after(opcityInput);

    // Make field label apply to integerInput and opcityInput.
    const fieldID = integerInput.id;
    const label = document.querySelector(`label[for="${fieldID}"]`);
    label.removeAttribute('for');
    label.setAttribute('id', `${fieldID}-label`);

    integerInput.setAttribute('aria-labelledby', `${fieldID}-label`);
    opcityInput.setAttribute('aria-labelledby', `${fieldID}-label`);

    // Add `input` event listener to keep inputs synchronized.
    integerInput.addEventListener('input', () => {
      synchronizeInputs(integerInput, opcityInput);
    });

    opcityInput.addEventListener('input', () => {
      synchronizeInputs(opcityInput, integerInput);
    });
  }

  /**
   * Paragraphs bundles range behavior.
   *
   * @type {Drupal~behavior}
   * @prop {Drupal~behaviorAttach} attach
   *   Initializes range field.
   */
  Drupal.behaviors.paragraphRange = {
    attach: () => {


      const opacityIntegerInputs = once(
        'para-opacity-range',
        '[data-drupal-selector="para-opacity-range"] input[type="number"]',
      );

      opacityIntegerInputs.forEach((integerInput) => {
        initColorPicker(integerInput);
        console.log('hello');
      });
    },
  };
})(Drupal, drupalSettings, once);
