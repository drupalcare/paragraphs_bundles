/**
 * @file
 * Paragraph Bundle 3D Carousel.
 *
 * Two 3D geometries with autoplay, keyboard navigation and touch: a rotating
 * ring (a cylinder whose slides are the faces of an N-gon) and a coverflow
 * (an open fan, centre card forward). The style is chosen per paragraph and
 * read from `data-pb-style`, which is emitted only for coverflow so the ring's
 * markup is unchanged.
 *
 * THE TWO STYLES END DIFFERENTLY, ON PURPOSE.
 *
 * The COVERFLOW loops: the cards wrap and the count starts over, because a
 * card sliding from the last position to the first is one step like any other.
 *
 * The RING ping-pongs: it reverses at each end and hides the arrow there. It
 * cannot loop, because its slides are the faces of a cylinder — returning from
 * the last to the first is a full revolution through every intermediate face
 * inside one transition, which reads as a violent spin rather than a loop. At
 * ten slides it looks broken. This is why the original removed it.
 *
 * Filename:     paragraph-bundle-3d-carousel.js
 * Website:      https://www.flashwebcenter.com
 * Developer:    Alaa Haddad https://www.alaahaddad.com
 */
// phpcs:disable Drupal.NamingConventions.UpperCaseConstant
// phpcs:disable Generic.PHP.UpperCaseConstant
// phpcs:disable Squiz.WhiteSpace.OperatorSpacing

((Drupal, once) => {
  const PARAGRAPHS_BUNDLES = 'paragraphs-bundles';
  const PB_3D_CAROUSEL = 'pb-3d-carousel';
  const PB_3D_CAROUSEL__RING = 'pb-3d-carousel__ring';
  const PB_3D_CAROUSEL__SLIDE = 'pb-3d-carousel__slide';
  const PB_NAV__PLAY = 'pb-nav__play';
  const PB_NAV__PAUSE = 'pb-nav__pause';
  const PB_NAV__PLAY_ICON = 'pb-nav__play-icon';
  const PB_NAV__PAUSE_ICON = 'pb-nav__pause-icon';
  const PB_NAV__PREV = 'pb-nav__prev';
  const PB_NAV__NEXT = 'pb-nav__next';
  const PB_IS_ACTIVE = 'pb-is-active';

  const PARAGRAPHS_BUNDLES_PB_3D_CAROUSEL = `.${PARAGRAPHS_BUNDLES}.${PB_3D_CAROUSEL}`;
  const PARAGRAPHS_BUNDLES_PB_3D_CAROUSEL_PB_3D_CAROUSEL__RING = `${PARAGRAPHS_BUNDLES_PB_3D_CAROUSEL} .${PB_3D_CAROUSEL__RING}`;

  const PB_3D_CAROUSEL__RING_OWNED_SLIDE = `:scope > .${PB_3D_CAROUSEL__SLIDE}`;

  const ONCE_PB_PARAGRAPH_BUNDLE_3D_CAROUSEL_RING =
    'pb-paragraph-bundle-3d-carousel-ring';

  /**
   * Debounce utility to limit function execution frequency.
   *
   * @param {Function} func - The function to debounce.
   * @param {number} delay - Delay in milliseconds.
   * @return {Function} Debounced function.
   */
  const debounce = (func, delay) => {
    let timer;

    const run = (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => func(...args), delay);
    };

    // 🚨 CANCELLABLE, because teardown has to be able to stop it. Without
    // this, a callback already scheduled when detach() runs still fires up to
    // `delay` later — on an element the behaviour has released — and the
    // resize path can then schedule a fresh timer and restart autoplay with
    // nothing left to clear it.
    run.cancel = () => {
      clearTimeout(timer);
    };

    return run;
  };

  /** @type {WeakMap<HTMLElement, () => void>} */
  const ringCleanupHandlers = new WeakMap();

  /**
   * Checks if an element is mostly visible in the viewport.
   *
   * @param {HTMLElement} element - The element to check.
   * @return {boolean} True if more than 20% of the element is visible.
   */
  const isMostlyVisible = (element) => {
    const rect = element.getBoundingClientRect();
    if (rect.height <= 0) {
      return false;
    }
    const visibleHeight =
      Math.min(rect.bottom, window.innerHeight) - Math.max(rect.top, 0);
    return visibleHeight / rect.height > 0.2;
  };

  Drupal.behaviors.paragraphBundle3dCarousel = {
    attach(context) {
      const rings = once(
        ONCE_PB_PARAGRAPH_BUNDLE_3D_CAROUSEL_RING,
        PARAGRAPHS_BUNDLES_PB_3D_CAROUSEL_PB_3D_CAROUSEL__RING,
        context,
      );

      rings.forEach((ring) => {
        const abortController = new AbortController();
        const { signal } = abortController;

        const ringId = ring.id;
        const uniqueId = ringId.replace(/^pb-3d-carousel__ring-/, '');
        const cells = ring.querySelectorAll(PB_3D_CAROUSEL__RING_OWNED_SLIDE);
        const cellCount = cells.length;

        if (cellCount === 0) {
          return;
        }

        const root = ring.closest(PARAGRAPHS_BUNDLES_PB_3D_CAROUSEL);
        const slideNumberElement = document.getElementById(
          `pb-3d-carousel__index-${uniqueId}`,
        );
        const prevButton = document.getElementById(
          `pb-3d-carousel__prev-${uniqueId}`,
        );
        const nextButton = document.getElementById(
          `pb-3d-carousel__next-${uniqueId}`,
        );
        const playPauseButton = document.getElementById(
          `pb-3d-carousel__play-pause-${uniqueId}`,
        );
        const playIcon =
          playPauseButton?.querySelector(`:scope > .${PB_NAV__PLAY_ICON}`) ??
          null;
        const pauseIcon =
          playPauseButton?.querySelector(`:scope > .${PB_NAV__PAUSE_ICON}`) ??
          null;

        // WHICH GEOMETRY, read from the markup rather than from a setting the
        // script could disagree with. The attribute is emitted only for
        // coverflow, so a ring renders from exactly the markup it always had.
        const style =
          ring.getAttribute('data-pb-style') === 'coverflow'
            ? 'coverflow'
            : 'ring';

        // The coverflow look. Fixed values, the same kind of constant as the
        // ring's `perspective: 40rem` — none of them is a setting, and none of
        // them caps how many slides an editor may add.
        // How much of the slide's own width the CENTRE card occupies. The
        // slide's layout box is whatever the width, height and breakpoint
        // settings made it; this shrinks it by transform alone so there is
        // room either side for its neighbours. Nothing here overrides CSS.
        const CF_CENTRE_SCALE = 0.62;
        const CF_SPACING = 0.72;
        const CF_TILT = 45;
        const CF_DEPTH = 120;
        const CF_SHRINK = 0.16;
        const CF_MIN_SCALE = 0.3;

        let currentIndex = 0;

        // RING ONLY. Which way the drum is travelling, reversed at each end.
        // The coverflow never reads it: it wraps, and takes its step as an
        // argument so a Prev click cannot leave autoplay running backwards.
        let direction = 1;
        let isPaused = false;
        let intervalId = null;
        let previousVisibility = isMostlyVisible(ring);
        let touchStartX = 0;
        let touchEndX = 0;
        let lastKnownWidth = window.innerWidth;
        let resizeTimeout = null;

        const rotationDelay =
          Number.parseInt(ring.getAttribute('data-rotation-delay'), 10) || 0;

        if (rotationDelay <= 0) {
          isPaused = true;
        }

        // Respect prefers-reduced-motion: start paused so autoplay never runs
        // automatically; the play/pause control still lets users opt in.
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
          isPaused = true;
        }

        /**
         * @return {boolean}
         */
        const prefersReducedMotion = () =>
          window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        /**
         * Clears autoplay interval.
         */
        const pauseAutoPlay = () => {
          if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
          }
        };

        /**
         * Updates play/pause button visuals.
         *
         * @param {boolean} paused - Whether autoplay is paused.
         */
        const updatePlayPauseButton = (paused) => {
          if (!playPauseButton) {
            return;
          }

          playPauseButton.classList.toggle(PB_NAV__PAUSE, !paused);
          playPauseButton.classList.toggle(PB_NAV__PLAY, paused);
          playIcon?.toggleAttribute('hidden', !paused);
          pauseIcon?.toggleAttribute('hidden', paused);
          playPauseButton.setAttribute(
            'aria-label',
            paused ? Drupal.t('Play slideshow') : Drupal.t('Pause slideshow'),
          );
          playPauseButton.setAttribute('aria-pressed', String(!paused));
        };

        /**
         * Updates tabindex for focusable elements within a cell.
         *
         * @param {HTMLElement} cell - Slide element.
         * @param {boolean} isActive - Whether the slide is active.
         */
        const updateFocusableElements = (cell, isActive) => {
          cell
            .querySelectorAll('a, button, input, textarea, select, [tabindex]')
            .forEach((el) => {
              el.setAttribute('tabindex', isActive ? '0' : '-1');
            });
        };

        /**
         * Updates prev/next button visibility at ends.
         */
        const updateNavVisibility = () => {
          // THE RING HAS ENDS AND SAYS SO. It ping-pongs, so at the first
          // slide there is nothing before and at the last nothing after, and
          // the arrow is hidden to show it.
          //
          // THE FAN HAS NONE. It wraps, so both controls always do something
          // and neither is ever hidden or taken out of the tab order.
          // Read BEFORE any arrow is hidden: Chrome can drop focus from an
          // element the moment it becomes `visibility: hidden`, and in vvjc
          // it did, so a read after the toggle returned <body> (measured
          // 2026-09-25). Here it happened to work; that was timing, not design.
          const focused = document.activeElement;

          const looping = style === 'coverflow';
          const hidePrev = !looping && currentIndex === 0;
          const hideNext = !looping && currentIndex === cellCount - 1;

          if (prevButton) {
            prevButton.classList.toggle('pb-is-hidden', hidePrev);
            prevButton.setAttribute('tabindex', hidePrev ? '-1' : '0');
          }

          if (nextButton) {
            nextButton.classList.toggle('pb-is-hidden', hideNext);
            nextButton.setAttribute('tabindex', hideNext ? '-1' : '0');
          }

          // 🚨 NEVER HIDE THE CONTROL THAT HOLDS FOCUS WITHOUT MOVING IT. The
          // hidden arrow is `visibility: hidden`, which the browser cannot
          // keep focused, so a keyboard user who pressed Enter to reach the
          // last slide was dropped to the top of the page (WCAG 2.4.3).
          // Inherited from v1, measured 2026-09-25. Focus moves to the
          // opposite arrow, which is the one that still does something.
          if (hideNext && focused === nextButton && prevButton && !hidePrev) {
            prevButton.focus();
          } else if (
            hidePrev &&
            focused === prevButton &&
            nextButton &&
            !hideNext
          ) {
            nextButton.focus();
          }
        };

        /**
         * Gets stable carousel geometry for the current slide count.
         *
         * @return {{theta: number, radius: number}}
         *   Rotation angle and Z radius.
         */
        const getCarouselGeometry = () => {
          if (cellCount <= 1) {
            return { theta: 0, radius: 0 };
          }

          const theta = 360 / cellCount;
          const radius = Math.round(
            ring.offsetWidth / 2 / Math.tan(Math.PI / cellCount),
          );

          return { theta, radius };
        };

        /**
         * Which cards are shown, and where the group sits — derived from the box.
         *
         * 🚨 THIS IS THE FIX FOR THREE FAILED LAYOUTS, and the failure was
         * never the constants. Every earlier version placed a card at
         * `(index - currentIndex) * step`, anchored on the CURRENT card. At
         * slide 1 every offset is positive, so the cards could only ever run
         * off to one side — a receding staircase with half the box empty.
         * Narrowing the cards changed the spacing and not the shape, which is
         * what proved the constants were not the problem.
         *
         * The maintainer, having looked at it: "it must load on the center".
         *
         * So the group is centered on ITS OWN midpoint rather than on the
         * current card. At slide 1 the run is clamped at the start, and the
         * room that would have been wasted on the empty left is handed to the
         * right instead, which keeps the arrangement centered and full at the
         * first slide, in the middle and at the last alike.
         *
         * NOTHING HERE IS A SETTING. How many cards fit comes from the box
         * width and the card width, both of which the browser already knows —
         * "the user adds as many as they want", so no count is ever asked for
         * and adding a twentieth slide changes nothing about the first.
         *
         * @return {{cardWidth: number, step: number, lo: number, hi: number, centre: number}}
         *   Card width and step in px, the first and last card shown, and the
         *   index the group is centered on.
         */
        const getCoverflowLayout = () => {
          // THE SLIDE'S REAL WIDTH, whatever the editor's width setting and
          // the breakpoint have made it. Nothing here assumes a size, which is
          // what lets the fan follow the settings instead of fighting them.
          const cardWidth = cells[0]?.offsetWidth || ring.offsetWidth;

          // SPLIT THE CHILDREN AROUND ONE CENTRE CARD. The maintainer's rule:
          // "we count the children, then we try to split, if it is 5 then
          // 2 1 2, but if it is 8 may be 3 1 4". Odd remainders go right.
          const left = Math.floor((cellCount - 1) / 2);
          const right = cellCount - 1 - left;

          const step = CF_SPACING * cardWidth * CF_CENTRE_SCALE;

          // HOW FAR OUT A CARD CAN BE AND STILL BE SEEN. A card beyond this is
          // painted and then clipped to nothing, so it must not stay in the
          // tab order: a keyboard user would otherwise walk every link in
          // every slide of a twelve-slide coverflow, landing on content that
          // cannot be scrolled into view.
          //
          // 🚨 MEASURE THE BOX THAT CLIPS, NOT THE RING. `overflow: hidden`
          // sits on the ROOT (.pb-3d-carousel), and BELOW the chosen
          // breakpoint on the inner as well — never on the ring. The ring is
          // routinely narrower than the root, so measuring it hides cards that
          // are plainly visible: on a 600px ring inside a wider root it gives
          // maxDist 1 and turns a 2|1|2 fan into 1|1|1.
          //
          // Which box clips is read from the cascade rather than assumed, so
          // this stays correct at every breakpoint. If neither clips, nothing
          // is hidden — the safe direction.
          const inner = ring.parentElement;
          const clipWidth = Math.min(
            root instanceof HTMLElement ? root.offsetWidth : Infinity,
            inner instanceof HTMLElement &&
              getComputedStyle(inner).overflow === 'hidden'
              ? inner.offsetWidth
              : Infinity,
          );

          // A card counts as seen while any of it is inside the clip, so half
          // a centre-scaled card is allowed past the edge.
          const reach = clipWidth / 2 + (cardWidth * CF_CENTRE_SCALE) / 2;
          const maxDist = Number.isFinite(reach)
            ? Math.max(1, Math.floor(reach / step))
            : cellCount;

          return { cardWidth, left, right, step, maxDist };
        };

        /**
         * Which card sits in a given slot, wrapping round the ends.
         *
         * 🚨 WRAPPING IS WHY THE CENTRING PROBLEM DISAPPEARS. Three layouts
         * failed trying to centre a one-sided fan, because at slide 1 there
         * was nothing to the left to draw. With the list treated as a loop
         * there is ALWAYS a card for every slot, so the centre card sits at
         * x = 0 on the first slide, the last slide and every slide between,
         * with neighbours on both sides. No special case, nothing to clamp.
         *
         * @param {number} slot - Slots from the centre; negative is left.
         *
         * @return {number} The cell index that belongs in that slot.
         */
        const coverflowIndexAt = (slot) =>
          (((currentIndex + slot) % cellCount) + cellCount) % cellCount;

        /**
         * Lays the cards on an open arc, centered in the box.
         *
         * Depth, tilt and scale come from the distance to the CURRENT card, so
         * that one reads as the front of the stack. The horizontal position
         * comes from the distance to the GROUP'S centre, which is what keeps
         * the arrangement in the middle of the box.
         */
        // Each card's slot at the previous layout, so a card that WRAPS can be
        // told apart from one that merely moves. Empty until the first layout.
        const lastSlot = new Map();

        /**
         * The stylesheet's transition duration for a card, in milliseconds.
         *
         * Read from the cascade rather than repeated here, so the wrap below
         * always runs on the same clock as the cards sliding beside it.
         *
         * @param {HTMLElement} cell - A card.
         *
         * @return {number} Duration in ms; 0 when none is declared.
         */
        let cssDuration = null;
        const cssDurationOf = (cell) => {
          // Memoised on first use. By construction that is the first wrap,
          // when the card still carries no inline transition of its own, so
          // this reads the stylesheet rather than an earlier override.
          if (cssDuration !== null) {
            return cssDuration;
          }
          cell.style.transition = '';
          const raw = getComputedStyle(cell).transitionDuration.split(',')[0];
          const value = parseFloat(raw);
          if (!Number.isFinite(value)) {
            cssDuration = 0;
            return 0;
          }

          cssDuration = raw.trim().endsWith('ms') ? value : value * 1000;
          return cssDuration;
        };

        const applyCoverflow = () => {
          const reducedMotion = prefersReducedMotion();

          // The centre card is shrunk by TRANSFORM, never by CSS width, so the
          // slide keeps the box the width, height and breakpoint settings gave
          // it. Everything else is measured from that shrunken width.
          //
          // ⚠ Nothing below may call setAttribute('style', …) or write
          // cssText. Every style change here is a CSSOM property write, which
          // is what keeps this widget clean under a Content-Security-Policy
          // with no 'unsafe-inline' — CSP governs the style CONTENT ATTRIBUTE,
          // not .style.prop mutations. The reason is invisible to a reader
          // looking only at this function, hence the note.
          const { left, right, step, maxDist } = getCoverflowLayout();

          // Slot first, card second. Every slot from -left to +right is filled
          // because the list wraps, so no side is ever empty.
          const placed = new Map();

          for (let slot = -left; slot <= right; slot++) {
            const dist = Math.abs(slot);
            const dir = Math.sign(slot);
            const scale = Math.max(
              CF_CENTRE_SCALE * (1 - dist * CF_SHRINK),
              CF_MIN_SCALE,
            );

            placed.set(coverflowIndexAt(slot), {
              slot,
              x: slot * step,
              z: -dist * CF_DEPTH,
              ry: -dir * CF_TILT,
              scale,
              dist,
            });
          }

          cells.forEach((cell, index) => {
            const at = placed.get(index);

            // UNREACHABLE BY CONSTRUCTION, and kept only as belt and braces.
            // The loop walks `cellCount` consecutive integers and
            // coverflowIndexAt() maps them through `mod cellCount`, so they
            // form a complete residue system: every index 0…cellCount-1 gets
            // exactly one slot, at every N including 1 and 2.
            //
            // ⚠ An earlier version of this comment said it "bites when a
            // duplicate index would be needed, i.e. very few slides". That was
            // false — no N produces a duplicate — and a wrong comment about an
            // unreachable branch is worse than no comment.
            if (!at) {
              cell.style.opacity = '0';
              cell.style.transition = reducedMotion ? 'none' : '';
              return;
            }

            // Beyond maxDist the card is clipped by the wrapper's
            // `overflow: hidden`. Hiding it here is what lets the a11y pass
            // below take opacity as its single source of truth for "on
            // screen", instead of repeating this arithmetic and drifting.
            // 🚨 A WRAPPING CARD MUST NOT SLIDE. The deck is a loop, so on
            // every step one card leaves one end and reappears at the other.
            // A normal transition animates that as a card flying across the
            // whole fan — measured 2026-09-25 on a twelve-card coverflow, card
            // 8 travelled 472px across the others and was still 20% opaque
            // half way. Instead it fades out, jumps while invisible, and fades
            // in at its new end, on the same clock as the cards around it.
            //
            // "Wrapping" is a slot change of more than half the deck: a card
            // that merely moves shifts by the step, one that wraps shifts by
            // the deck size minus the step, and only one of those can exceed
            // half.
            const previous = lastSlot.get(index);
            lastSlot.set(index, at.slot);
            const wraps =
              !reducedMotion &&
              previous !== undefined &&
              Math.abs(at.slot - previous) * 2 > cellCount;

            // ⚠ THE ORDER BELOW IS LOAD-BEARING. The transition must be set,
            // and the duration read, BEFORE the new transform is written.
            // Reading computed style after the write forces a style recalc
            // that starts the ordinary slide on the spot, and no later change
            // to `transition` cancels it — the first version of this fix did
            // exactly that and the card still flew across (measured).
            const duration = wraps ? cssDurationOf(cell) : 0;
            const animated =
              wraps && duration > 0 && typeof cell.animate === 'function';
            if (animated) {
              // The transform jumps at the half-way mark, when the fade below
              // has the card fully transparent.
              cell.style.transition = `transform 0s ${duration / 2}ms, opacity 0s`;
            } else {
              cell.style.transition = reducedMotion || wraps ? 'none' : '';
            }

            const fromOpacity = cell.style.opacity || '1';
            cell.style.opacity = at.dist > maxDist ? '0' : '1';
            cell.style.zIndex = String(100 - at.dist);
            cell.style.transform =
              `translateX(${at.x}px) ` +
              `translateZ(${at.z}px) ` +
              `rotateY(${at.ry}deg) ` +
              `scale(${at.scale})`;

            if (animated) {
              // Opacity is driven by this animation alone; when it ends, the
              // inline value written above takes over.
              cell.animate(
                [
                  { opacity: fromOpacity },
                  { opacity: 0, offset: 0.5 },
                  { opacity: cell.style.opacity },
                ],
                { duration, easing: 'ease-in-out' },
              );
            }
          });

          // THE RING ITSELF NEVER MOVES in a coverflow — the cards do. That is
          // the structural difference from the drum, where the ring carries
          // the rotation and the cells are fixed to it.
          ring.style.transform = '';
          ring.style.transition = reducedMotion ? 'none' : '';
        };

        /**
         * Updates the visible slide counter and its label.
         */
        const updateCounter = () => {
          if (!slideNumberElement) {
            return;
          }

          slideNumberElement.setAttribute(
            'aria-label',
            Drupal.t('Slide @current of @total', {
              '@current': currentIndex + 1,
              '@total': cellCount,
            }),
          );
          slideNumberElement.textContent = String(currentIndex + 1);
        };

        /**
         * Positions cells in the 3D ring.
         */
        const buildCarousel = () => {
          if (style === 'coverflow') {
            // ⚠ NO applyCoverflow() HERE. Both callers — attach and the resize
            // handler — call updateSlide() immediately after this, and that
            // lays the cards out. Doing it twice measured offsetWidth, wrote
            // every cell, then measured again: a forced synchronous layout
            // between two identical passes, for nothing.
            //
            // The ring branch below is NOT the same shape and must keep both
            // calls: there buildCarousel() positions the cells and
            // updateSlide() rotates the ring — different work, not repeated
            // work.
            cells.forEach((cell) => cell.classList.remove(PB_IS_ACTIVE));
            cells[0]?.classList.add(PB_IS_ACTIVE);
            return;
          }

          const { theta, radius } = getCarouselGeometry();

          const reducedMotion = prefersReducedMotion();

          cells.forEach((cell, index) => {
            cell.style.opacity = '1';
            cell.style.transform = `rotateY(${theta * index}deg) translateZ(${radius}px)`;
            cell.style.transition = reducedMotion ? 'none' : '';
          });

          ring.style.transition = reducedMotion ? 'none' : '';
          cells.forEach((cell) => cell.classList.remove(PB_IS_ACTIVE));
          cells[0]?.classList.add(PB_IS_ACTIVE);
          updateNavVisibility();
        };

        /**
         * Updates ring transform and slide state.
         */
        const updateSlide = () => {
          if (style === 'coverflow') {
            applyCoverflow();

            // SEVERAL CARDS ARE READABLE AT ONCE, so `inert` follows what is
            // actually on screen rather than what is centered. A coverflow
            // whose flanking cards were inert would look right and be
            // unusable.
            //
            // 🚨 CORRECTED: an earlier version marked EVERY card reachable,
            // reasoning that the split places them all. It does — but the
            // wrapper clips anything past `maxDist`, so a keyboard user walked
            // every link in all twelve slides and focus landed on content that
            // could not be brought into view. Placed is not the same as shown,
            // and `ACCESSIBILITY.md` promises off-screen slides stay out of
            // the tab order.
            //
            // applyCoverflow() has just written opacity, so that IS what is on
            // screen. Reading it back keeps one source of truth rather than
            // recomputing the distance here and letting the two drift.
            cells.forEach((cell, index) => {
              const shown = cell.style.opacity !== '0';

              cell.setAttribute('aria-hidden', String(!shown));
              cell.toggleAttribute('inert', !shown);
              updateFocusableElements(cell, shown);
              cell.classList.toggle(PB_IS_ACTIVE, index === currentIndex);
            });

            // Still one announcement, naming the centre card. Reading five
            // slides out at once would be worse, not better.
            updateCounter();
            updateNavVisibility();
            return;
          }

          const { theta, radius } = getCarouselGeometry();
          const angle = theta * currentIndex * -1;
          const reducedMotion = prefersReducedMotion();

          ring.style.transition = reducedMotion
            ? 'none'
            : 'transform 0.8s ease-in-out';
          ring.style.transform = `translateZ(${-radius}px) rotateY(${angle}deg)`;

          cells.forEach((cell, index) => {
            const isActive = index === currentIndex;
            cell.setAttribute('aria-hidden', String(!isActive));
            cell.toggleAttribute('inert', !isActive);
            updateFocusableElements(cell, isActive);
            cell.classList.toggle(PB_IS_ACTIVE, isActive);
          });

          updateCounter();
          updateNavVisibility();
        };

        /**
         * Moves one slide. The two geometries end differently, on purpose.
         *
         * @param {number} step - 1 forward, -1 back. The coverflow uses it
         *   directly; the ring keeps its own `direction` so autoplay can
         *   bounce, which is that geometry's original behaviour.
         */
        const advanceSlide = (step = 1) => {
          if (cellCount <= 1) {
            currentIndex = 0;
            direction = 1;
            updateSlide();
            return;
          }

          if (style === 'coverflow') {
            // THE FAN WRAPS. A card sliding from the last position to the
            // first is one step like any other, so the loop is invisible and
            // the count simply starts over. The step is an argument rather
            // than shared state, so a Prev click moves back once and leaves
            // autoplay travelling forwards.
            currentIndex = (currentIndex + step + cellCount) % cellCount;
            updateSlide();
            return;
          }

          // 🚨 THE RING PING-PONGS, AND THAT IS DELIBERATE — restored
          // 2026-09-24 after it was briefly made to wrap like the fan.
          //
          // The maintainer: "the loop in the old style was removed for a
          // reason, if you have 10 items, it loop fast and it looks like
          // broken, because you have to rotate all items in a second".
          //
          // It follows from the geometry. These slides are the faces of a
          // cylinder, so returning from the last to the first is not one step
          // — it is a full revolution through every intermediate face, inside
          // a single transition. It reads as a violent spin, not as a loop,
          // and the more slides there are the worse it looks. The fan has no
          // such problem because nothing rotates; the cards just move along.
          //
          // So the ring reverses at each end instead, and `direction` carries
          // that between ticks. updateNavVisibility() hides the arrow at each
          // end to show the user there is nothing further that way.
          currentIndex += direction;

          if (currentIndex >= cellCount) {
            currentIndex = cellCount - 2;
            direction = -1;
          } else if (currentIndex < 0) {
            currentIndex = 1;
            direction = 1;
          }

          updateSlide();
        };

        /**
         * Starts autoplay when allowed.
         */
        const startAutoPlay = () => {
          pauseAutoPlay();

          // NEVER START A TIMER FOR AN ELEMENT THAT HAS LEFT THE DOCUMENT.
          // This closes the whole class of problem rather than one path of it:
          // it covers a debounced callback firing after detach, and also a
          // third party removing the element without Drupal's detach ever
          // running, where nothing would be left to clear the interval.
          if (!ring.isConnected) {
            return;
          }
          if (
            cellCount > 1 &&
            rotationDelay > 0 &&
            !isPaused &&
            isMostlyVisible(ring)
          ) {
            intervalId = setInterval(() => advanceSlide(), rotationDelay);
          }
        };

        /**
         * Restarts autoplay after manual navigation.
         */
        const restartAutoPlay = () => {
          pauseAutoPlay();
          startAutoPlay();
        };

        /**
         * Manual navigation handler.
         *
         * @param {number} step - Direction step (-1 or 1).
         */
        const manualAdvance = (step) => {
          if (cellCount <= 1) {
            return;
          }

          // The RING carries direction between ticks, so a manual move also
          // sets which way autoplay then travels — that is its original
          // behaviour and the bounce depends on it.
          //
          // The FAN takes the step directly and never writes shared state, so
          // one Prev click cannot leave autoplay running backwards for ever.
          if (style !== 'coverflow') {
            direction = step;
          }

          advanceSlide(step);
          restartAutoPlay();
        };

        /**
         * Toggles autoplay.
         */
        const togglePlayPause = () => {
          isPaused = !isPaused;
          updatePlayPauseButton(isPaused);
          if (isPaused) {
            pauseAutoPlay();
          } else {
            restartAutoPlay();
          }
        };

        /**
         * Handles swipe gestures.
         */
        const handleSwipe = () => {
          const swipeThreshold = 50;
          if (touchStartX - touchEndX > swipeThreshold) {
            manualAdvance(1);
          } else if (touchEndX - touchStartX > swipeThreshold) {
            manualAdvance(-1);
          }
        };

        /**
         * Pauses or resumes autoplay based on viewport visibility.
         */
        const handleCarouselVisibility = () => {
          const currentlyVisible = isMostlyVisible(ring);
          if (currentlyVisible !== previousVisibility) {
            previousVisibility = currentlyVisible;
            if (currentlyVisible && !isPaused) {
              startAutoPlay();
            } else {
              pauseAutoPlay();
            }
          }
        };

        /**
         * Resets ring layout after significant resize.
         */
        const handleResizeOrRotate = () => {
          clearTimeout(resizeTimeout);
          resizeTimeout = setTimeout(() => {
            const newWidth = window.innerWidth;
            if (Math.abs(newWidth - lastKnownWidth) >= 50) {
              currentIndex = 0;
              direction = 1;
              buildCarousel();
              updateSlide();
              restartAutoPlay();
              lastKnownWidth = newWidth;
            }
          }, 200);
        };

        /**
         * Pauses autoplay when the tab is hidden.
         */
        const handleVisibilityChange = () => {
          if (document.hidden) {
            pauseAutoPlay();
          } else if (isMostlyVisible(ring) && !isPaused) {
            updateSlide();
            startAutoPlay();
          }
        };

        const debouncedHandleVisibility = debounce(
          handleCarouselVisibility,
          200,
        );
        const debouncedResize = debounce(() => {
          handleCarouselVisibility();
          handleResizeOrRotate();
        }, 200);

        buildCarousel();
        updatePlayPauseButton(isPaused);

        if (playPauseButton) {
          playPauseButton.style.display = 'flex';
        }

        updateSlide();

        if (previousVisibility && !isPaused) {
          startAutoPlay();
        }

        nextButton?.addEventListener('click', () => manualAdvance(1), {
          signal,
        });
        prevButton?.addEventListener('click', () => manualAdvance(-1), {
          signal,
        });
        playPauseButton?.addEventListener('click', togglePlayPause, { signal });
        playPauseButton?.addEventListener(
          'keydown',
          (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
              event.preventDefault();
              togglePlayPause();
            }
          },
          { signal },
        );

        ring.addEventListener(
          'keydown',
          (event) => {
            if (event.key === 'ArrowRight') {
              event.preventDefault();
              manualAdvance(1);
            } else if (event.key === 'ArrowLeft') {
              event.preventDefault();
              manualAdvance(-1);
            }
          },
          { signal },
        );

        ring.addEventListener('mouseenter', pauseAutoPlay, { signal });
        ring.addEventListener(
          'mouseleave',
          () => {
            if (!isPaused) {
              startAutoPlay();
            }
          },
          { signal },
        );

        ring.addEventListener(
          'touchstart',
          (event) => {
            touchStartX = event.touches[0].clientX;
          },
          { passive: true, signal },
        );
        ring.addEventListener(
          'touchmove',
          (event) => {
            touchEndX = event.touches[0].clientX;
          },
          { passive: true, signal },
        );
        ring.addEventListener('touchend', handleSwipe, { signal });

        document.addEventListener('scroll', debouncedHandleVisibility, {
          passive: true,
          signal,
        });
        window.addEventListener('orientationchange', debouncedResize, {
          signal,
        });
        window.addEventListener('resize', debouncedResize, { signal });
        document.addEventListener('visibilitychange', handleVisibilityChange, {
          signal,
        });

        ringCleanupHandlers.set(ring, () => {
          abortController.abort();
          pauseAutoPlay();
          clearTimeout(resizeTimeout);

          // The two debounced callbacks keep their timers in their own
          // closures, so aborting the signal does not reach them. Cancelling
          // them here is what stops a scheduled resize from waking the
          // carousel up after teardown.
          debouncedResize.cancel();
          debouncedHandleVisibility.cancel();
          ring.style.transform = '';
          ring.style.transition = '';
          cells.forEach((cell) => {
            cell.style.opacity = '';
            cell.style.transform = '';
            cell.style.transition = '';
            cell.classList.remove(PB_IS_ACTIVE);
            cell.setAttribute('aria-hidden', 'true');
            cell.removeAttribute('inert');
          });
          // A block restoring `#prev-…`, `#next-…` and `#btn-…` used to sit
          // here. Those are v1 ids: the template emits
          // `pb-3d-carousel__prev-…`, `…__next-…` and `…__play-pause-…`, so
          // the selector matched nothing and the restore was a no-op.
          //
          // ⚠ AND THE SECOND HALF OF THIS NOTE USED TO BE FALSE. It claimed
          // "nothing adds `pb-is-hidden` any more, since a looping carousel
          // has no ends" — written when both geometries wrapped. The ring was
          // then returned to ping-pong, so it does have ends, and this file
          // still toggles that class in `updateNavVisibility()` with `…-ring.css`
          // styling it. Corrected 2026-09-25 after an independent review.
          //
          // The removal above is still right, for the v1-id reason only: the
          // selectors matched nothing. What remains unfixed is that a ring's
          // hidden arrow keeps `pb-is-hidden` and `tabindex="-1"` after
          // detach — pre-existing in v1, not a regression from this work.
        });
      });
    },

    detach(context, settings, trigger) {
      if (trigger !== 'unload') {
        return;
      }

      once
        .remove(
          ONCE_PB_PARAGRAPH_BUNDLE_3D_CAROUSEL_RING,
          PARAGRAPHS_BUNDLES_PB_3D_CAROUSEL_PB_3D_CAROUSEL__RING,
          context,
        )
        .forEach((ring) => {
          ringCleanupHandlers.get(ring)?.();
          ringCleanupHandlers.delete(ring);
        });
    },
  };
})(Drupal, once);
