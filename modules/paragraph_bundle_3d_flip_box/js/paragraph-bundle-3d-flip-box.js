/**
 * @file
 * Keyboard operation and face-down isolation for the 3D flip box.
 *
 * The CSS flips the box on `:hover` and on `:focus-within` of the faces. Both
 * are visual only: `backface-visibility` does not touch the accessibility
 * tree, so without this script both faces are exposed to assistive technology
 * at once and any link on the face that is turned away stays in the tab order
 * while invisible.
 *
 * Three things happen here:
 *   - the toggle button is revealed (it is rendered `hidden`, so a page
 *     without this script never shows a control that cannot work);
 *   - `inert` + `aria-hidden` follow whichever face is actually presented;
 *   - the button flips the box for keyboard and touch users, which `:hover`
 *     alone never did.
 *
 * The presented face has three independent sources — the button, the pointer
 * and focus — tracked separately so they cannot overwrite each other. The
 * button's state is sticky: hovering away does not undo an explicit flip.
 */
// The Drupal standard sniffs JavaScript with PHP rules; `false` is not a PHP
// constant here. Same exemption as the other bundle behaviours.
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
((Drupal, once) => {
  'use strict';

  /**
   * Applies the presented-face state to one flip box.
   *
   * @param {object} box
   *   Box state: scene, faces wrapper, front, back, toggle and the three
   *   flip sources.
   */
  function sync(box) {
    const showBack = box.pressed || box.hovered || box.focused;
    const up = showBack ? box.back : box.front;
    const down = showBack ? box.front : box.back;

    // Order matters: release the face coming up before isolating the one
    // going down, so focus inside the box is never momentarily inside an
    // inert subtree.
    up.removeAttribute('inert');
    up.removeAttribute('aria-hidden');
    down.setAttribute('inert', '');
    down.setAttribute('aria-hidden', 'true');

    box.scene.classList.toggle('pb-is-flipped', box.pressed);
    box.toggle.setAttribute('aria-expanded', showBack ? 'true' : 'false');
  }

  /**
   * Wires one flip box.
   *
   * @param {HTMLElement} scene
   *   The `.pb-3d-flip-box__scene` element.
   */
  function initFlipBox(scene) {
    const faces = scene.querySelector('.pb-3d-flip-box__inner');
    const front = scene.querySelector('.pb-3d-flip-box__front');
    const back = scene.querySelector('.pb-3d-flip-box__back');
    const toggle = scene.querySelector('.pb-3d-flip-box__toggle');

    if (!faces || !front || !back || !toggle) {
      return;
    }

    const box = {
      scene,
      faces,
      front,
      back,
      toggle,
      pressed: false,
      hovered: false,
      focused: false,
    };

    toggle.hidden = false;

    toggle.addEventListener('click', () => {
      box.pressed = !box.pressed;
      sync(box);
    });

    // The pointer sources mirror the CSS `:hover` rule. Without them a mouse
    // user hovering the box would see the back face and be unable to click
    // anything on it, which is worse than the defect being fixed.
    scene.addEventListener('pointerenter', () => {
      box.hovered = true;
      sync(box);
    });
    scene.addEventListener('pointerleave', () => {
      box.hovered = false;
      sync(box);
    });

    // Focus is tracked on the faces wrapper, not the scene: the toggle lives
    // in the scene, and treating focus on it as "show the back" would make
    // the button fight its own state.
    faces.addEventListener('focusin', () => {
      box.focused = true;
      sync(box);
    });
    faces.addEventListener('focusout', (event) => {
      if (faces.contains(event.relatedTarget)) {
        return;
      }
      box.focused = false;
      sync(box);
    });

    sync(box);
  }

  Drupal.behaviors.pb3dFlipBox = {
    attach(context) {
      once('pb3dFlipBox', '.pb-3d-flip-box__scene', context).forEach(initFlipBox);
    },
  };
})(Drupal, once);
