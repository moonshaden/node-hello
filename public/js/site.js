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
