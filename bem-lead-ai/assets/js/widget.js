/**
 * BEM Lead AI — widget de chat + tracking comportemental.
 * JS vanilla, sans dépendance, scopé pour éviter les conflits de thème.
 *
 * Principe non-fonctionnel : tracking et scoring ne bloquent jamais le rendu
 * ni la conversation. Tous les appels sont asynchrones et échouent en silence.
 */
(function () {
  'use strict';

  var CFG = window.BemLeadAiConfig || {};
  if (!CFG.restUrl) return;

  var STORAGE_KEY = 'bem_lead_session';
  var CONSENT_KEY = 'bem_lead_consent';

  /* ---- Session unifiée (persistée en localStorage) ------------------- */
  function sessionId() {
    var id = null;
    try { id = localStorage.getItem(STORAGE_KEY); } catch (e) {}
    if (!id) {
      id = 'web_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10);
      try { localStorage.setItem(STORAGE_KEY, id); } catch (e) {}
    }
    return id;
  }

  function hasConsent() {
    try { return localStorage.getItem(CONSENT_KEY) === '1'; } catch (e) { return false; }
  }

  function api(path, body, method) {
    return fetch(CFG.restUrl + path, {
      method: method || 'POST',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': CFG.nonce || '' },
      body: body ? JSON.stringify(body) : undefined,
      credentials: 'same-origin'
    }).then(function (r) { return r.ok ? r.json() : Promise.reject(r); });
  }

  function track(type, payload) {
    if (!hasConsent() && type !== 'consent_given') return;
    api('/track', { session_id: sessionId(), type: type, payload: payload || {} }).catch(function () {});
  }

  /* ---- Consentement (loi n°2008-12 CDP Sénégal) ---------------------- */
  function renderConsentBanner() {
    if (hasConsent()) return;
    var banner = document.createElement('div');
    banner.className = 'bem-consent';
    banner.innerHTML =
      '<span>Nous utilisons un suivi anonyme pour améliorer votre orientation et notre conseiller IA. ' +
      '<a href="' + (CFG.privacyUrl || '/politique-de-confidentialite') + '" target="_blank" rel="noopener">En savoir plus</a>.</span>' +
      '<div class="bem-consent-actions">' +
      '<button type="button" class="bem-btn bem-btn-ghost" data-bem="refuse">Refuser</button>' +
      '<button type="button" class="bem-btn bem-btn-primary" data-bem="accept">Accepter</button>' +
      '</div>';
    document.body.appendChild(banner);

    banner.querySelector('[data-bem="accept"]').addEventListener('click', function () {
      try { localStorage.setItem(CONSENT_KEY, '1'); } catch (e) {}
      track('consent_given');
      trackPageView();
      banner.remove();
    });
    banner.querySelector('[data-bem="refuse"]').addEventListener('click', function () {
      try { localStorage.setItem(CONSENT_KEY, '0'); } catch (e) {}
      banner.remove();
    });
  }

  /* ---- Tracking comportemental --------------------------------------- */
  var pageStart = Date.now();

  function trackPageView() {
    var ctx = CFG.pageContext || {};
    // Visite de retour : déjà venu il y a plus de 30 min ?
    var lastVisit = 0;
    try { lastVisit = parseInt(localStorage.getItem('bem_last_visit') || '0', 10); } catch (e) {}
    var now = Date.now();
    if (lastVisit && now - lastVisit > 30 * 60 * 1000) {
      track('return_visit', { page_kind: ctx.page_kind });
    }
    try { localStorage.setItem('bem_last_visit', String(now)); } catch (e) {}

    track('page_view', {
      page_kind: ctx.page_kind || 'generic',
      post_id: ctx.post_id || 0,
      url: ctx.url || location.href,
      title: ctx.title || document.title
    });
  }

  function bindEngagementTracking() {
    // Temps passé sur la page (envoyé au départ).
    window.addEventListener('beforeunload', function () {
      var seconds = Math.round((Date.now() - pageStart) / 1000);
      if (seconds < 5 || !hasConsent()) return;
      var ctx = CFG.pageContext || {};
      // sendBeacon pour ne pas bloquer le déchargement.
      try {
        navigator.sendBeacon(
          CFG.restUrl + '/track',
          new Blob([JSON.stringify({ session_id: sessionId(), type: 'time_on_page', payload: { seconds: seconds, page_kind: ctx.page_kind } })], { type: 'application/json' })
        );
      } catch (e) {}
    });

    // Clics sur CTA candidature / brochure (attributs data ou libellés).
    document.addEventListener('click', function (e) {
      var el = e.target.closest('a, button');
      if (!el) return;
      var label = (el.getAttribute('data-bem-track') || el.textContent || '').toLowerCase();
      var href = (el.getAttribute('href') || '').toLowerCase();
      if (/candidat|postul|inscri|apply/.test(label + href)) {
        track('cta_click', { label: label.slice(0, 60) });
      } else if (/brochure|plaquette|catalogue|\.pdf/.test(label + href)) {
        track('brochure_download', { label: label.slice(0, 60) });
      }
    });
  }

  /* ---- Widget de chat ------------------------------------------------ */
  var chatOpen = false;
  var lastMessageId = 0;
  var pollTimer = null;

  function buildWidget() {
    var design = CFG.design || {};
    var root = document.createElement('div');
    root.className = 'bem-widget';

    // Avatar/logo utilisé dans l'en-tête, devant chaque réponse, et comme icône du lanceur.
    var hasImage = !!design.avatar;
    var avatarInner = hasImage
      ? '<img src="' + encodeURI(design.avatar) + '" alt="">'
      : '<span>🎓</span>';
    var launcherIcon = hasImage
      ? '<img src="' + encodeURI(design.avatar) + '" alt="">'
      : escapeHtml(design.launcher || '💬');
    var waHtml = CFG.whatsappEnabled
      ? '<button type="button" class="bem-wa" data-bem="whatsapp">' +
        '<span class="bem-wa-icon">✆</span> ' + escapeHtml(CFG.whatsappLabel || 'Continuer sur WhatsApp') + '</button>'
      : '';

    root.innerHTML =
      '<button class="bem-launcher' + (hasImage ? ' bem-has-image' : '') + '" aria-label="Ouvrir le conseiller">' +
      '  <span class="bem-launcher-icon">' + launcherIcon + '</span>' +
      '  <span class="bem-launcher-close">×</span>' +
      '</button>' +
      '<div class="bem-panel" role="dialog" aria-label="Conseiller BEM" hidden>' +
      '  <div class="bem-header">' +
      '    <div class="bem-header-avatar">' + avatarInner + '</div>' +
      '    <div class="bem-header-text">' +
      '      <div class="bem-header-title">' + escapeHtml(CFG.title || 'Conseiller BEM') + '</div>' +
      '      <div class="bem-header-subtitle">' + escapeHtml(CFG.subtitle || 'En ligne') + '</div>' +
      '    </div>' +
      '    <button class="bem-close" aria-label="Fermer">×</button>' +
      '  </div>' +
      '  <div class="bem-messages"></div>' +
      '  <div class="bem-handoff-note" hidden>👤 Un conseiller a pris le relais.</div>' +
      '  ' + waHtml +
      '  <form class="bem-input"><input type="text" placeholder="Votre question…" autocomplete="off" required>' +
      '    <button type="submit" aria-label="Envoyer">➤</button></form>' +
      '</div>';
    document.body.appendChild(root);
    var botAvatar = avatarInner;

    var waBtn = root.querySelector('[data-bem="whatsapp"]');
    if (waBtn) {
      waBtn.addEventListener('click', function () {
        waBtn.disabled = true;
        api('/whatsapp-link', { session_id: sessionId() }).then(function (res) {
          waBtn.disabled = false;
          if (res && res.url) { window.open(res.url, '_blank', 'noopener'); }
        }).catch(function () { waBtn.disabled = false; });
      });
    }

    var launcher = root.querySelector('.bem-launcher');
    var panel = root.querySelector('.bem-panel');
    var messages = root.querySelector('.bem-messages');
    var form = root.querySelector('.bem-input');
    var input = form.querySelector('input');
    var initialized = false;

    // Molette : empêche les thèmes qui « détournent » le scroll (smooth-scroll
    // global) de voler l'événement — le défilement à la souris fonctionne alors.
    messages.addEventListener('wheel', function (e) { e.stopPropagation(); }, { passive: true });
    messages.addEventListener('touchmove', function (e) { e.stopPropagation(); }, { passive: true });

    function loadHistory() {
      api('/history?session_id=' + encodeURIComponent(sessionId()), null, 'GET')
        .then(function (res) {
          var msgs = (res && res.messages) || [];
          if (msgs.length) {
            msgs.forEach(function (m) {
              addMessage(m.role === 'user' ? 'user' : 'assistant', m.contenu);
              lastMessageId = Math.max(lastMessageId, m.id);
            });
            if (res.handoff) { root.querySelector('.bem-handoff-note').hidden = false; }
          } else {
            addMessage('assistant', CFG.greeting || 'Bonjour ! Comment puis-je vous aider ?');
          }
        })
        .catch(function () { addMessage('assistant', CFG.greeting || 'Bonjour ! Comment puis-je vous aider ?'); })
        .then(function () { startPolling(); });
    }

    function setOpen(open) {
      chatOpen = open;
      panel.hidden = !open;
      launcher.classList.toggle('bem-open', open);
      root.classList.toggle('bem-panel-open', open);
      if (open && !initialized) {
        initialized = true;
        loadHistory(); // recharge la conversation existante (continuité entre visites)
      }
      if (open) { setTimeout(function () { input.focus(); }, 60); }
    }

    launcher.addEventListener('click', function () { setOpen(!chatOpen); });
    root.querySelector('.bem-close').addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      setOpen(false);
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var text = input.value.trim();
      if (!text) return;
      addMessage('user', text);
      input.value = '';
      var typing = addTyping();
      api('/chat', { session_id: sessionId(), message: text }).then(function (res) {
        typing.remove();
        if (res.handoff) {
          root.querySelector('.bem-handoff-note').hidden = false;
        } else if (res.reply) {
          addMessage('assistant', res.reply);
        }
        if (res.last_message_id) lastMessageId = res.last_message_id;
      }).catch(function () {
        typing.remove();
        addMessage('assistant', "Désolé, un souci technique est survenu. Réessayez dans un instant.");
      });
    });

    function addMessage(role, text) {
      var isUser = role === 'user';
      var row = document.createElement('div');
      row.className = 'bem-row ' + (isUser ? 'bem-row-user' : 'bem-row-bot');
      var bubble = document.createElement('div');
      bubble.className = 'bem-msg';
      if (isUser) {
        // Messages utilisateur : texte brut (pas de Markdown).
        bubble.className += ' bem-msg-plain';
        bubble.textContent = text;
      } else {
        // Réponses du conseiller : Markdown léger rendu proprement.
        bubble.innerHTML = renderMarkdown(text);
        var av = document.createElement('div');
        av.className = 'bem-row-avatar';
        av.innerHTML = botAvatar;
        row.appendChild(av);
      }
      row.appendChild(bubble);
      messages.appendChild(row);
      messages.scrollTop = messages.scrollHeight;
      return row;
    }
    function addTyping() {
      var row = document.createElement('div');
      row.className = 'bem-row bem-row-bot';
      row.innerHTML = '<div class="bem-row-avatar">' + botAvatar + '</div>' +
        '<div class="bem-msg bem-typing"><span></span><span></span><span></span></div>';
      messages.appendChild(row);
      messages.scrollTop = messages.scrollHeight;
      return row;
    }

    /* Polling : relances proactives + réponses conseiller (handoff). */
    function startPolling() {
      if (pollTimer) return;
      pollTimer = setInterval(function () {
        if (document.hidden) return;
        api('/messages?session_id=' + encodeURIComponent(sessionId()) + '&after_id=' + lastMessageId, null, 'GET')
          .then(function (res) {
            (res.messages || []).forEach(function (m) {
              addMessage(m.role === 'user' ? 'user' : 'assistant', m.contenu);
              lastMessageId = Math.max(lastMessageId, m.id);
            });
            root.querySelector('.bem-handoff-note').hidden = !res.handoff;
          }).catch(function () {});
      }, 8000);
    }
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  /* Rendu Markdown léger et SÛR : on échappe tout le HTML d'abord, puis on
     n'ajoute que nos propres balises (gras, italique, code, listes, liens). */
  function renderMarkdown(raw) {
    var lines = escapeHtml(String(raw == null ? '' : raw)).split(/\r?\n/);
    var html = '';
    var listType = null;
    var para = [];

    function inline(s) {
      return s
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/__([^_]+)__/g, '<strong>$1</strong>')
        .replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>')
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>')
        .replace(/(^|[\s(])((https?:\/\/[^\s<]+))/g, '$1<a href="$2" target="_blank" rel="noopener">$2</a>');
    }
    function flushPara() {
      if (para.length) { html += '<p>' + inline(para.join('<br>')) + '</p>'; para = []; }
    }
    function flushList() {
      if (listType) { html += '</' + listType + '>'; listType = null; }
    }

    lines.forEach(function (line) {
      var t = line.trim();
      var ul = t.match(/^[-*•]\s+(.*)$/);
      var ol = t.match(/^\d+[.)]\s+(.*)$/);
      var hd = t.match(/^#{1,4}\s+(.*)$/);
      if (hd) {
        flushPara(); flushList();
        html += '<p><strong>' + inline(hd[1]) + '</strong></p>';
      } else if (ul) {
        flushPara();
        if (listType !== 'ul') { flushList(); html += '<ul>'; listType = 'ul'; }
        html += '<li>' + inline(ul[1]) + '</li>';
      } else if (ol) {
        flushPara();
        if (listType !== 'ol') { flushList(); html += '<ol>'; listType = 'ol'; }
        html += '<li>' + inline(ol[1]) + '</li>';
      } else if (t === '') {
        flushPara(); flushList();
      } else {
        flushList(); para.push(t);
      }
    });
    flushPara(); flushList();
    return html || '<p></p>';
  }

  /* ---- Boot ---------------------------------------------------------- */
  function boot() {
    renderConsentBanner();
    if (hasConsent()) trackPageView();
    bindEngagementTracking();
    buildWidget();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
