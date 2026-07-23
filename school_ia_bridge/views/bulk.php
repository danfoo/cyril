<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <h4 class="no-margin" style="margin-bottom:15px;"><i class="fa fa-paper-plane"></i> Nouvelle campagne <?php echo sia_help('bulk'); ?></h4>

    <?php
    // Bloc de filtres de ciblage réutilisé pour l'e-mail et le SMS.
    $targetFilters = function ($tab) use ($model, $programs, $staff) {
        ob_start(); ?>
        <div class="form-group">
          <label class="control-label">Étape</label>
          <select name="stage" class="form-control sia-bulk-filter">
            <option value="">Toutes</option>
            <?php foreach ($model->stages() as $s => $conf) { ?>
              <option value="<?php echo $s; ?>"><?php echo htmlspecialchars($conf[0], ENT_QUOTES); ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="form-group">
          <label class="control-label">Programme</label>
          <select name="program" class="form-control sia-bulk-filter">
            <option value="">Tous</option>
            <?php foreach ($programs as $p) { ?>
              <option value="<?php echo htmlspecialchars($p, ENT_QUOTES); ?>"><?php echo htmlspecialchars($p, ENT_QUOTES); ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="form-group">
          <label class="control-label">Responsable</label>
          <select name="owner" class="form-control sia-bulk-filter">
            <option value="">Tous</option>
            <option value="<?php echo (int) get_staff_user_id(); ?>">Mes leads</option>
            <option value="none">Non assignés</option>
            <?php foreach ($staff as $st) { ?>
              <option value="<?php echo (int) $st->staffid; ?>"><?php echo htmlspecialchars($st->firstname . ' ' . $st->lastname, ENT_QUOTES); ?></option>
            <?php } ?>
          </select>
        </div>
        <div class="row">
          <div class="col-xs-6 form-group">
            <label class="control-label">Reçu depuis</label>
            <input type="date" name="date_from" class="form-control sia-bulk-filter">
          </div>
          <div class="col-xs-6 form-group">
            <label class="control-label">Reçu jusqu'au</label>
            <input type="date" name="date_to" class="form-control sia-bulk-filter">
          </div>
        </div>
        <div class="form-group">
          <label class="control-label">Score minimum</label>
          <input type="number" name="min_score" class="form-control sia-bulk-filter" placeholder="ex. 60">
        </div>
        <div class="sia-recipient-badge" id="sia-count-<?php echo $tab; ?>">
          <i class="fa fa-users"></i> <strong>…</strong> destinataire(s) ciblé(s)
        </div>
        <p class="text-muted" style="font-size:12px;margin-top:8px;">
          <?php echo $tab === 'email' ? 'Seuls les leads avec e-mail sont contactés.' : 'Seuls les leads avec un numéro sont contactés.'; ?>
        </p>
        <?php return ob_get_clean();
    };
    ?>

    <ul class="nav nav-tabs" role="tablist" style="margin-bottom:15px;">
      <li role="presentation" class="active"><a href="#bulk-email" data-toggle="tab"><i class="fa fa-envelope"></i> E-mail groupé</a></li>
      <li role="presentation"><a href="#bulk-sms" data-toggle="tab"><i class="fa fa-mobile"></i> SMS groupé</a></li>
    </ul>

    <div class="tab-content">
      <!-- ============ E-MAIL ============ -->
      <div role="tabpanel" class="tab-pane active" id="bulk-email">
        <?php echo form_open(admin_url('school_ia_bridge/bulk_send'), ['id' => 'sia-bulk-email-form', 'onsubmit' => "return siaBulkConfirm('email');"]); ?>
        <div class="row">
          <div class="col-md-4">
            <div class="panel_s"><div class="panel-body">
              <h5 class="bold" style="margin-top:0;">1. Cibler</h5>
              <?php echo $targetFilters('email'); ?>
            </div></div>
          </div>
          <div class="col-md-8">
            <div class="panel_s"><div class="panel-body">
              <h5 class="bold" style="margin-top:0;">2. Rédiger</h5>
              <?php if (!empty($emailTpls)) { ?>
                <div class="form-group">
                  <label class="control-label">Modèle</label>
                  <select class="form-control" id="bulk-email-tpl">
                    <option value="">— Choisir un modèle —</option>
                    <?php foreach ($emailTpls as $tpl) { ?>
                      <option value="" data-subject="<?php echo htmlspecialchars((string) $tpl->subject, ENT_QUOTES); ?>"
                              data-body="<?php echo htmlspecialchars((string) $tpl->body, ENT_QUOTES); ?>">
                        <?php echo htmlspecialchars((string) $tpl->name, ENT_QUOTES); ?>
                      </option>
                    <?php } ?>
                  </select>
                </div>
              <?php } else { ?>
                <p class="text-muted" style="font-size:12px;">Astuce : créez des modèles réutilisables dans <a href="<?php echo admin_url('school_ia_bridge/templates'); ?>">Modèles</a>.</p>
              <?php } ?>
              <div class="form-group">
                <label class="control-label">Objet</label>
                <input type="text" name="subject" id="bulk-email-subject" class="form-control" required>
              </div>
              <div class="form-group">
                <label class="control-label">Message</label>
                <textarea name="message" id="bulk-email-message" class="form-control" rows="6" required></textarea>
                <p class="text-muted" style="font-size:12px;">Variables : <code>{prenom}</code>, <code>{formation}</code></p>
              </div>
              <?php if (!empty($documents)) { ?>
                <div class="form-group">
                  <label class="control-label"><i class="fa fa-paperclip"></i> Pièces jointes</label>
                  <div style="max-height:110px; overflow-y:auto; border:1px solid var(--sia-border); border-radius:8px; padding:6px;">
                    <?php foreach ($documents as $doc) { ?>
                      <label style="display:block; font-weight:normal; margin:2px 0;">
                        <input type="checkbox" name="attachments[]" value="<?php echo (int) $doc->id; ?>">
                        <?php echo htmlspecialchars(($doc->program ? $doc->program . ' · ' : '') . $doc->title, ENT_QUOTES); ?>
                      </label>
                    <?php } ?>
                  </div>
                </div>
              <?php } ?>
              <button type="button" class="btn btn-default" onclick="siaBulkPreview('email');"><i class="fa fa-eye"></i> Aperçu</button>
              <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Envoyer les e-mails</button>
            </div></div>
          </div>
        </div>
        <?php echo form_close(); ?>
      </div>

      <!-- ============ SMS ============ -->
      <div role="tabpanel" class="tab-pane" id="bulk-sms">
        <?php echo form_open(admin_url('school_ia_bridge/bulk_sms_send'), ['id' => 'sia-bulk-sms-form', 'onsubmit' => "return siaBulkConfirm('sms');"]); ?>
        <div class="row">
          <div class="col-md-4">
            <div class="panel_s"><div class="panel-body">
              <h5 class="bold" style="margin-top:0;">1. Cibler</h5>
              <?php echo $targetFilters('sms'); ?>
            </div></div>
          </div>
          <div class="col-md-8">
            <div class="panel_s"><div class="panel-body">
              <h5 class="bold" style="margin-top:0;">2. Message SMS</h5>
              <?php if (!empty($smsTpls)) { ?>
                <div class="form-group">
                  <label class="control-label">Modèle</label>
                  <select class="form-control" id="bulk-sms-tpl">
                    <option value="">— Choisir un modèle —</option>
                    <?php foreach ($smsTpls as $tpl) { ?>
                      <option value="" data-body="<?php echo htmlspecialchars((string) $tpl->body, ENT_QUOTES); ?>">
                        <?php echo htmlspecialchars((string) $tpl->name, ENT_QUOTES); ?>
                      </option>
                    <?php } ?>
                  </select>
                </div>
              <?php } ?>
              <div class="form-group">
                <label class="control-label">Texte</label>
                <textarea name="text" id="bulk-sms-text" class="form-control" rows="4" maxlength="459" required></textarea>
                <p class="text-muted" style="font-size:12px;">Variables : <code>{prenom}</code>, <code>{formation}</code></p>
              </div>
              <button type="button" class="btn btn-default" onclick="siaBulkPreview('sms');"><i class="fa fa-eye"></i> Aperçu</button>
              <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Envoyer les SMS</button>
            </div></div>
          </div>
        </div>
        <?php echo form_close(); ?>
      </div>
    </div>

    <div class="alert alert-info" style="margin-top:6px;">
      <i class="fa fa-info-circle"></i> Chaque envoi est <strong>tracé dans la fiche du prospect</strong> (onglet Notes &amp; suivi) et compté dans <a href="<?php echo admin_url('school_ia_bridge/campaigns'); ?>">Statistiques des campagnes</a> (ouvertures, clics…).
    </div>

    <!-- Modale d'aperçu -->
    <div class="modal fade" id="sia-preview-modal" tabindex="-1" role="dialog">
      <div class="modal-dialog" role="document"><div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title"><i class="fa fa-eye"></i> Aperçu du message</h4>
        </div>
        <div class="modal-body">
          <p class="text-muted" style="font-size:12px;">Rendu pour un destinataire d'exemple : <strong id="sia-preview-sample">—</strong></p>
          <div id="sia-preview-subject-wrap" style="margin-bottom:10px;">
            <div class="control-label" style="font-size:12px;">Objet</div>
            <div id="sia-preview-subject" style="font-weight:700;"></div>
          </div>
          <div class="control-label" style="font-size:12px;">Message</div>
          <div id="sia-preview-body" style="white-space:pre-wrap; background:var(--sia-surface-2); border-radius:8px; padding:12px 14px; line-height:1.5;"></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button></div>
      </div></div>
    </div>

  </div>
</div>
<script>
(function () {
  var COUNT_URL = '<?php echo admin_url('school_ia_bridge/bulk_count'); ?>';
  var samples = { email: {prenom: 'Prénom', formation: 'votre formation'}, sms: {prenom: 'Prénom', formation: 'votre formation'} };

  function wireTpl(selId, subjId, bodyId) {
    var sel = document.getElementById(selId); if (!sel) return;
    sel.addEventListener('change', function () {
      var o = sel.options[sel.selectedIndex]; if (!o) return;
      if (subjId && o.getAttribute('data-subject') !== null) document.getElementById(subjId).value = o.getAttribute('data-subject') || '';
      if (o.getAttribute('data-body') !== null) document.getElementById(bodyId).value = o.getAttribute('data-body') || '';
    });
  }
  wireTpl('bulk-email-tpl', 'bulk-email-subject', 'bulk-email-message');
  wireTpl('bulk-sms-tpl', null, 'bulk-sms-text');

  function formOf(tab) { return document.getElementById('sia-bulk-' + tab + '-form'); }

  function filterParams(tab) {
    var form = formOf(tab); var p = new URLSearchParams();
    if (!form) return p;
    ['stage', 'program', 'owner', 'date_from', 'date_to', 'min_score'].forEach(function (n) {
      var el = form.querySelector('[name="' + n + '"]');
      if (el && el.value) p.set(n, el.value);
    });
    return p;
  }

  function updateCount(tab) {
    var badge = document.getElementById('sia-count-' + tab);
    if (!badge) return;
    fetch(COUNT_URL + '?' + filterParams(tab).toString(), {headers: {'X-Requested-With': 'XMLHttpRequest'}})
      .then(function (r) { return r.json(); })
      .then(function (d) {
        samples[tab] = d.sample || samples[tab];
        var n = tab === 'email' ? d.email : d.sms;
        badge.querySelector('strong').textContent = n;
        badge.classList.toggle('sia-recipient-zero', n === 0);
      })
      .catch(function () {});
  }

  ['email', 'sms'].forEach(function (tab) {
    var form = formOf(tab);
    if (!form) return;
    form.querySelectorAll('.sia-bulk-filter').forEach(function (el) {
      el.addEventListener('change', function () { updateCount(tab); });
      el.addEventListener('input', function () { updateCount(tab); });
    });
    updateCount(tab);
  });

  function render(tpl, sample) {
    return (tpl || '').split('{prenom}').join(sample.prenom).split('{formation}').join(sample.formation);
  }

  window.siaBulkPreview = function (tab) {
    var s = samples[tab] || {prenom: 'Prénom', formation: 'votre formation'};
    document.getElementById('sia-preview-sample').textContent = s.prenom + ' · ' + s.formation;
    var subjWrap = document.getElementById('sia-preview-subject-wrap');
    if (tab === 'email') {
      subjWrap.style.display = '';
      document.getElementById('sia-preview-subject').textContent = render(document.getElementById('bulk-email-subject').value, s);
      document.getElementById('sia-preview-body').textContent = render(document.getElementById('bulk-email-message').value, s);
    } else {
      subjWrap.style.display = 'none';
      document.getElementById('sia-preview-body').textContent = render(document.getElementById('bulk-sms-text').value, s);
    }
    try { jQuery('#sia-preview-modal').modal('show'); } catch (e) {}
  };

  window.siaBulkConfirm = function (tab) {
    var badge = document.getElementById('sia-count-' + tab);
    var n = badge ? parseInt(badge.querySelector('strong').textContent, 10) : 0;
    if (!n || isNaN(n)) { alert('Aucun destinataire ne correspond à ces filtres.'); return false; }
    return confirm('Envoyer ' + (tab === 'email' ? "l'e-mail" : 'le SMS') + ' à ' + n + ' destinataire(s) ?');
  };
})();
</script>
<?php init_tail(); ?>
</body>
</html>
