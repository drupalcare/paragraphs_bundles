/**
 * @file
 * Pattern Hand: click-only turning, and the deal-in class.
 *
 * PB's flip box turns on hover and focus as well as on its button. Here only
 * the button may turn a card, so the pointer and focus events are stopped on
 * their way to PB's listeners; the CSS removes the matching :hover and
 * :focus-within flips. Without this script the cards turn as PB's default.
 */

((Drupal, once) => {
  /**
   * Holds PB's pointer and focus sources back from a card's flip state.
   *
   * @param {HTMLElement} root
   *   The Pattern's root.
   */
  function holdClickOnly(root) {
    const stopForScene = (event) => {
      if (
        event.target instanceof Element &&
        event.target.matches('.pb-3d-flip-box__scene')
      ) {
        event.stopPropagation();
      }
    };
    const stopForFaces = (event) => {
      if (
        event.target instanceof Element &&
        event.target.closest('.pb-3d-flip-box__inner')
      ) {
        event.stopPropagation();
      }
    };
    root.addEventListener('pointerenter', stopForScene, true);
    root.addEventListener('pointerleave', stopForScene, true);
    root.addEventListener('focusin', stopForFaces, true);
    root.addEventListener('focusout', stopForFaces, true);
  }

  Drupal.behaviors.pbPatternGridHand = {
    attach(context) {
      once('pb-pattern-grid-hand', '.pb-pattern--grid-hand', context).forEach(
        (root) => {
          holdClickOnly(root);
          root.classList.add('pb-pattern-is-in');
        },
      );
    },
  };
})(Drupal, once);
