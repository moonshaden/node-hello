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


/* The hero rail.
 *
 * Three awarded students beside the one in the hero, rotating through every
 * published recipient with a cross-fade.
 *
 * This is the only thing on the site that moves by itself, so it carries the
 * rules that stop an auto-advancing element being hostile -- the same ones the
 * old carousel had:
 *
 *  - It stops on hover, and while anything inside it holds keyboard focus. A
 *    card changing under a pointer or mid-read is the worst thing a rotator
 *    does.
 *  - It stops while the tab is in the background. Nobody needs fourteen
 *    portraits fetched behind their back.
 *  - prefers-reduced-motion turns it off entirely. Not a slower fade: off. The
 *    visitor keeps the three the server rendered.
 *
 * The markup is already correct without this file: the first three cards are
 * shown and the rest carry the `hidden` attribute, so no JS means a static trio
 * rather than an empty box, and the other portraits are never fetched.
 */
(function () {
  'use strict';

  var rail = document.querySelector('[data-hero-rail]');
  if (!rail) return;

  var cards = Array.prototype.slice.call(rail.querySelectorAll('.hero-rail-card'));
  var PER_VIEW = 3;
  if (cards.length <= PER_VIEW) return;              // nothing to rotate through

  var still = window.matchMedia('(prefers-reduced-motion: reduce)');
  if (still.matches) return;                         // the server's trio stands

  // Tell the stylesheet the rotation is live, so a card that is visible but not
  // yet faded in starts from transparent rather than snapping into place.
  rail.setAttribute('data-hero-live', '');

  var interval = parseInt(rail.getAttribute('data-hero-interval'), 10) || 5000;
  var FADE = 600;                                    // must match the CSS transition
  var start = 0;
  var timer = null;
  var fading = false;

  function windowAt(offset) {
    var out = [];
    for (var i = 0; i < PER_VIEW; i++) out.push(cards[(offset + i) % cards.length]);
    return out;
  }

  function show(offset) {
    var next = windowAt(offset);

    // Fade the current three out, then swap. Reading `hidden` rather than a
    // list we maintain keeps this honest if anything else ever touches them.
    cards.forEach(function (card) { card.classList.remove('is-shown'); });

    window.setTimeout(function () {
      cards.forEach(function (card) {
        if (next.indexOf(card) === -1) card.hidden = true;
      });
      next.forEach(function (card) { card.hidden = false; });

      // A frame between un-hiding and the class, or there is no state to
      // transition from and the fade does not happen at all.
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          next.forEach(function (card) { card.classList.add('is-shown'); });
          fading = false;
        });
      });
    }, FADE);
  }

  function advance() {
    if (fading) return;
    fading = true;
    start = (start + PER_VIEW) % cards.length;
    show(start);
  }

  function play() {
    if (timer || still.matches) return;
    timer = window.setInterval(advance, interval);
  }

  function pause() {
    if (timer) { window.clearInterval(timer); timer = null; }
  }

  rail.addEventListener('mouseenter', pause);
  rail.addEventListener('mouseleave', play);
  rail.addEventListener('focusin', pause);
  rail.addEventListener('focusout', play);
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) pause(); else play();
  });
  still.addEventListener('change', function () { if (still.matches) pause(); });

  // The three the server rendered are already correct; just mark them shown so
  // the first rotation has something to fade out of.
  windowAt(0).forEach(function (card) { card.classList.add('is-shown'); });
  play();
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
