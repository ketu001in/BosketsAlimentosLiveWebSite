/* Bosket's Alimentos — front-end behaviour
   (vanilla JS, no dependencies) */
(function () {
  'use strict';
  const B = window.BOSKETS || { base: '', csrf: '', loggedIn: false };

  const $ = (sel, ctx) => (ctx || document).querySelector(sel);
  const $$ = (sel, ctx) => Array.from((ctx || document).querySelectorAll(sel));

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
      ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function api(path, data) {
    return fetch(B.base + '/api/' + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF': B.csrf },
      body: JSON.stringify(data || {})
    }).then(r => r.json());
  }

  function toast(msg, ok) {
    const t = document.createElement('div');
    t.className = 'flash ' + (ok === false ? 'flash-error' : 'flash-success');
    t.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);z-index:300;box-shadow:0 8px 30px rgba(0,0,0,.18);max-width:90vw';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3200);
  }

  function requireLogin() {
    if (!B.loggedIn) {
      window.location.href = B.base + '/login.php';
      return false;
    }
    return true;
  }

  /* ============================== Reactions ============================== */
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.react-btn');
    if (!btn) return;
    if (!requireLogin()) return;
    const bar = btn.closest('.react-bar');
    api('react.php', {
      target_type: bar.dataset.type,
      target_id: +bar.dataset.id,
      reaction: btn.dataset.reaction
    }).then(res => {
      if (!res.ok) return toast(res.error || 'Something went wrong', false);
      $$('.react-btn', bar).forEach(b => {
        b.classList.toggle('active', res.mine === b.dataset.reaction);
        $('.react-count', b).textContent = res.counts[b.dataset.reaction] || 0;
      });
    });
  });

  /* ============================== Comments ============================== */
  $$('.comment-form').forEach(form => {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!requireLogin()) return;
      const ta = $('textarea', form);
      const body = ta.value.trim();
      if (!body) return;
      const btn = $('button', form);
      btn.disabled = true;
      api('comment.php', {
        target_type: form.dataset.type,
        target_id: +form.dataset.id,
        body: body
      }).then(res => {
        btn.disabled = false;
        if (!res.ok) return toast(res.error || 'Could not post comment', false);
        const list = $(form.dataset.list);
        if (list) {
          list.insertAdjacentHTML('beforeend', res.html);
          const empty = $('.empty', list.parentElement);
          if (empty) empty.remove();
        }
        ta.value = '';
        toast('Comment posted!');
      });
    });
  });

  /* ============================== Buddy actions ============================== */
  document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-buddy-action]');
    if (!btn) return;
    e.preventDefault();
    if (!requireLogin()) return;
    btn.disabled = true;
    api('buddy.php', { action: btn.dataset.buddyAction, user_id: +btn.dataset.userId })
      .then(res => {
        if (!res.ok) { btn.disabled = false; return toast(res.error || 'Failed', false); }
        toast(res.message || 'Done!');
        setTimeout(() => window.location.reload(), 700);
      });
  });

  /* ============================== Typeahead (master lists) ==============================
     <input class="typeahead" data-master="ingredients" name="..."> wrapped in .ta-wrap.
     Suggestions appear after 3 letters; unknown names are kept as typed and
     created server-side on submit (becoming part of the master list). */
  function attachTypeahead(input) {
    if (!input || input.dataset.taBound) return;
    input.dataset.taBound = '1';
    const wrap = input.closest('.ta-wrap') || input.parentElement;
    let list = null, timer = null;
    function close() { if (list) { list.remove(); list = null; } }
    input.addEventListener('input', function () {
      const q = input.value.trim();
      clearTimeout(timer);
      if (q.length < 3) { close(); return; }
      timer = setTimeout(() => {
        fetch(B.base + '/api/suggest.php?type=' + encodeURIComponent(input.dataset.master) + '&q=' + encodeURIComponent(q))
          .then(r => r.json())
          .then(res => {
            if (!res.ok) return;
            close();
            list = document.createElement('div');
            list.className = 'ta-list';
            res.items.forEach(name => {
              const it = document.createElement('div');
              it.className = 'ta-item';
              it.textContent = name;
              it.addEventListener('mousedown', () => { input.value = name; close(); });
              list.appendChild(it);
            });
            if (!res.items.some(n => n.toLowerCase() === q.toLowerCase())) {
              const add = document.createElement('div');
              add.className = 'ta-item ta-new';
              add.textContent = '➕ Add "' + q + '" as a new entry';
              add.addEventListener('mousedown', () => { input.value = q; close(); });
              list.appendChild(add);
            }
            wrap.appendChild(list);
          });
      }, 220);
    });
    input.addEventListener('blur', () => setTimeout(close, 180));
  }
  $$('.typeahead').forEach(attachTypeahead);

  /* ============================== Recipe form: ingredient rows ============================== */
  const ingList = $('#ingredient-rows');
  if (ingList) {
    $('#add-ingredient').addEventListener('click', function () {
      const first = $('.repeat-row', ingList);
      const row = first.cloneNode(true);
      $$('input', row).forEach(i => { i.value = ''; delete i.dataset.taBound; });
      const old = $('.ta-list', row); if (old) old.remove();
      ingList.appendChild(row);
      attachTypeahead($('.typeahead', row));
      $('.typeahead', row).focus();
    });
    ingList.addEventListener('click', function (e) {
      if (e.target.classList.contains('row-remove')) {
        if ($$('.repeat-row', ingList).length > 1) e.target.closest('.repeat-row').remove();
      }
    });
  }

  /* ============================== Recipe form: step blocks ============================== */
  const stepList = $('#step-blocks');
  if (stepList) {
    function renumber() {
      $$('.step-block', stepList).forEach((b, i) => {
        $('.step-label', b).textContent = 'Step ' + (i + 1);
        const ta = $('textarea', b);
        if (ta) ta.name = 'step_text[' + i + ']';
        const file = $('input[type=file]', b);
        if (file) file.name = 'step_media[' + i + ']';
        const keep = $('input.keep-media', b);
        if (keep) keep.name = 'keep_media[' + i + ']';
      });
    }
    $('#add-step').addEventListener('click', function () {
      const first = $('.step-block', stepList);
      const block = first.cloneNode(true);
      $('textarea', block).value = '';
      const file = $('input[type=file]', block);
      if (file) file.value = '';
      const existing = $('.step-existing', block);
      if (existing) existing.remove();
      const keep = $('input.keep-media', block);
      if (keep) keep.remove();
      stepList.appendChild(block);
      renumber();
    });
    stepList.addEventListener('click', function (e) {
      if (e.target.classList.contains('step-remove')) {
        if ($$('.step-block', stepList).length > 1) {
          e.target.closest('.step-block').remove();
          renumber();
        }
      }
    });
  }

  /* ============================== Client-side upload size check ============================== */
  document.addEventListener('change', function (e) {
    const input = e.target;
    if (input.type !== 'file' || !input.files.length) return;
    const f = input.files[0];
    const isVideo = f.type.startsWith('video/');
    const limit = isVideo ? 25 * 1024 * 1024 : 5 * 1024 * 1024;
    if (f.size > limit) {
      toast((isVideo ? 'Videos' : 'Images') + ' must be ' + (isVideo ? '25' : '5') + ' MB or smaller.', false);
      input.value = '';
    }
  });

  /* ============================== Share modal ============================== */
  document.addEventListener('click', function (e) {
    const open = e.target.closest('[data-share-recipe]');
    if (open) {
      e.preventDefault();
      const m = $('#share-modal');
      if (!m) return;
      m.dataset.recipeId = open.dataset.shareRecipe;
      $('#share-url').value = open.dataset.shareUrl;
      const title = encodeURIComponent(open.dataset.shareTitle + ' — ' + open.dataset.shareUrl);
      $('#share-wa').href = 'https://wa.me/?text=' + title;
      $('#share-fb').href = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(open.dataset.shareUrl);
      $('#share-x').href = 'https://twitter.com/intent/tweet?text=' + title;
      m.classList.add('open');
    }
    if (e.target.classList.contains('modal-overlay') || e.target.closest('[data-close-modal]')) {
      $$('.modal-overlay').forEach(m => m.classList.remove('open'));
    }
  });

  const copyBtn = $('#share-copy');
  if (copyBtn) copyBtn.addEventListener('click', function () {
    navigator.clipboard.writeText($('#share-url').value).then(() => toast('Link copied!'));
  });

  const repostBtn = $('#share-repost');
  if (repostBtn) repostBtn.addEventListener('click', function () {
    if (!requireLogin()) return;
    const m = $('#share-modal');
    repostBtn.disabled = true;
    api('wall.php', {
      action: 'share',
      recipe_id: +m.dataset.recipeId,
      body: $('#share-note').value.trim()
    }).then(res => {
      repostBtn.disabled = false;
      if (!res.ok) return toast(res.error || 'Could not share', false);
      m.classList.remove('open');
      $('#share-note').value = '';
      toast('Shared to your wall! Your buddies will see it in their feed.');
    });
  });

  /* ============================== Notification poll (logged-in) ============================== */
  if (B.loggedIn && $('#bell')) {
    setInterval(function () {
      fetch(B.base + '/api/notify.php?count=1', { headers: { 'X-CSRF': B.csrf } })
        .then(r => r.json())
        .then(res => {
          if (!res.ok) return;
          let badge = $('#bell-count');
          if (res.unread > 0) {
            if (!badge) {
              badge = document.createElement('span');
              badge.className = 'bell-count';
              badge.id = 'bell-count';
              $('#bell').appendChild(badge);
            }
            badge.textContent = res.unread;
          } else if (badge) {
            badge.remove();
          }
        }).catch(() => {});
    }, 45000);
  }

  /* ============================== Messages: nav unread badge poll ============================== */
  if (B.loggedIn && $('#msgicon')) {
    const icon = $('#msgicon');
    setInterval(function () {
      fetch(B.base + '/api/chat.php?unread=1', { headers: { 'X-CSRF': B.csrf } })
        .then(r => r.json())
        .then(res => {
          if (!res.ok) return;
          let badge = $('#msg-count');
          if (res.unread > 0) {
            if (!badge) {
              badge = document.createElement('span');
              badge.className = 'bell-count';
              badge.id = 'msg-count';
              icon.appendChild(badge);
            }
            badge.textContent = res.unread;
          } else if (badge) {
            badge.remove();
          }
        }).catch(() => {});
    }, 20000);
  }

  /* ============================== Chat conversation (messages page) ============================== */
  const chatLog = $('#chat-log');
  if (chatLog) {
    const withId = +chatLog.dataset.with;
    let lastId = +chatLog.dataset.last || 0;
    const composer = $('#chat-composer');
    const input = $('#chat-input');

    function bubble(m) {
      const wrap = document.createElement('div');
      wrap.className = 'chat-msg' + (m.mine ? ' mine' : '');
      if (m.id) wrap.dataset.id = m.id;
      wrap.innerHTML = '<div class="chat-bubble">' + escapeHtml(m.body).replace(/\n/g, '<br>') + '</div>' +
                       '<div class="chat-time muted">' + escapeHtml(m.time) + '</div>';
      return wrap;
    }
    function appendMessages(list) {
      const empty = $('#chat-empty');
      if (empty && list.length) empty.remove();
      const nearBottom = chatLog.scrollHeight - chatLog.scrollTop - chatLog.clientHeight < 90;
      list.forEach(m => {
        if (m.id && chatLog.querySelector('.chat-msg[data-id="' + m.id + '"]')) return;
        chatLog.appendChild(bubble(m));
        if (m.id > lastId) lastId = m.id;
      });
      if (nearBottom) chatLog.scrollTop = chatLog.scrollHeight;
    }
    chatLog.scrollTop = chatLog.scrollHeight;

    setInterval(function () {
      fetch(B.base + '/api/chat.php?with=' + withId + '&after=' + lastId, { headers: { 'X-CSRF': B.csrf } })
        .then(r => r.json())
        .then(res => { if (res.ok && res.messages.length) appendMessages(res.messages); })
        .catch(() => {});
    }, 4000);

    if (composer && input) {
      const autoGrow = function () { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 140) + 'px'; };
      input.addEventListener('input', autoGrow);
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); composer.requestSubmit(); }
      });
      composer.addEventListener('submit', function (e) {
        e.preventDefault();
        const body = input.value.trim();
        if (!body) return;
        const btn = $('button', composer);
        btn.disabled = true;
        api('chat.php', { to: +composer.dataset.to, body: body }).then(res => {
          btn.disabled = false;
          if (!res.ok) return toast(res.error || 'Could not send message', false);
          appendMessages([res.message]);
          input.value = ''; autoGrow(); input.focus();
        });
      });
    }
  }

  /* ============================== Avatar preview ============================== */
  const avatarInput = $('#avatar-input');
  if (avatarInput) avatarInput.addEventListener('change', function () {
    const f = avatarInput.files[0];
    if (!f || !f.type.startsWith('image/')) return;
    const prev = $('#avatar-preview');
    if (prev) prev.src = URL.createObjectURL(f);
  });

  /* ============================== Header recipe search ============================== */
  const searchInput = $('#recipe-search');
  const searchResults = $('#recipe-search-results');
  if (searchInput && searchResults) {
    let stimer = null, scontroller = null;
    function closeSearch() {
      searchResults.classList.remove('open');
      searchInput.setAttribute('aria-expanded', 'false');
    }
    function renderSearch(items) {
      if (!items.length) {
        searchResults.innerHTML = '<div class="nav-search-empty">No recipes match that — try another word.</div>';
      } else {
        searchResults.innerHTML = items.map(it =>
          '<a href="' + it.url + '" role="option">' + escapeHtml(it.title) + '</a>'
        ).join('');
      }
      searchResults.classList.add('open');
      searchInput.setAttribute('aria-expanded', 'true');
    }
    searchInput.addEventListener('input', function () {
      const q = searchInput.value.trim();
      clearTimeout(stimer);
      if (q.length < 3) { closeSearch(); return; }
      stimer = setTimeout(function () {
        if (scontroller) scontroller.abort();
        scontroller = new AbortController();
        fetch(B.base + '/api/recipe-search.php?q=' + encodeURIComponent(q), { signal: scontroller.signal })
          .then(r => r.json())
          .then(res => { if (res.ok) renderSearch(res.items); })
          .catch(() => {});
      }, 220);
    });
    searchInput.addEventListener('focus', function () {
      if (searchInput.value.trim().length >= 3 && searchResults.children.length) {
        searchResults.classList.add('open');
      }
    });
    document.addEventListener('click', function (e) {
      if (!e.target.closest('.nav-search')) closeSearch();
    });
    searchInput.addEventListener('keydown', function (e) {
      const opts = $$('a', searchResults);
      if (e.key === 'Escape') { closeSearch(); return; }
      if (!opts.length) return;
      let idx = opts.findIndex(o => o.classList.contains('hl'));
      if (e.key === 'ArrowDown') { e.preventDefault(); idx = Math.min(idx + 1, opts.length - 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); idx = Math.max(idx - 1, 0); }
      else if (e.key === 'Enter') { if (idx >= 0) { e.preventDefault(); window.location.href = opts[idx].href; } return; }
      else return;
      opts.forEach(o => o.classList.remove('hl'));
      if (opts[idx]) opts[idx].classList.add('hl');
    });
  }

  /* ============================== Mobile mega-menu toggle ============================== */
  document.querySelectorAll('.nav-item > a').forEach(function (a) {
    a.addEventListener('click', function (e) {
      if (window.matchMedia('(max-width: 900px)').matches) {
        const item = a.parentElement;
        if (!item.classList.contains('open')) {
          e.preventDefault();
          item.classList.add('open');
        }
      }
    });
  });
})();
