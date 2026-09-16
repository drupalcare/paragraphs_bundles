/**
 * @file
 * The hover note that shows a section's id, and the New / Updated badge.
 *
 * NOTHING IS ADDED INSIDE A SECTION, and that is the whole design. The obvious
 * build — position: relative on the section, an absolute child — would create
 * a positioning context inside bundles that already position their own
 * children absolutely (image overlay, image background, the flip box) and move
 * them. So there is ONE fixed layer on the page, and the note and the badge are
 * placed into it from the hovered section's rectangle.
 *
 * THE IDS ARE ALREADY IN THE PAGE. PB renders every section as
 * `pb-{slug}-{id}` from its preprocess hook, and its own dynamic CSS targets
 * the same id, so this reads the DOM rather than being told anything.
 */

((Drupal, once) => {
  'use strict';

  // `pb-slideshow-186`, and not `pb-slideshow__stage-186`: only the outer
  // element of a section, never the parts inside it.
  const SECTION_ID = /^pb-[a-z0-9-]+-(\d+)$/;

  /**
   * The single fixed layer both the note and the badge live in.
   *
   * @return {HTMLElement} The layer.
   */
  const layer = () => {
    let el = document.querySelector('.pb-ai-layer');

    if (!el) {
      el = document.createElement('div');
      el.className = 'pb-ai-layer';
      document.body.appendChild(el);
    }

    return el;
  };

  /**
   * Places a floating element over a section.
   *
   * @param {HTMLElement} el The floating element.
   * @param {HTMLElement} section The section it belongs to.
   */
  const place = (el, section) => {
    const box = section.getBoundingClientRect();
    el.style.left = `${Math.max(8, box.left + 8)}px`;
    el.style.top = `${Math.min(window.innerHeight - 8, box.bottom - 8)}px`;
  };

  Drupal.behaviors.pbAiSections = {
    attach(context) {
      const sections = once('pb-ai-section', '[id^="pb-"]', context).filter((el) =>
        SECTION_ID.test(el.id),
      );

      if (!sections.length) {
        return;
      }

      const note = document.createElement('span');
      note.className = 'pb-ai-note';
      note.hidden = true;
      layer().appendChild(note);

      let hovered = null;

      const show = (section) => {
        hovered = section;
        note.textContent = section.id;
        note.hidden = false;
        place(note, section);
      };

      const hide = () => {
        hovered = null;
        note.hidden = true;
      };

      sections.forEach((section) => {
        section.addEventListener('mouseenter', () => show(section));
        section.addEventListener('mouseleave', hide);
        // Hover alone leaves out keyboards and touch screens.
        section.addEventListener('focusin', () => show(section));
        section.addEventListener('focusout', hide);
      });

      // The note follows its section rather than floating away from it.
      const follow = () => {
        if (hovered && !note.hidden) {
          place(note, hovered);
        }
      };

      window.addEventListener('scroll', follow, { passive: true });
      window.addEventListener('resize', follow, { passive: true });

      this.mark(sections);
    },

    /**
     * Marks the section the assistant just created or changed.
     *
     * The mark comes from the URL rather than from the rendered page, so
     * nothing person-specific is ever baked into a cached page: one visitor's
     * "New" can never be served to the next.
     *
     * @param {Array} sections Every section element on the page.
     */
    mark(sections) {
      const params = new URLSearchParams(window.location.search);
      const updated = params.get('pb-ai-updated');
      const added = params.get('pb-ai-added');

      let target = null;
      let label = '';

      if (updated) {
        target = sections.find((el) => el.id.endsWith(`-${updated}`)) || null;
        label = Drupal.t('Updated');
      } else if (added !== null) {
        // An added section is appended, so it is the last one on the page.
        target = sections[sections.length - 1] || null;
        label = Drupal.t('New');
      }

      if (!target) {
        return;
      }

      const badge = document.createElement('button');
      badge.type = 'button';
      badge.className = 'pb-ai-badge';
      badge.textContent = label;
      badge.title = Drupal.t('Dismiss');
      layer().appendChild(badge);
      place(badge, target);

      const keepUp = () => place(badge, target);
      window.addEventListener('scroll', keepUp, { passive: true });
      window.addEventListener('resize', keepUp, { passive: true });

      // It stays until it is clicked or the page is left — the way core marks
      // a new comment — rather than fading while somebody is still reading.
      badge.addEventListener('click', () => {
        badge.remove();
        window.removeEventListener('scroll', keepUp);
        window.removeEventListener('resize', keepUp);
      });

      target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    },
  };
})(Drupal, once);
