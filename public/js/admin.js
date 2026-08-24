// Show only the date fields that apply to the chosen window type.
(function () {
  var select = document.querySelector('[data-window-type]');
  if (!select) return;

  function sync() {
    document.querySelectorAll('[data-window-block]').forEach(function (block) {
      block.style.display = block.dataset.windowBlock === select.value ? '' : 'none';
    });
  }

  select.addEventListener('change', sync);
  sync();
})();
