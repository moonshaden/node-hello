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
