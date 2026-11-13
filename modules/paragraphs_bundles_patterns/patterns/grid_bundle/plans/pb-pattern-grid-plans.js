/**
 * @param Drupal
 * @param once
 * @file
 * Pattern Plans: a segmented switch turns every card in a wave, click-only.
 *
 * The cards turn through PB's own toggle buttons, so PB keeps inert and
 * aria-hidden on the face-down side. PB's hover and focus sources are stopped
 * on their way to PB's listeners; the CSS removes the matching flips. Without
 * this script the cards turn as PB's default and no switch is added.
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

  /**
   * Builds the switch and turns every card when it changes.
   *
   * @param {HTMLElement} root
   *   The Pattern's root.
   * @param {HTMLElement} grid
   *   The `.pb-grid__wrapper` element.
   */
  function addSwitch(root, grid) {
    const reduced = window.matchMedia(
      '(prefers-reduced-motion: reduce)',
    ).matches;
    const group = document.createElement('div');
    group.className = 'pb-pattern-plans__switch';
    group.setAttribute('role', 'group');
    group.setAttribute('aria-label', Drupal.t('Show on every card'));
    const buttons = [Drupal.t('Overview'), Drupal.t('What is included')].map(
      (text, index) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'control-btn';
        button.textContent = text;
        button.setAttribute('aria-pressed', index === 0 ? 'true' : 'false');
        group.append(button);
        return button;
      },
    );
    let timers = [];
    buttons.forEach((button, side) => {
      button.addEventListener('click', () => {
        buttons.forEach((item, i) => {
          item.setAttribute('aria-pressed', i === side ? 'true' : 'false');
        });
        timers.forEach(clearTimeout);
        timers = [];
        root.querySelectorAll('.pb-3d-flip-box__scene').forEach((scene, i) => {
          timers.push(
            setTimeout(
              () => {
                if (
                  scene.classList.contains('pb-is-flipped') !==
                  (side === 1)
                ) {
                  scene.querySelector('.pb-3d-flip-box__toggle')?.click();
                }
              },
              reduced ? 0 : i * 90,
            ),
          );
        });
      });
    });
    grid.before(group);
  }

  Drupal.behaviors.pbPatternGridPlans = {
    attach(context) {
      once('pb-pattern-grid-plans', '.pb-pattern--grid-plans', context).forEach(
        (root) => {
          holdClickOnly(root);
          const grid = root.querySelector('.pb-grid__wrapper');
          if (grid) {
            addSwitch(root, grid);
          }
        },
      );
    },
  };
})(Drupal, once);
