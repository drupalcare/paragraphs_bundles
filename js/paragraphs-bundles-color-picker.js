/**
 * @file
 * Paragraphs Bundles — Color Picker widget.
 *
 * Enhances each [data-drupal-selector="pb-color-picker"] text input with:
 *  - A native <input type="color"> swatch (leading).
 *  - The existing hex text input (middle).
 *  - A clear button that resets the field (trailing).
 *
 * Both inputs stay in sync via requestAnimationFrame. An "empty state"
 * CSS class is toggled on the outer card so CSS can indicate an unset
 * field (dashed swatch border, muted text, disabled clear button).
 *
 * Follows the same pattern as apex/admin-color-picker.js.
 *
 * @license SPDX-License-Identifier: GPL-2.0-or-later
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing
(function (Drupal, once) {
  'use strict';

  // -------------------------------------------------------------------------
  // Pure helpers (no DOM side-effects).
  // -------------------------------------------------------------------------

  /**
   * Returns true when value is empty or a valid 3/6-digit hex string.
   *
   * @param {string} value - Color value to test.
   * @return {boolean} Whether the value is valid or empty.
   */
  const isValidColor = (value) => {
    if (!value || value === '') {
      return true;
    }
    return /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(value);
  };

  /**
   * Normalizes #RGB → #RRGGBB and uppercases the result.
   *
   * @param {string} value - Hex color value.
   * @return {string} Six-digit hex color.
   */
  const toSixDigitHex = (value) => {
    if (!value) { return value; }
    const m = /^#([A-Fa-f0-9]{3})$/.exec(value);
    if (m) {
      const [r, g, b] = m[1].split('');
      return `#${r}${r}${g}${g}${b}${b}`.toUpperCase();
    }
    return value.toUpperCase();
  };

  // -------------------------------------------------------------------------
  // DOM mutators.
  // -------------------------------------------------------------------------

  /**
   * Toggles the pb-color-empty class on the outer card element.
   *
   * @param {HTMLElement} wrapper - The .pb-color-picker__wrapper element.
   * @param {string} value - Current text-input value.
   */
  const updateEmptyState = (wrapper, value) => {
    const card = wrapper.closest('[data-drupal-selector="pb-color-picker"]');
    if (!card) { return; }
    if (!value || value === '') {
      card.classList.add('pb-color-empty');
    }
    else {
      card.classList.remove('pb-color-empty');
    }
  };

  /**
   * Copies source value → target input (with format conversion).
   * Runs inside requestAnimationFrame to batch DOM writes.
   *
   * @param {HTMLInputElement} source - The changed input.
   * @param {HTMLInputElement} target - The input to update.
   * @param {HTMLElement} wrapper - The .pb-color-picker__wrapper element.
   */
  const syncInputs = (source, target, wrapper) => {
    const { value } = source;
    if (!isValidColor(value)) { return; }
    requestAnimationFrame(() => {
      if (value === '') {
        target.value = target.type === 'color' ? '#000000' : '';
      }
      else {
        target.value =
          target.type === 'color'
            ? toSixDigitHex(value)
            : value.toUpperCase();
      }
      updateEmptyState(wrapper, value);
    });
  };

  /**
   * Resets both inputs and notifies Drupal of the change.
   *
   * @param {HTMLInputElement} textInput - The hex text input.
   * @param {HTMLInputElement} colorInput - The native color swatch input.
   * @param {HTMLElement} wrapper - The .pb-color-picker__wrapper element.
   */
  const clearColor = (textInput, colorInput, wrapper) => {
    textInput.value = '';
    colorInput.value = '#000000';
    updateEmptyState(wrapper, '');
    textInput.dispatchEvent(new Event('change', { bubbles: true }));
  };

  // -------------------------------------------------------------------------
  // Element factories.
  // -------------------------------------------------------------------------

  /**
   * Builds the native color swatch input seeded with the current hex value.
   *
   * @param {HTMLInputElement} textInput - The existing hex text input.
   * @return {HTMLInputElement} The created color input.
   */
  const createColorInput = (textInput) => {
    const colorInput = document.createElement('input');
    colorInput.type = 'color';
    colorInput.className =
      'form-color form-element form-element--type-color';
    const val = textInput.value;
    colorInput.value =
      val && isValidColor(val) ? toSixDigitHex(val) : '#000000';
    return colorInput;
  };

  /**
   * Builds the × clear button.
   *
   * @return {HTMLButtonElement} The created button.
   */
  const createClearButton = () => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'pb-color__clear-btn';
    btn.setAttribute('aria-label', Drupal.t('Clear color'));
    btn.setAttribute('title', Drupal.t('Clear color'));
    btn.innerHTML = '&times;';
    return btn;
  };

  // -------------------------------------------------------------------------
  // Picker initializer.
  // -------------------------------------------------------------------------

  /**
   * Wraps a hex text input in [swatch | text | clear] row.
   *
   * @param {HTMLInputElement} textInput - The text input to enhance.
   */
  const initColorPicker = (textInput) => {
    if (!textInput || textInput.nodeType !== 1) { return; }

    const colorInput = createColorInput(textInput);
    const clearBtn = createClearButton();

    const wrapper = document.createElement('div');
    wrapper.className = 'pb-color-picker__wrapper';

    // Insert wrapper before the text input, then move elements inside.
    textInput.parentNode.insertBefore(wrapper, textInput);
    wrapper.appendChild(colorInput);
    wrapper.appendChild(textInput);
    wrapper.appendChild(clearBtn);

    updateEmptyState(wrapper, textInput.value);

    // Sync: text → swatch (debounced for typed input).
    let textTimeout;
    textInput.addEventListener('input', () => {
      clearTimeout(textTimeout);
      textTimeout = setTimeout(
        () => syncInputs(textInput, colorInput, wrapper),
        300
      );
    });
    textInput.addEventListener('change', () => {
      syncInputs(textInput, colorInput, wrapper);
    });

    // Sync: swatch → text (immediate; native picker fires 'input' live).
    colorInput.addEventListener('input', () => {
      syncInputs(colorInput, textInput, wrapper);
    });
    colorInput.addEventListener('change', () => {
      syncInputs(colorInput, textInput, wrapper);
    });

    clearBtn.addEventListener('click', (e) => {
      e.preventDefault();
      clearColor(textInput, colorInput, wrapper);
    });
  };

  // -------------------------------------------------------------------------
  // Drupal behavior.
  // -------------------------------------------------------------------------

  Drupal.behaviors.pbColorPicker = {
    attach(context) {
      once(
        'pb-color-picker',
        '[data-drupal-selector="pb-color-picker"] input[type="text"]',
        context
      ).forEach(initColorPicker);
    },

    detach(context, _settings, trigger) {
      if (trigger !== 'unload') { return; }
      once.remove(
        'pb-color-picker',
        '[data-drupal-selector="pb-color-picker"] input[type="text"]',
        context
      );
    },
  };

})(Drupal, once);
