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
  // How many show at once is the template's decision -- it is what decides
  // which cards ship with the `hidden` attribute -- so read it rather than
  // keeping a second copy of the number here that can fall out of step.
  var PER_VIEW = parseInt(rail.getAttribute('data-hero-visible'), 10) || 3;
  if (cards.length <= PER_VIEW) return;              // nothing to rotate through

  var still = window.matchMedia('(prefers-reduced-motion: reduce)');
  if (still.matches) return;                         // the server's trio stands

  // Tell the stylesheet the rotation is live, so a card that is visible but not
  // yet faded in starts from transparent rather than snapping into place.
  rail.setAttribute('data-hero-live', '');

  var interval = parseInt(rail.getAttribute('data-hero-interval'), 10) || 5000;
  var FADE = 600;                                    // must match the CSS transition
  var timer = null;
  var tick = 0;
  var busy = false;

  // One card changes at a time, each in its own slot, so the rail never swaps
  // as a block. `order` is what pins a card to its slot: grid otherwise lays the
  // visible cards out in DOM order, so replacing the first one would slide the
  // other two up a place -- a jump, not a fade.
  var slots = cards.slice(0, PER_VIEW);
  slots.forEach(function (card, i) { card.style.order = i; });

  // A cursor that walks the deck in order and wraps, rather than searching
  // outward from whatever is on screen. That is what guarantees every student
  // gets their turn: the cursor only ever moves forward, so nobody is stepped
  // over, whether the rail shows two at a time or three.
  var cursor = PER_VIEW % cards.length;

  function nextCard() {
    for (var tries = 0; tries < cards.length; tries++) {
      var candidate = cards[cursor];
      cursor = (cursor + 1) % cards.length;
      if (slots.indexOf(candidate) === -1) return candidate;   // skip anyone on screen
    }
    return null;
  }

  function advance() {
    if (busy) return;
    var slot = tick % PER_VIEW;
    var outgoing = slots[slot];
    var incoming = nextCard();
    if (!incoming || !outgoing) return;

    busy = true;
    tick++;

    outgoing.classList.remove('is-shown');
    window.setTimeout(function () {
      outgoing.hidden = true;
      outgoing.style.removeProperty('order');
      incoming.style.order = slot;
      incoming.hidden = false;
      slots[slot] = incoming;

      // A frame between un-hiding and the class, or there is no state to
      // transition from and the fade does not happen at all.
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          incoming.classList.add('is-shown');
          busy = false;
        });
      });
    }, FADE);
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
  // the first change has something to fade out of.
  slots.forEach(function (card) { card.classList.add('is-shown'); });
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


/* Back to top.
 *
 * The control ships with the `hidden` attribute. This is the only thing that
 * ever takes it off, and only on a page whose document runs past one full
 * screen -- so a page short enough to read without scrolling never carries one,
 * and a page that grows or shrinks (the copy is editable in /admin, and a
 * narrow window makes every page taller) is re-judged on resize.
 *
 * Two thresholds rather than one, because a single one is wrong at both ends:
 *
 *  - Half a screen down is the usual trigger, but on a page only a little
 *    taller than the window you can never scroll that far -- the shortest page
 *    on this site scrolls 427px in a 1080px-tall window -- so the control would
 *    exist and never appear.
 *  - Halfway down the page covers that case, and on a long page it is far too
 *    late: the homepage would not offer one until 1,797px.
 *
 * Whichever comes FIRST, then. It is the smaller number in both directions.
 *
 * prefers-reduced-motion drops the smooth scroll, not the control: a visitor
 * asking for stillness still gets taken to the top, it just happens at once.
 */
(function () {
  'use strict';

  var button = document.querySelector('.to-top');
  if (!button) return;

  var target = document.getElementById('top');
  var still = window.matchMedia('(prefers-reduced-motion: reduce)');
  var shown = false;
  var eligible = false;

  function maxScroll() {
    return document.documentElement.scrollHeight - window.innerHeight;
  }

  function judge() {
    // "Past a full screen" is the document being taller than the window. A
    // page that cannot scroll at all can never reveal the control anyway, but
    // the attribute is what keeps it out of the accessibility tree and the tab
    // order on such a page rather than merely invisible.
    eligible = maxScroll() > 0;
    button.hidden = !eligible;
    if (!eligible) {
      shown = false;
      button.classList.remove('is-shown');
    }
  }

  function update() {
    if (!eligible) return;
    var trigger = Math.min(window.innerHeight * 0.5, maxScroll() * 0.5);
    var want = window.scrollY > trigger;
    if (want === shown) return;                 // don't touch the class every frame
    shown = want;
    button.classList.toggle('is-shown', want);
  }

  button.addEventListener('click', function (e) {
    // The href is a real fragment and works without this handler; the handler
    // only adds the smooth scroll and, more importantly, moves focus. Without
    // that a keyboard user lands at the top of the page with their focus still
    // in the footer, so the next Tab takes them straight back down.
    e.preventDefault();
    window.scrollTo({ top: 0, behavior: still.matches ? 'auto' : 'smooth' });
    if (target) target.focus({ preventScroll: true });
  });

  var queued = false;
  addEventListener('scroll', function () {
    if (queued) return;
    queued = true;
    requestAnimationFrame(function () { queued = false; update(); });
  }, { passive: true });

  var settle;
  addEventListener('resize', function () {
    clearTimeout(settle);
    settle = setTimeout(function () { judge(); update(); }, 180);
  }, { passive: true });

  // Images load lazily and the document gets taller as they land, so a page
  // that was one screen at parse time may not be one screen a moment later.
  addEventListener('load', function () { judge(); update(); });

  judge();
  update();
})();
