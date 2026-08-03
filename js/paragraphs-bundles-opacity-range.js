/**
 * @file
 * Paragraphs Bundles — opacity range widget.
 *
 * Builds the inner [number | range] row inside each opacity form item after
 * Drupal renders the standard label + number + description structure.
 *
 * Filename:     paragraphs-bundles-opacity-range.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com.
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing
((Drupal, once) => {
  const DATA_PARA_CUSTOM_OPACITY = 'data-para-custom-opacity';
  const OPACITY_RANGE_SELECTOR = 'para-opacity-range';
  const NUMBER_INPUT_SELECTOR =
    '.field--type-paragraphs-bundles-range input[type="number"]';

  /**
   * Syncs number and range inputs and stores the current value.
   *
   * @param {HTMLInputElement} changedInput
   *   The input that changed.
   * @param {HTMLInputElement} inputToSync
   *   The input to update.
   */
  function synchronizeInputs(changedInput, inputToSync) {
    const value = changedInput.value;
    inputToSync.value = value;
    changedInput.setAttribute(DATA_PARA_CUSTOM_OPACITY, value);
    inputToSync.setAttribute(DATA_PARA_CUSTOM_OPACITY, value);
  }

  /**
   * Builds the visual range input paired with the number field.
   *
   * @param {HTMLInputElement} integerInput
   *   The number input.
   *
   * @return {HTMLInputElement}
   *   The range input.
   */
  function createRangeInput(integerInput) {
    const rangeInput = document.createElement('input');
    rangeInput.type = 'range';
    rangeInput.classList.add(
      'form-range',
      'form-element',
      'form-element--type-range',
      'form-element--api-range',
    );
    rangeInput.value = integerInput.value;
    rangeInput.setAttribute('name', `visual_${integerInput.name}`);
    rangeInput.setAttribute('min', '1');
    rangeInput.setAttribute('max', '100');
    rangeInput.setAttribute(
      DATA_PARA_CUSTOM_OPACITY,
      integerInput.getAttribute(DATA_PARA_CUSTOM_OPACITY) ?? integerInput.value,
    );
    return rangeInput;
  }

  /**
   * Links the range control to the field label for screen readers.
   *
   * @param {HTMLElement} formItem
   *   The outer Drupal form item.
   * @param {HTMLInputElement} integerInput
   *   The number input.
   * @param {HTMLInputElement} rangeInput
   *   The range input.
   */
  function linkLabelToInputs(formItem, integerInput, rangeInput) {
    const fieldId = integerInput.id;
    if (!fieldId) {
      return;
    }

    const label = formItem.querySelector(`label[for="${fieldId}"]`);
    if (!(label instanceof HTMLLabelElement)) {
      return;
    }

    if (!label.id) {
      label.id = `${fieldId}-label`;
      label.removeAttribute('for');
      integerInput.setAttribute('aria-labelledby', label.id);
    }
    rangeInput.setAttribute('aria-labelledby', label.id);
  }

  /**
   * Wraps number + range in the inner opacity row container.
   *
   * @param {HTMLInputElement} integerInput
   *   The number input to enhance.
   */
  function initRangeInput(integerInput) {
    if (
      integerInput.closest(`[data-drupal-selector="${OPACITY_RANGE_SELECTOR}"]`)
    ) {
      return;
    }

    const formItem = integerInput.closest('.js-form-item');
    if (!formItem) {
      return;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'pb-opacity-range';
    wrapper.setAttribute('data-drupal-selector', OPACITY_RANGE_SELECTOR);

    integerInput.parentNode.insertBefore(wrapper, integerInput);

    const rangeInput = createRangeInput(integerInput);
    wrapper.appendChild(integerInput);
    wrapper.appendChild(rangeInput);

    linkLabelToInputs(formItem, integerInput, rangeInput);

    integerInput.addEventListener('input', () => {
      synchronizeInputs(integerInput, rangeInput);
    });
    rangeInput.addEventListener('input', () => {
      synchronizeInputs(rangeInput, integerInput);
    });
  }

  Drupal.behaviors.paragraphRange = {
    attach(context) {
      once('pb-opacity-range', NUMBER_INPUT_SELECTOR, context).forEach(
        (integerInput) => {
          if (integerInput instanceof HTMLInputElement) {
            initRangeInput(integerInput);
          }
        },
      );
    },

    detach(context, _settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }
      once.remove('pb-opacity-range', NUMBER_INPUT_SELECTOR, context);
    },
  };
})(Drupal, once);
