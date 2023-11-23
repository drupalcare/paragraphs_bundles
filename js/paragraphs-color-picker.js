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

    changedInput.setAttribute('data-para-custom-color', changedInput.value);
    inputToSync.setAttribute('data-para-custom-color', changedInput.value);

    const colorSchemeSelect = document.querySelector(
      '[data-drupal-selector="edit-color-scheme"]',
    );

    if (colorSchemeSelect.value !== '') {
      colorSchemeSelect.value = '';
      announceFieldChange(colorSchemeSelect);
    }
  }


  function initColorPicker(textInput) {
    // Create input element.
    const colorInput = document.createElement('input');

    // Set new input's attributes.
    colorInput.type = 'color';
    colorInput.classList.add(
      'form-color',
      'form-element',
      'form-element--type-color',
      'form-element--api-color',
    );
    colorInput.value = textInput.value;
    colorInput.setAttribute('name', `visual_${textInput.name}`);
    colorInput.setAttribute(
      'data-para-custom-color',
      textInput.getAttribute('data-para-custom-color'),
    );

    // Insert new input into DOM.
    textInput.after(colorInput);

    // Make field label apply to textInput and colorInput.
    const fieldID = textInput.id;
    const label = document.querySelector(`label[for="${fieldID}"]`);
    label.removeAttribute('for');
    label.setAttribute('id', `${fieldID}-label`);

    textInput.setAttribute('aria-labelledby', `${fieldID}-label`);
    colorInput.setAttribute('aria-labelledby', `${fieldID}-label`);

    // Add `input` event listener to keep inputs synchronized.
    textInput.addEventListener('input', () => {
      synchronizeInputs(textInput, colorInput);
    });

    colorInput.addEventListener('input', () => {
      synchronizeInputs(colorInput, textInput);
    });
  }

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
        '[data-drupal-selector="para-color-picker"] input[type="text"]',
      );

      colorTextInputs.forEach((textInput) => {
        initColorPicker(textInput);
      });
    },
  };
})(Drupal, drupalSettings, once);
