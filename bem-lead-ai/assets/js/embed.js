/**
 * Snippet universel d'embarquement — School IA / bem-lead-ai.
 *
 * À coller sur N'IMPORTE QUEL site (WordPress ou non) :
 *
 *   <script src="https://VOTRE-BACKEND/wp-content/plugins/bem-lead-ai/assets/js/embed.js"
 *           data-backend="https://VOTRE-BACKEND/wp-json/bem-lead-ai/v1"
 *           data-key="VOTRE_CLE_DE_SITE" defer></script>
 *
 * Le script récupère la configuration du widget auprès du backend, charge le
 * widget de chat, et écoute les soumissions de formulaires de la page pour les
 * transmettre au CRM — sans dépendance à WordPress côté site client.
 */
(function () {
  'use strict';

  var self = document.currentScript;
  if (!self) { return; }
  var BACKEND = (self.getAttribute('data-backend') || '').replace(/\/+$/, '');
  var KEY = self.getAttribute('data-key') || '';
  if (!BACKEND || !KEY) {
    console.warn('[school-ia] data-backend et data-key sont requis sur le <script> d\'embarquement.');
    return;
  }

  var SESSION_KEY = 'bem_lead_session';

  /* ---- Session partagée avec le widget (même clé localStorage/cookie) ---- */
  function readCookie(name) {
    var m = document.cookie.match('(?:^|; )' + name + '=([^;]*)');
    return m ? decodeURIComponent(m[1]) : null;
  }
  function sessionId() {
    var id = null;
    try { id = localStorage.getItem(SESSION_KEY); } catch (e) {}
    if (!id) { id = readCookie(SESSION_KEY); }
    return id || '';
  }

  /* Attribution de campagne (UTM) « premier contact » : figée 90 jours. */
  function writeCookie(name, value, days) {
    try {
      var d = new Date();
      d.setTime(d.getTime() + days * 864e5);
      document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
    } catch (e) {}
  }
  function utm() {
    var stored = readCookie('bem_lead_utm');
    if (stored) { try { return JSON.parse(stored); } catch (e) {} }
    var p;
    try { p = new URLSearchParams(location.search); } catch (e) { return { source: '', medium: '', campaign: '' }; }
    var u = { source: p.get('utm_source') || '', medium: p.get('utm_medium') || '', campaign: p.get('utm_campaign') || '' };
    if (u.source || u.medium || u.campaign) { writeCookie('bem_lead_utm', JSON.stringify(u), 90); }
    return u;
  }

  // Fige l'attribution UTM dès l'atterrissage (avant toute navigation).
  utm();

  /* ---- Chargement du widget après récupération de la config ---- */
  fetch(BACKEND + '/embed/config?key=' + encodeURIComponent(KEY), { method: 'GET' })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (cfg) {
      if (!cfg || !cfg.restUrl) {
        console.warn('[school-ia] Configuration indisponible (clé de site invalide ?).');
        return;
      }
      // Le widget lit window.BemLeadAiConfig, exactement comme sous WordPress.
      cfg.pageContext = {
        url: location.href,
        title: document.title,
        page_kind: 'generic'
      };
      window.BemLeadAiConfig = cfg;

      // Feuille de style de base + script du widget, servis par le backend.
      var assets = (cfg.assetsUrl || '').replace(/\/+$/, '');
      if (assets) {
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = assets + '/css/widget.css';
        document.head.appendChild(link);

        // Variables CSS des couleurs/design (sous WordPress elles sont injectées
        // en inline ; ici elles viennent de la config). Sans ça, le widget
        // s'affiche mais garde les couleurs par défaut.
        if (cfg.inlineCss) {
          var style = document.createElement('style');
          style.textContent = cfg.inlineCss;
          document.head.appendChild(style);
        }

        var s = document.createElement('script');
        s.src = assets + '/js/widget.js';
        s.defer = true;
        document.body.appendChild(s);
      }
    })
    .catch(function () { /* silencieux : le site ne doit jamais casser à cause du widget */ });

  /* ---- Capture universelle des formulaires ---- */
  // Heuristiques légères pour retrouver les champs quel que soit le formulaire.
  function fieldValue(form, patterns, types) {
    var els = form.querySelectorAll('input, select, textarea');
    for (var i = 0; i < els.length; i++) {
      var el = els[i];
      if (!el.value || !el.value.trim()) { continue; }
      if (types && types.indexOf((el.getAttribute('type') || '').toLowerCase()) !== -1) {
        return el.value.trim();
      }
      var hay = ((el.name || '') + ' ' + (el.id || '') + ' ' + (el.getAttribute('placeholder') || '')).toLowerCase();
      if (patterns.test(hay)) { return el.value.trim(); }
    }
    return '';
  }

  function formTitle(form) {
    return (form.getAttribute('data-sia-form') || form.getAttribute('name') || form.getAttribute('id') || '').toString().slice(0, 190);
  }

  function capture(form) {
    var email = fieldValue(form, /mail|courriel/, ['email']);
    var phone = fieldValue(form, /t[ée]l|phone|mobile|whatsapp|num[ée]ro/, ['tel']);
    if (!email && !phone) { return; } // rien d'exploitable → on n'envoie pas
    var name = fieldValue(form, /nom|name|pr[ée]nom/, []);
    var formation = fieldValue(form, /formation|programme|fili[èe]re|cursus|dipl[ôo]me/, []);

    var body = new URLSearchParams();
    body.set('key', KEY);
    body.set('email', email);
    body.set('phone', phone);
    body.set('name', name);
    body.set('formation', formation);
    body.set('source_form', formTitle(form));
    body.set('session_id', sessionId());
    var u = utm();
    body.set('utm[source]', u.source || '');
    body.set('utm[medium]', u.medium || '');
    body.set('utm[campaign]', u.campaign || '');

    // keepalive : l'envoi survit à la navigation qui suit la soumission.
    fetch(BACKEND + '/embed/capture', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
      keepalive: true
    }).catch(function () {});
  }

  // On écoute en phase de capture pour agir avant une éventuelle navigation.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form && form.tagName === 'FORM' && form.getAttribute('data-sia-ignore') === null) {
      try { capture(form); } catch (err) {}
    }
  }, true);
})();
