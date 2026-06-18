/* Bosket's Alimentos — visitor light/dark toggle (paired with cms-theme.css).
   The initial theme is already set by an inline <head> script (anti-flash);
   this only handles the toggle button + live OS changes when default=system. */
(function () {
  'use strict';
  var doc = document.documentElement;

  function current() {
    return doc.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  }

  function apply(theme, remember) {
    doc.setAttribute('data-theme', theme);
    if (remember) {
      try { localStorage.setItem('boskets-theme', theme); } catch (e) {}
    }
  }

  function wire() {
    var btn = document.getElementById('cms-theme-toggle');
    if (btn) {
      btn.addEventListener('click', function () {
        apply(current() === 'dark' ? 'light' : 'dark', true);
      });
    }
  }

  // If the visitor hasn't chosen, follow OS changes live.
  try {
    if (!localStorage.getItem('boskets-theme') && window.matchMedia) {
      var mq = window.matchMedia('(prefers-color-scheme: dark)');
      var onChange = function (e) {
        if (!localStorage.getItem('boskets-theme')) apply(e.matches ? 'dark' : 'light', false);
      };
      if (mq.addEventListener) mq.addEventListener('change', onChange);
      else if (mq.addListener) mq.addListener(onChange);
    }
  } catch (e) {}

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', wire);
  } else {
    wire();
  }
})();
