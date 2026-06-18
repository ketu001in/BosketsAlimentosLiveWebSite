/* Bosket's Alimentos CMS — portal interactions (vanilla JS) */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  /* -------- confirm prompts -------- */
  $$('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!window.confirm(el.getAttribute('data-confirm'))) e.preventDefault();
    });
  });
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  /* -------- slug auto-fill from title (until the slug is hand-edited) -------- */
  var title = $('#cms-title'), slug = $('#cms-slug');
  if (title && slug) {
    var slugTouched = slug.value.trim() !== '';
    slug.addEventListener('input', function () { slugTouched = true; });
    title.addEventListener('input', function () {
      if (slugTouched) return;
      slug.value = title.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    });
  }

  /* -------- appearance: highlight chosen theme card -------- */
  $$('.theme-choice input').forEach(function (r) {
    r.addEventListener('change', function () {
      $$('.theme-choice').forEach(function (c) { c.classList.remove('sel'); });
      if (r.checked) r.closest('.theme-choice').classList.add('sel');
    });
  });

  /* -------- WYSIWYG editor -------- */
  var editor = $('.cms-editor');
  if (editor) {
    var area = $('.cms-area', editor);
    var hidden = $('#cms-body');
    var csrf = editor.getAttribute('data-csrf') || '';
    var uploadUrl = editor.getAttribute('data-upload') || '';

    function sync() { if (hidden) hidden.value = area.innerHTML; }
    area.addEventListener('input', sync);
    area.addEventListener('blur', sync);

    $$('.cms-toolbar [data-cmd]', editor).forEach(function (btn) {
      btn.addEventListener('mousedown', function (e) { e.preventDefault(); });
      btn.addEventListener('click', function () {
        var cmd = btn.getAttribute('data-cmd');
        var val = btn.getAttribute('data-val') || null;
        area.focus();
        if (cmd === 'createLink') {
          var url = window.prompt('Link URL (https://… or /page.php?slug=…):', 'https://');
          if (url) document.execCommand('createLink', false, url);
        } else if (cmd === 'formatBlock') {
          document.execCommand('formatBlock', false, '<' + val + '>');
        } else {
          document.execCommand(cmd, false, val);
        }
        sync();
      });
    });

    var imgBtn = $('[data-img]', editor), imgInput = $('.cms-img-input', editor);
    if (imgBtn && imgInput) {
      imgBtn.addEventListener('click', function () { imgInput.click(); });
      imgInput.addEventListener('change', function () {
        var f = imgInput.files[0];
        if (!f) return;
        if (f.size > 5 * 1024 * 1024) { alert('Image must be 5 MB or smaller.'); imgInput.value = ''; return; }
        var fd = new FormData();
        fd.append('file', f);
        fetch(uploadUrl, { method: 'POST', headers: { 'X-CSRF': csrf }, body: fd })
          .then(function (r) { return r.json(); })
          .then(function (res) {
            if (res.ok && res.url) {
              area.focus();
              document.execCommand('insertImage', false, res.url);
              sync();
            } else {
              alert(res.error || 'Upload failed.');
            }
          })
          .catch(function () { alert('Upload failed.'); })
          .finally(function () { imgInput.value = ''; });
      });
    }

    // make sure the body is synced before the form submits
    var form = editor.closest('form');
    if (form) form.addEventListener('submit', sync);
  }
})();
