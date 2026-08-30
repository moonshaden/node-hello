/* Homepage slider.
 *
 * No library: one component, a few hundred bytes, and nothing to keep updated.
 *
 * The rules that matter more than the animation:
 *  - Auto-advance stops on hover, on focus inside the slider, and whenever the
 *    tab is hidden. A slide changing while someone reads it is the single
 *    worst thing a carousel does.
 *  - prefers-reduced-motion turns auto-advance off entirely. The slider still
 *    works, it just waits to be driven.
 *  - Arrow keys move between slides when focus is inside it.
 *  - Slides that are not current are hidden from assistive tech and taken out
 *    of the tab order, so a keyboard user cannot tab into an invisible button.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-slider]');
  if (!root) return;

  var slides = Array.prototype.slice.call(root.querySelectorAll('[data-slide]'));
  if (slides.length < 2) return;          // one slide needs no controls

  var dots = Array.prototype.slice.call(root.querySelectorAll('[data-goto]'));
  var live = root.querySelector('[data-slider-status]');
  var index = 0;
  var timer = null;
  var INTERVAL = 7000;

  var still = window.matchMedia('(prefers-reduced-motion: reduce)');

  function show(next) {
    index = (next + slides.length) % slides.length;
    slides.forEach(function (slide, i) {
      var current = i === index;
      slide.classList.toggle('is-current', current);
      slide.setAttribute('aria-hidden', current ? 'false' : 'true');
      // keep hidden slides out of the tab order
      Array.prototype.forEach.call(slide.querySelectorAll('a, button'), function (el) {
        if (current) el.removeAttribute('tabindex');
        else el.setAttribute('tabindex', '-1');
      });
    });
    dots.forEach(function (dot, i) {
      dot.setAttribute('aria-current', i === index ? 'true' : 'false');
    });
    if (live) live.textContent = 'Slide ' + (index + 1) + ' of ' + slides.length;
  }

  function start() {
    if (still.matches || timer) return;
    timer = window.setInterval(function () { show(index + 1); }, INTERVAL);
  }

  function stop() {
    if (timer) { window.clearInterval(timer); timer = null; }
  }

  root.addEventListener('mouseenter', stop);
  root.addEventListener('mouseleave', start);
  root.addEventListener('focusin', stop);
  root.addEventListener('focusout', function (e) {
    if (!root.contains(e.relatedTarget)) start();
  });

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) stop(); else start();
  });

  root.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowRight') { stop(); show(index + 1); }
    else if (e.key === 'ArrowLeft') { stop(); show(index - 1); }
    else return;
    e.preventDefault();
  });

  root.querySelectorAll('[data-step]').forEach(function (button) {
    button.addEventListener('click', function () {
      stop();
      show(index + Number(button.dataset.step));
    });
  });

  dots.forEach(function (dot, i) {
    dot.addEventListener('click', function () { stop(); show(i); });
  });

  // If the visitor asks for stillness mid-session, honour it immediately.
  if (still.addEventListener) still.addEventListener('change', function () {
    if (still.matches) stop(); else start();
  });

  show(0);
  start();
})();

/* Depth.
 *
 * Three behaviours, one file, no library: sections rise into place as you reach
 * them, cards turn toward the pointer, and a couple of layers drift against the
 * scroll.
 *
 * The rules that matter more than the effect:
 *  - Nothing is ever hidden by the stylesheet. This script *adds* the hidden
 *    state and then removes it, so a page whose JS never runs -- or /admin,
 *    which loads the stylesheet and no script -- shows the finished page.
 *  - If IntersectionObserver is missing, no element is ever opted in, so the
 *    page stays exactly as it renders without this file.
 *  - prefers-reduced-motion turns all three off. Not softened: off.
 *  - Tilt is for fine pointers with hover. A finger has no hover state.
 *  - Only transform and opacity are written, and scroll work is batched into
 *    one rAF, so none of this forces layout while scrolling.
 */
(function () {
  'use strict';

  var still = window.matchMedia('(prefers-reduced-motion: reduce)');
  if (still.matches) return;

  /* ---- sections rise into place ---- */
  if ('IntersectionObserver' in window) {
    var risers = document.querySelectorAll(
      '.band > .wrap > *, .impact-grid > *, .pillars > *, .hero-grid > *, ' +
      '.program-list > *, .gallery > img, .grid > *'
    );

    var seen = new WeakSet();

    // Reveal is a one-shot. Once an element has arrived, both classes come off
    // so it stops participating in the cascade at all -- otherwise the finished
    // state (`transform: none`, specificity 2) outranks the pointer tilt
    // (specificity 1) and cards silently stop tilting once they have risen.
    function settleRisen(el) {
      el.classList.add('is-risen');
      setTimeout(function () {
        el.classList.remove('is-deep', 'is-risen');
        el.style.removeProperty('--deep-delay');
      }, 1200);
    }

    var watcher = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        settleRisen(entry.target);
        watcher.unobserve(entry.target);
      });
    // The huge top margin is load-bearing. Without it, a jump to the foot of a
    // long page moves elements from below the viewport to above it without the
    // ratio ever crossing the threshold, no callback fires, and that content
    // stays invisible for good. Counting everything at or above the viewport as
    // intersecting means a scrolled-past element always resolves.
    }, { rootMargin: '9999px 0px -8% 0px', threshold: 0.05 });

    Array.prototype.forEach.call(risers, function (el) {
      if (seen.has(el)) return;
      seen.add(el);

      // Anything already on screen at load is left alone -- animating what the
      // visitor is already looking at reads as a glitch, not an entrance.
      var box = el.getBoundingClientRect();
      if (box.top < window.innerHeight * 0.92) return;

      // Stagger within a row so a grid deals itself out rather than snapping.
      var siblings = el.parentNode.children;
      var index = Array.prototype.indexOf.call(siblings, el);
      el.style.setProperty('--deep-delay', Math.min(index, 5) * 70 + 'ms');

      el.classList.add('is-deep');
      watcher.observe(el);
    });

    // Last resort. If the observer somehow never resolves an element -- a
    // browser quirk, a detached subtree -- the page must not be left with
    // invisible content. After a few seconds everything still waiting is shown.
    setTimeout(function () {
      Array.prototype.forEach.call(
        document.querySelectorAll('.is-deep:not(.is-risen)'),
        function (el) { watcher.unobserve(el); settleRisen(el); }
      );
    }, 4000);
  }

  /* ---- cards turn toward the pointer ---- */
  var fine = window.matchMedia('(hover: hover) and (pointer: fine)');
  if (fine.matches) {
    var MAX = 6;   // degrees; past about 7 a card starts to look like it is falling over
    var tiltable = document.querySelectorAll('.card, .pillar, .sidebar-card');

    Array.prototype.forEach.call(tiltable, function (card) {
      var frame = null;

      function track(e) {
        if (frame) return;
        frame = requestAnimationFrame(function () {
          frame = null;
          var box = card.getBoundingClientRect();
          if (!box.width || !box.height) return;
          var px = (e.clientX - box.left) / box.width;
          var py = (e.clientY - box.top) / box.height;
          // Pointer right tips the right edge away, pointer high tips the top
          // away -- the card leans toward the cursor, not with it.
          card.style.setProperty('--tilt-y', ((px - 0.5) * 2 * MAX).toFixed(2) + 'deg');
          card.style.setProperty('--tilt-x', ((0.5 - py) * 2 * MAX).toFixed(2) + 'deg');
          card.style.setProperty('--glare-x', (px * 100).toFixed(1) + '%');
          card.style.setProperty('--glare-y', (py * 100).toFixed(1) + '%');
        });
      }

      function settle() {
        if (frame) { cancelAnimationFrame(frame); frame = null; }
        card.classList.remove('is-tilting');
        card.style.removeProperty('--tilt-x');
        card.style.removeProperty('--tilt-y');
      }

      card.addEventListener('pointerenter', function (e) {
        if (e.pointerType !== 'mouse') return;   // pen and touch both fake enter
        card.classList.add('is-tilting');
        track(e);
      });
      card.addEventListener('pointermove', function (e) {
        if (card.classList.contains('is-tilting')) track(e);
      });
      card.addEventListener('pointerleave', settle);
      // A card holding a <details> changes height under the pointer; the stale
      // tilt would then be measured against the old box.
      card.addEventListener('toggle', settle, true);
    });
  }

  /* ---- a couple of layers drift against the scroll ---- */
  var drifters = document.querySelectorAll('.page-head, .slide-img');
  if (drifters.length) {
    var queued = false;

    function drift() {
      queued = false;
      var viewport = window.innerHeight;
      Array.prototype.forEach.call(drifters, function (el) {
        var box = el.getBoundingClientRect();
        if (box.bottom < 0 || box.top > viewport) return;   // off screen, skip
        // -1 above the viewport, +1 below it; the layer travels a fraction of
        // that, which is what separates it from the text riding on top.
        var progress = (box.top + box.height / 2 - viewport / 2) / viewport;
        el.style.setProperty('--drift', (progress * 26).toFixed(1) + 'px');
      });
    }

    function queue() {
      if (queued) return;
      queued = true;
      requestAnimationFrame(drift);
    }

    addEventListener('scroll', queue, { passive: true });
    addEventListener('resize', queue, { passive: true });
    drift();
  }
})();

/* Cinematic staging.
 *
 * Turns the page into a corridor of panels and moves a camera through it. One
 * rAF drives every section, the travelling backdrop and the impact counters.
 *
 * The safety model is the class on <body>: every rule in the stylesheet that
 * stages anything is scoped under `.is-staged`, and only this file adds it. No
 * JS, a reader mode, a crawler, or prefers-reduced-motion and the page is the
 * plain document it has always been. The dramatic version is the enhancement,
 * never the baseline.
 */
(function () {
  'use strict';

  var still = window.matchMedia('(prefers-reduced-motion: reduce)');
  if (still.matches) return;

  var scenes = document.querySelectorAll('main > section');
  if (!scenes.length) return;

  document.body.classList.add('is-staged');

  /* Give the impact figures their own planes, alternating so the row reads as
     staggered depth rather than a slope. */
  var figures = document.querySelectorAll('.impact-grid > *');
  Array.prototype.forEach.call(figures, function (fig, i) {
    fig.style.setProperty('--tier', (i % 2 ? -1 : 1) * (1 - Math.abs(i - (figures.length - 1) / 2) / figures.length));
  });

  var queued = false;

  function frame() {
    queued = false;
    var viewport = window.innerHeight;
    var mid = viewport / 2;

    Array.prototype.forEach.call(scenes, function (scene) {
      var box = scene.getBoundingClientRect();
      // How far this panel's centre is from the centre of the screen, as a
      // fraction of a screen. Clamped, because a very tall panel would
      // otherwise fade itself out while you are still reading it.
      var offset = (box.top + box.height / 2 - mid) / viewport;
      var p = Math.max(-1, Math.min(1, offset));

      // A tall panel is a page of content, not a slide: hold it near the
      // screen plane while it is the thing being read.
      if (box.height > viewport * 1.1) {
        var covered = box.top < mid && box.bottom > mid;
        if (covered) p *= 0.18;
      }

      scene.style.setProperty('--p', p.toFixed(4));
      scene.style.setProperty('--pa', Math.abs(p).toFixed(4));
    });

    document.body.style.setProperty('--travel', (window.scrollY * 0.06).toFixed(1) + 'px');
  }

  function queue() {
    if (queued) return;
    queued = true;
    requestAnimationFrame(frame);
  }

  addEventListener('scroll', queue, { passive: true });
  addEventListener('resize', queue, { passive: true });
  frame();

  /* ---- impact figures count up as their scene arrives ----
   *
   * The published figures are transcribed from the live site, so the number on
   * screen when this finishes must be the seeded string character for
   * character. The original text is captured first and written back at the end
   * rather than reconstructed from the parsed parts -- a formatting guess here
   * would quietly misreport what the foundation has raised.
   */
  if ('IntersectionObserver' in window) {
    var values = document.querySelectorAll('.impact-grid .value');
    var counter = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        counter.unobserve(entry.target);
        countUp(entry.target);
      });
    }, { threshold: 0.4 });
    Array.prototype.forEach.call(values, function (el) { counter.observe(el); });
  }

  function countUp(el) {
    var exact = el.textContent;
    var match = exact.match(/^(\D*?)([\d,.]+)(\D*)$/);
    if (!match) return;                       // not a number; leave it alone

    var target = parseFloat(match[2].replace(/,/g, ''));
    if (!isFinite(target) || target <= 0) return;

    var decimals = (match[2].split('.')[1] || '').length;
    var grouped = match[2].indexOf(',') !== -1;
    var start = performance.now();
    var RUN = 1100;

    el.style.setProperty('min-width', el.getBoundingClientRect().width + 'px');

    function tick(now) {
      var t = Math.min(1, (now - start) / RUN);
      // ease-out: fast off the mark, settling into the real figure
      var eased = 1 - Math.pow(1 - t, 3);
      if (t < 1) {
        var n = target * eased;
        var shown = decimals ? n.toFixed(decimals) : String(Math.round(n));
        if (grouped) shown = Number(shown).toLocaleString('en-US');
        el.textContent = match[1] + shown + match[3];
        requestAnimationFrame(tick);
      } else {
        // the published string, restored verbatim
        el.textContent = exact;
        el.style.removeProperty('min-width');
      }
    }
    requestAnimationFrame(tick);
  }
})();

/* The awarded students.
 *
 * Turns the flat list of every awarded student into a stage that steps through
 * them one at a time. Arrows, arrow keys, and swipe.
 *
 * The list is readable before this runs and would stay readable if it never
 * did: the script *adds* `.is-live`, and only then does the stylesheet stack
 * them. Nothing is hidden by CSS alone, because a visitor without JavaScript
 * has to be able to read all fifteen.
 */
(function () {
  'use strict';

  var deck = document.querySelector('[data-awardees]');
  if (!deck) return;

  var people = Array.prototype.slice.call(deck.querySelectorAll('[data-awardee]'));
  if (people.length < 2) return;              // one student needs no controls

  var counter = document.querySelector('[data-awardee-count]');
  var steps = document.querySelectorAll('[data-awardee-step]');
  var index = 0;

  // Stacking them absolutely collapses the deck's height, so measure the
  // tallest while they are still in flow and hold the stage to it. Without this
  // the page jumps every time someone steps to a longer story.
  function measure() {
    var was = deck.classList.contains('is-live');
    if (was) deck.classList.remove('is-live');
    var tallest = 0;
    people.forEach(function (person) {
      tallest = Math.max(tallest, person.getBoundingClientRect().height);
    });
    if (was) deck.classList.add('is-live');
    if (tallest) deck.style.setProperty('--stage-height', Math.ceil(tallest) + 'px');
  }

  function show(next) {
    index = (next + people.length) % people.length;
    people.forEach(function (person, i) {
      if (i === index) person.removeAttribute('aria-hidden');
      else person.setAttribute('aria-hidden', 'true');
    });
    if (counter) counter.textContent = (index + 1) + ' of ' + people.length;
  }

  measure();
  deck.classList.add('is-live');
  show(0);

  Array.prototype.forEach.call(steps, function (button) {
    button.addEventListener('click', function () {
      show(index + Number(button.getAttribute('data-awardee-step')));
    });
  });

  // Arrow keys, but only while the stage has focus -- stealing them from the
  // whole page would break ordinary scrolling.
  deck.parentNode.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowLeft') { show(index - 1); e.preventDefault(); }
    if (e.key === 'ArrowRight') { show(index + 1); e.preventDefault(); }
  });

  // Swipe. Horizontal intent only, so a vertical scroll that starts on a
  // portrait still scrolls the page.
  var startX = null, startY = null;
  deck.addEventListener('pointerdown', function (e) {
    if (e.pointerType === 'mouse') return;
    startX = e.clientX; startY = e.clientY;
  });
  deck.addEventListener('pointerup', function (e) {
    if (startX === null) return;
    var dx = e.clientX - startX, dy = e.clientY - startY;
    startX = startY = null;
    if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy)) show(index + (dx < 0 ? 1 : -1));
  });

  var settle;
  addEventListener('resize', function () {
    clearTimeout(settle);
    settle = setTimeout(measure, 180);
  }, { passive: true });

  // Portraits load lazily, and a story's height changes once its image has a
  // box. Re-measure when they land or the stage can end up too short.
  Array.prototype.forEach.call(deck.querySelectorAll('img'), function (img) {
    if (!img.complete) img.addEventListener('load', measure, { once: true });
  });
})();
