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

  // PB puts `paragraphs-bundles` on that same outer element, from preprocess,
  // so a theme overriding a bundle's template keeps it. Requiring the class as
  // well as the id shape means the tooltip lands on the section itself rather
  // than on anything else that happens to be named `pb-…`.
  const SECTIONS = '.paragraphs-bundles[id^="pb-"]';

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
   * Places the id tooltip ON the section's top-left corner.
   *
   * INSIDE the section, not floating above it. Anchoring above meant that for
   * a tall section, or one whose top had scrolled past, the tooltip ended up
   * clamped to the top of the window — far from the thing it was naming.
   *
   * @param {HTMLElement} el The floating element.
   * @param {HTMLElement} section The section it belongs to.
   */
  const place = (el, section) => {
    const box = section.getBoundingClientRect();
    el.style.left = `${Math.max(8, box.left + 8)}px`;
    // Sits just inside the top edge; if that edge has scrolled off, it stays
    // at the top of the section's visible part rather than leaving the page.
    el.style.top = `${Math.min(
      Math.max(8, box.top + 8),
      Math.max(8, box.bottom - el.offsetHeight - 8),
    )}px`;
  };

  /**
   * Lays a floating outline exactly over a section.
   *
   * @param {HTMLElement} el The outline.
   * @param {HTMLElement} section The section to cover.
   */
  const cover = (el, section) => {
    const box = section.getBoundingClientRect();
    el.style.left = `${box.left}px`;
    el.style.top = `${box.top}px`;
    el.style.width = `${box.width}px`;
    el.style.height = `${box.height}px`;
  };

  /**
   * The section element carrying a given paragraph id.
   *
   * @param {string} id The paragraph id.
   *
   * @return {HTMLElement|null} The section, or null.
   */
  const sectionById = (id) =>
    document.querySelector(`[id^="pb-"][id$="-${id}"]`);

  /**
   * Shows "how many inside it" only for sections that hold other sections.
   *
   * Which sections nest is a fact about this site's configuration, so it is
   * computed on the server and handed over in drupalSettings — the script
   * decides nothing, it only reveals what the profile already knows. Asking
   * "how many slides" for a section that cannot hold one is a question with no
   * right answer.
   */
  Drupal.behaviors.pbAiCount = {
    attach(context) {
      const selects = once('pb-ai-count', '.pb-ai-bar select[name="section"]', context);

      if (!selects.length) {
        return;
      }

      const settings = drupalSettings.pbAi || {};
      const nests = settings.nests || {};
      const byHand = settings.byHand || {};
      const hints = settings.hints || {};

      selects.forEach((select) => {
        const bar = select.closest('.pb-ai-bar');
        const wrapper = bar.querySelector('.pb-ai-bar__count');

        // The box a person types into, and the sentence it started with. The
        // original is kept so that clearing the dropdown restores it rather
        // than leaving the last section's advice under an empty choice.
        const words = bar.querySelector('textarea[name="words"]');
        const generic = words ? words.placeholder : '';

        // Said at the moment of choosing: some sections need a view, a webform
        // or an image, and none of those can be written.
        let notice = bar.querySelector('.pb-ai-bar__needs');

        if (!notice) {
          notice = document.createElement('p');
          notice.className = 'pb-ai-bar__needs';
          notice.hidden = true;
          const intro = bar.querySelector('.pb-ai-bar__intro');
          intro.parentNode.insertBefore(notice, intro.nextSibling);
        }

        // WHICH BUNDLES THE CHOSEN FIELD WILL TAKE. A body field that accepts
        // anything and a footer restricted to two bundles do not share one Add
        // list, so the server sends both lists and this narrows the dropdown
        // to the chosen one. It is a convenience only: the callback re-derives
        // the real list and refuses anything outside it, so a browser without
        // this script is restricted just the same, only later.
        const where = bar.querySelector('select[name="target_field"]');

        const narrow = () => {
          if (!where) {
            return;
          }

          const allow = (drupalSettings.pbAi || {}).allowed || {};
          const usable = allow[where.value] || null;

          Array.from(select.options).forEach((option) => {
            // The empty option always stays: it is how a person clears the
            // choice, and it names no bundle.
            const off = Boolean(option.value) && usable !== null && !usable.includes(option.value);
            option.hidden = off;
            option.disabled = off;
          });

          // AND THE CATEGORY GOES WITH ITS LAST OPTION. Hiding every option in
          // a group leaves the group's heading behind — a category a person can
          // see and cannot use. The maintainer's rule: "if the category empty
          // dont show it". Hiding the options is not enough to do that, because
          // an optgroup is drawn from its own label, not from its children.
          Array.from(select.querySelectorAll('optgroup')).forEach((group) => {
            const usableLeft = Array.from(group.children).some((option) => !option.hidden);
            group.hidden = !usableLeft;
            group.disabled = !usableLeft;
          });

          // A section already chosen that the new field will not take has to
          // go, or the dropdown would keep showing a choice that is about to
          // be refused.
          const chosen = select.selectedOptions[0];

          if (chosen && chosen.disabled) {
            select.value = '';
            select.dispatchEvent(new Event('change', { bubbles: true }));
          }
        };

        const sync = () => {
          if (wrapper) {
            const holds = Boolean(nests[select.value]);
            wrapper.hidden = !holds;

            if (!holds) {
              const input = wrapper.querySelector('input');

              if (input) {
                input.value = 0;
              }
            }
          }

          const warning = byHand[select.value];
          notice.textContent = warning || '';
          notice.hidden = !warning;

          // WHAT TO TYPE, for the section just chosen. A placeholder is the one
          // place a person is already looking when they are about to type, so
          // it is where "a hero has a body and a link title" belongs. It never
          // touches what they have written — only the hint behind it.
          if (words) {
            words.placeholder = hints[select.value] || generic;
          }
        };

        select.addEventListener('change', sync);

        if (where) {
          where.addEventListener('change', narrow);
          narrow();
        }

        sync();
      });
    },
  };

  /**
   * Outlines the section chosen in the Edit dropdown.
   *
   * Seeing which section you are about to change, before changing it, is the
   * difference between an id and a place on the page. The outline is drawn in
   * the floating layer and never as a border on the section: a border there
   * would move the page, and inside image overlay, image background and the
   * flip box it would move their contents as well.
   */
  Drupal.behaviors.pbAiHighlight = {
    attach(context) {
      const selects = once('pb-ai-highlight', '.pb-ai-bar select[name="existing"]', context);

      if (!selects.length) {
        return;
      }

      let outline = document.querySelector('.pb-ai-highlight');

      if (!outline) {
        outline = document.createElement('div');
        outline.className = 'pb-ai-highlight';
        outline.hidden = true;
        layer().appendChild(outline);
      }

      selects.forEach((select) => {
        let chosen = null;

        const sync = () => {
          chosen = select.value ? sectionById(select.value) : null;

          if (!chosen) {
            outline.hidden = true;
            return;
          }

          outline.hidden = false;
          cover(outline, chosen);
          chosen.scrollIntoView({ behavior: 'smooth', block: 'center' });
        };

        const follow = () => {
          if (chosen && !outline.hidden) {
            cover(outline, chosen);
          }
        };

        select.addEventListener('change', sync);
        window.addEventListener('scroll', follow, { passive: true });
        window.addEventListener('resize', follow, { passive: true });
        sync();
      });
    },
  };

  Drupal.behaviors.pbAiSections = {
    attach(context) {
      const sections = once('pb-ai-section', SECTIONS, context).filter((el) =>
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

      const wrapper = document.createElement('span');
      wrapper.className = 'pb-ai-mark';

      const badge = document.createElement('button');
      badge.type = 'button';
      badge.className = 'pb-ai-badge';
      badge.textContent = label;
      badge.title = Drupal.t('Dismiss');
      wrapper.appendChild(badge);

      // Undo is core's revert form, linked rather than reimplemented: it
      // carries its own permission check, which a bespoke undo would have to
      // repeat and could get wrong.
      // THE URL IS NOT THE SERVER. This value only ever comes from
      // revertUrl(), which produces a root-relative path — but it arrives
      // through the query string, where anyone can put anything. Assigning it
      // to href unchecked turned a link somebody was just invited to click
      // into script execution: `?pb-ai-added=&pb-ai-revert=javascript:…` was
      // enough, because mark() runs on a bare `pb-ai-added`.
      //
      // So: one leading slash and not a second. That admits the real paths
      // and refuses `javascript:`, `data:`, and protocol-relative `//host`,
      // without this script needing to know the site's own origin.
      const revert = params.get('pb-ai-revert');

      if (revert && /^\/[^/\\]/.test(revert)) {
        const undo = document.createElement('a');
        undo.className = 'pb-ai-undo';
        undo.href = revert;
        undo.textContent = Drupal.t('Undo');
        wrapper.appendChild(undo);
      }

      layer().appendChild(wrapper);
      place(wrapper, target);

      // THE WRAPPER IS WHAT MOVES, not the badge inside it. `.pb-ai-mark`
      // carries `position: fixed`; `.pb-ai-badge` carries none, so setting
      // left/top on the badge set two properties that do nothing and the mark
      // stayed where the page had scrolled away from.
      const keepUp = () => place(wrapper, target);
      window.addEventListener('scroll', keepUp, { passive: true });
      window.addEventListener('resize', keepUp, { passive: true });

      // It stays until it is clicked or the page is left — the way core marks
      // a new comment — rather than fading while somebody is still reading.
      // Dismiss takes the WHOLE mark: removing just the button left the Undo
      // link floating on the page with nothing to remove it and no handler
      // left to move it.
      badge.addEventListener('click', () => {
        wrapper.remove();
        window.removeEventListener('scroll', keepUp);
        window.removeEventListener('resize', keepUp);
      });

      target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    },
  };

  /**
   * The best-practice brief, sliding down from the top.
   *
   * THE BAR COMES UP FROM THE BOTTOM AND THIS COMES DOWN FROM THE TOP, so with
   * both open the page is still readable in the band between them. Neither
   * pushes the document: both are fixed to their own edge.
   *
   * THE TEXT IS THE BUNDLE'S OWN DESCRIPTION — one text, two readers. The
   * person reads it here before typing; the model is handed the same words as
   * its instruction. A second field would be a second place to edit.
   *
   * The X is an inline SVG rather than a file, so it renders even where a theme
   * or a CSP blocks the module's own assets. The one control that closes this
   * must never be the thing that fails.
   */
  Drupal.behaviors.pbAiBrief = {
    attach(context) {
      const selects = once('pb-ai-brief', '.pb-ai-bar select[name="section"]', context);

      if (!selects.length) {
        return;
      }

      const KEY = 'pbAiBriefOff';
      const CLOSE = '<svg viewBox="0 -960 960 960" aria-hidden="true">'
        + '<path d="M256-200l-56-56 224-224-224-224 56-56 224 224 224-224 56 56-224 224 '
        + '224 224-56 56-224-224-224 224Z"/></svg>';

      let panel = document.querySelector('.pb-ai-brief');

      if (!panel) {
        panel = document.createElement('div');
        panel.className = 'pb-ai-brief';
        // THREE ROWS, AND THE CLOSE BUTTON SITS IN THE FIRST ONE. There is no
        // separate head bar: the name used to live there and is now part of
        // row one, so a head would have shown it twice.
        panel.innerHTML = '<div class="pb-ai-brief__card">'
          + '<div class="pb-ai-brief__body">'
          + '<div class="pb-ai-brief__meta">'
          + '<p class="pb-ai-brief__about"></p>'
          + '<button type="button" class="pb-ai-brief__close">' + CLOSE + '</button>'
          + '</div>'
          + '<p class="pb-ai-brief__heading"></p>'
          + '<ul class="pb-ai-brief__list"></ul>'
          + '</div></div>';
        document.body.appendChild(panel);
      }

      const body = panel.querySelector('.pb-ai-brief__body');
      const about = panel.querySelector('.pb-ai-brief__about');
      const heading = panel.querySelector('.pb-ai-brief__heading');
      const list = panel.querySelector('.pb-ai-brief__list');
      const closeBtn = panel.querySelector('.pb-ai-brief__close');

      closeBtn.setAttribute('aria-label', Drupal.t('Close'));

      const close = () => panel.classList.remove('pb-ai-brief--open');

      selects.forEach((select) => {
        const bar = select.closest('.pb-ai-bar');
        const box = bar.querySelector('.pb-ai-bar__switch input[type="checkbox"]');

        // Stored as "off" so the default — no stored value — is ON. Somebody
        // who has never built this section is exactly who this is for, and
        // they will not go looking for a switch to turn it on.
        if (box) {
          try {
            box.checked = localStorage.getItem(KEY) !== '1';
          } catch (error) {
            box.checked = true;
          }
        }

        const show = () => {
          const briefs = (drupalSettings.pbAi || {}).briefs || {};
          const brief = briefs[select.value];

          if (!brief || (box && !box.checked)) {
            close();

            return;
          }

          const chosen = select.selectedOptions[0];
          const name = chosen ? chosen.textContent.trim() : '';

          // ROW ONE: category, name, and what it is for — one line, with the
          // close button at its right end.
          //
          // Built as elements and never as innerHTML: this text is a site's
          // own config value, and innerHTML would let whatever is in it run.
          //
          // ⚠ NOT `body.textContent = ''`. The body now HOLDS the three rows,
          // so clearing it would delete the panel's own structure along with
          // the close button. Only the parts that vary are cleared.
          about.textContent = '';

          const facts = [];

          if (brief.category) {
            facts.push([Drupal.t('Category:'), brief.category]);
          }

          if (name) {
            facts.push([Drupal.t('Name:'), name]);
          }

          if (brief.good_for) {
            facts.push([Drupal.t('About:'), brief.good_for]);
          }

          facts.forEach((fact, index) => {
            if (index > 0) {
              const separator = document.createElement('span');
              separator.className = 'pb-ai-brief__sep';
              // A REAL SPACE EITHER SIDE, not CSS padding. Padding would look
              // right and still read as "structure ·Name:" to a screen reader,
              // which announces the text content and never the box around it.
              separator.textContent = ' · ';
              about.appendChild(separator);
            }

            const key = document.createElement('span');
            key.className = 'pb-ai-brief__key';
            key.textContent = fact[0];
            about.appendChild(key);
            about.appendChild(document.createTextNode(' ' + fact[1] + ' '));
          });

          // ROWS TWO AND THREE. The first line of the practices is the
          // heading; every line after it is one item in the list.
          //
          // TAKEN BY POSITION, NEVER BY MATCHING THE ENGLISH. Testing for
          // "Best practices…" would work in one language and nowhere else —
          // the same defect removed from the PHP, where warning severity was
          // recovered with str_starts_with($note, 'CAREFUL:').
          const lines = String(brief.practices || '')
            .split('\n')
            .map((line) => line.trim())
            .filter((line) => line !== '');

          heading.textContent = lines.length > 0 ? lines[0] : '';

          list.textContent = '';
          lines.slice(1).forEach((line) => {
            const item = document.createElement('li');
            item.textContent = line;
            list.appendChild(item);
          });

          body.scrollTop = 0;
          panel.classList.add('pb-ai-brief--open');
        };

        select.addEventListener('change', show);

        if (box) {
          box.addEventListener('change', () => {
            try {
              localStorage.setItem(KEY, box.checked ? '0' : '1');
            } catch (error) {
              // A private window, or storage switched off. The switch still
              // works; it just will not be remembered.
            }

            box.checked ? show() : close();
          });
        }
      });

      closeBtn.addEventListener('click', close);

      document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          close();
        }
      });
    },
  };

  /**
   * Hides the bar and brings it back.
   *
   * SHOWN IS THE DEFAULT, and the stored value can only take it away. An
   * editor who has never touched the toggle sees the bar exactly as it always
   * was; only somebody who chose to hide it gets it hidden on the next page.
   *
   * The choice is remembered per browser because an editor adding five
   * sections should not have to hide it five times. Nothing depends on it:
   * every access decision is made on the server, and a browser that refuses
   * storage simply starts shown each time.
   *
   * `inert` GOES ON THE FORM, NOT THE BAR. The bar keeps the toggle, which has
   * to stay clickable while everything else is off screen — marking the whole
   * bar inert would disable the one control that brings it back.
   */
  Drupal.behaviors.pbAiToggle = {
    attach(context) {
      const buttons = once('pb-ai-toggle', '.pb-ai-toggle', context);

      if (!buttons.length) {
        return;
      }

      const KEY = 'pbAiBarHidden';

      buttons.forEach((button) => {
        const panel = button.closest('.pb-ai');

        if (!panel) {
          // A toggle with no bar around it would be a button that does
          // nothing, which is worse than no button.
          button.remove();

          return;
        }

        const apply = (hidden) => {
          panel.classList.toggle('pb-ai--hidden', hidden);
          button.setAttribute('aria-expanded', hidden ? 'false' : 'true');

          const form = panel.querySelector('.pb-ai-bar');

          if (form) {
            form.inert = hidden;
          }

          try {
            localStorage.setItem(KEY, hidden ? '1' : '0');
          } catch (error) {
            // A private window, or storage switched off. The bar still hides;
            // it just will not be remembered.
          }
        };

        let hidden = false;

        try {
          hidden = localStorage.getItem(KEY) === '1';
        } catch (error) {
          hidden = false;
        }

        apply(hidden);

        button.addEventListener('click', () => {
          apply(!panel.classList.contains('pb-ai--hidden'));
        });

        // Escape puts it away, the way every other dismissible thing on the
        // web behaves. Focus moves to the toggle, or it would be left inside
        // a form that is no longer reachable.
        document.addEventListener('keydown', (event) => {
          if (event.key === 'Escape' && !panel.classList.contains('pb-ai--hidden')) {
            apply(true);
            button.focus();
          }
        });
      });
    },
  };
})(Drupal, once);
