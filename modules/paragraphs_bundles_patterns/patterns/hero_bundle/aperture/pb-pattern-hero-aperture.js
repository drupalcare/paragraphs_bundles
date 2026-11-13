/**
 * @file
 * Pattern Aperture: opens the photo when in view, title rises word by word.
 *
 * Without this script the photo and title show normally. The heading keeps
 * its full text as the accessible name; the built word spans are hidden from
 * assistive technology.
 */

((Drupal, once) => {
  const RTL_LETTER =
    /[\p{Script=Arabic}\p{Script=Hebrew}\p{Script=Syriac}\p{Script=Thaana}\p{Script=Nko}]/u;
  const FIRST_LETTER = /\p{L}/u;

  /**
   * Wraps each word of a title's text in the two spans the CSS animates.
   *
   * @param {HTMLElement} title
   *   The `.pb-content__title` element.
   */
  function splitWords(title) {
    const walker = document.createTreeWalker(title, NodeFilter.SHOW_TEXT);
    const nodes = [];
    while (walker.nextNode()) {
      if (walker.currentNode.nodeValue.trim()) {
        nodes.push(walker.currentNode);
      }
    }
    const text = title.textContent.trim();
    if (!nodes.length || !text) {
      return;
    }
    title.setAttribute('aria-label', text);
    // Word boxes run in the page's direction, so English on an Arabic page
    // printed back to front. The pieces follow the text's own direction.
    const first = text.match(FIRST_LETTER);
    if (first) {
      const textDir = RTL_LETTER.test(first[0]) ? 'rtl' : 'ltr';
      if (window.getComputedStyle(title).direction !== textDir) {
        title.setAttribute('dir', textDir);
      }
    }
    let index = 0;
    nodes.forEach((node) => {
      const fragment = document.createDocumentFragment();
      node.nodeValue.split(/(\s+)/).forEach((part) => {
        if (!part.trim()) {
          fragment.append(part);
          return;
        }
        const word = document.createElement('span');
        word.className = 'pb-pattern-word';
        word.setAttribute('aria-hidden', 'true');
        const inner = document.createElement('span');
        inner.textContent = part;
        inner.style.setProperty('--pb-pattern-i', String(index));
        index += 1;
        word.append(inner);
        fragment.append(word);
      });
      node.replaceWith(fragment);
    });
  }

  Drupal.behaviors.pbPatternHeroAperture = {
    attach(context) {
      once(
        'pb-pattern-hero-aperture',
        '.pb-pattern--hero-aperture',
        context,
      ).forEach((root) => {
        const title = root.querySelector('.pb-content__title');
        const reduced = window.matchMedia(
          '(prefers-reduced-motion: reduce)',
        ).matches;
        if (title && !reduced) {
          splitWords(title);
        }
        root.setAttribute('data-pb-pattern-ready', '');
        const open = () => root.classList.add('pb-pattern-is-open');
        if (reduced || !('IntersectionObserver' in window)) {
          open();
          return;
        }
        const observer = new IntersectionObserver(
          (entries) => {
            if (entries[0].isIntersecting) {
              open();
              observer.disconnect();
            }
          },
          { threshold: 0.2 },
        );
        observer.observe(root);
      });
    },
  };
})(Drupal, once);
