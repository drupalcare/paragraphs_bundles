/**
 * @file
 * Pattern Figures: numbers count up once.
 *
 * Without this script the tiles still turn and read as plain text.
 */

((Drupal, once) => {
  /**
   * Finds the number on a title's text node, plain digits only.
   *
   * @param {HTMLElement} title
   *   The title element.
   *
   * @return {{node: Text, value: number}|null}
   *   The text node and its number.
   */
  function numberNode(title) {
    const walker = document.createTreeWalker(title, NodeFilter.SHOW_TEXT);
    while (walker.nextNode()) {
      const text = walker.currentNode.nodeValue.trim();
      if (text) {
        return /^\d+$/.test(text)
          ? { node: walker.currentNode, value: Number(text) }
          : null;
      }
    }
    return null;
  }

  /**
   * Counts a text node up to its number once.
   *
   * @param {Text} node
   *   The text node.
   * @param {number} value
   *   The number to reach.
   */
  function countUp(node, value) {
    const duration = 1400;
    let start = 0;
    const step = (time) => {
      start = start || time;
      const progress = Math.min((time - start) / duration, 1);
      const eased = 1 - (1 - progress) ** 3;
      node.nodeValue = String(Math.round(value * eased));
      if (progress < 1) {
        window.requestAnimationFrame(step);
      }
    };
    node.nodeValue = '0';
    window.requestAnimationFrame(step);
  }

  Drupal.behaviors.pbPatternGridFigures = {
    attach(context) {
      once(
        'pb-pattern-grid-figures',
        '.pb-pattern--grid-figures',
        context,
      ).forEach((root) => {
        const reduced = window.matchMedia(
          '(prefers-reduced-motion: reduce)',
        ).matches;
        const counters = [];
        root.querySelectorAll('.pb-3d-flip-box').forEach((card) => {
          const title = card.querySelector(
            '.pb-3d-flip-box__front .pb-content__title',
          );
          if (!title) {
            return;
          }
          const found = numberNode(title);
          if (found && !reduced) {
            counters.push(found);
          }
        });
        const reveal = () => {
          root.classList.add('pb-pattern-is-in');
          counters.forEach(({ node, value }) => countUp(node, value));
        };
        if (reduced || !('IntersectionObserver' in window)) {
          reveal();
          return;
        }
        const observer = new IntersectionObserver(
          (entries) => {
            if (entries[0].isIntersecting) {
              reveal();
              observer.disconnect();
            }
          },
          { threshold: 0.3 },
        );
        observer.observe(root);
      });
    },
  };
})(Drupal, once);
