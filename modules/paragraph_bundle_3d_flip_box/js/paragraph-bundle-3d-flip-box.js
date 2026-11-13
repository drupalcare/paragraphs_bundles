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
 *   - `inert` + `aria-hidden` follow whichever face is actually presented,
 *     and so do the icon's rotation and the button's spoken label;
 *   - the button flips the box for keyboard and touch users, which `:hover`
 *     alone never did.
 *
 * The presented face has three independent sources — the button, the pointer
 * and focus — tracked separately so they cannot overwrite each other. The
 * button's state is sticky: hovering away does not undo an explicit flip.
 *
 * Focus never turns away the face that holds it. Focus on the back face keeps
 * the back up (so a pointer leaving cannot make the focused link inert), and
 * focus on the front face keeps the front up against a hover. The old rule,
 * "any focus in the faces shows the back", made the front inert while a front
 * link had focus, so focus dropped and front links were unreachable by
 * keyboard.
 */
// The Drupal standard sniffs JavaScript with PHP rules; `false` is not a PHP
// constant here. Same exemption as the other bundle behaviours.
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
((Drupal, once) => {
  /**
   * Applies the presented-face state to one flip box.
   *
   * @param {object} box
   *   Box state: scene, faces wrapper, front, back, toggle and the three
   *   flip sources.
   */
  function sync(box) {
    const showBack =
      box.focusedFace === 'back' ||
      box.pressed ||
      (box.hovered && box.focusedFace !== 'front');
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

    // `pb-is-flipped` tracks the BUTTON only, because it is what the sticky CSS
    // transform keys off. The icon needs the presented face instead: hovering
    // shows the back while leaving `pressed` false, and the control was still
    // drawing the front-facing icon over a back-facing card.
    box.scene.classList.toggle('pb-is-showing-back', showBack);

    // The label changes with the face, so the button carries no
    // aria-expanded (an older cached page may still print one).
    box.toggle.removeAttribute('aria-expanded');

    // Swap the spoken label with the state. Open/closed does not describe a
    // card with two equal sides -- "Show the front of X" / "Show the back of X"
    // names what pressing will DO. Both strings are translated in the template
    // and carried on the element.
    if (box.label) {
      const next = showBack
        ? box.label.dataset.pbLabelBack
        : box.label.dataset.pbLabelFront;
      if (next && box.label.textContent !== next) {
        box.label.textContent = next;
      }
    }
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
    // Optional: an older rendered page may still carry a static label.
    const label = toggle ? toggle.querySelector('[data-pb-label-front]') : null;

    if (!faces || !front || !back || !toggle) {
      return;
    }

    const box = {
      scene,
      faces,
      front,
      back,
      toggle,
      label,
      pressed: false,
      hovered: false,
      // 'front', 'back' or null: which face holds keyboard focus.
      focusedFace: null,
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
    // the button fight its own state. The face that holds focus is recorded,
    // so sync() never makes it inert.
    faces.addEventListener('focusin', (event) => {
      if (back.contains(event.target)) {
        box.focusedFace = 'back';
      } else if (front.contains(event.target)) {
        box.focusedFace = 'front';
      }
      sync(box);
    });
    faces.addEventListener('focusout', (event) => {
      if (faces.contains(event.relatedTarget)) {
        return;
      }
      box.focusedFace = null;
      sync(box);
    });

    sync(box);
  }

  Drupal.behaviors.pb3dFlipBox = {
    attach(context) {
      once('pb3dFlipBox', '.pb-3d-flip-box__scene', context).forEach(
        initFlipBox,
      );
    },
  };
})(Drupal, once);
