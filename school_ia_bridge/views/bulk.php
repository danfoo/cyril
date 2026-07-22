<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <h4 class="no-margin" style="margin-bottom:15px;"><i class="fa fa-paper-plane"></i> Envoi groupé <?php echo sia_help('bulk'); ?></h4>

    <ul class="nav nav-tabs" role="tablist" style="margin-bottom:15px;">
      <li role="presentation" class="active"><a href="#bulk-email" data-toggle="tab"><i class="fa fa-envelope"></i> E-mail groupé</a></li>
      <li role="presentation"><a href="#bulk-sms" data-toggle="tab"><i class="fa fa-mobile"></i> SMS groupé</a></li>
    </ul>

    <div class="tab-content">
      <!-- ============ E-MAIL ============ -->
      <div role="tabpanel" class="tab-pane active" id="bulk-email">
        <?php echo form_open(admin_url('school_ia_bridge/bulk_send'), ['onsubmit' => "return confirm('Envoyer l\\'e-mail à tous les leads ciblés ?');"]); ?>
        <div class="row">
          <div class="col-md-4">
            <div class="panel_s"><div class="panel-body">
              <h5 class="bold" style="margin-top:0;">1. Cibler</h5>
              <div class="form-group">
                <label class="control-label">Étape</label>
                <select name="stage" class="form-control">
                  <option value="">Toutes</option>
                  <?php foreach ($model->stages() as $s => $conf) { ?>
                    <option value="<?php echo $s; ?>"><?php echo htmlspecialchars($conf[0], ENT_QUOTES); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="form-group">
                <label class="control-label">Programme</label>
                <select name="program" class="form-control">
                  <option value="">Tous</option>
                  <?php foreach ($programs as $p) { ?>
                    <option value="<?php echo htmlspecialchars($p, ENT_QUOTES); ?>"><?php echo htmlspecialchars($p, ENT_QUOTES); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="form-group">
                <label class="control-label">Score minimum</label>
                <input type="number" name="min_score" class="form-control" placeholder="ex. 60">
              </div>
              <p class="text-muted" style="font-size:12px;">Seuls les leads avec e-mail sont contactés.</p>
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
                  <div style="max-height:110px; overflow-y:auto; border:1px solid #eee; border-radius:4px; padding:6px;">
                    <?php foreach ($documents as $doc) { ?>
                      <label style="display:block; font-weight:normal; margin:2px 0;">
                        <input type="checkbox" name="attachments[]" value="<?php echo (int) $doc->id; ?>">
                        <?php echo htmlspecialchars(($doc->program ? $doc->program . ' · ' : '') . $doc->title, ENT_QUOTES); ?>
                      </label>
                    <?php } ?>
                  </div>
                </div>
              <?php } ?>
              <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Envoyer les e-mails</button>
            </div></div>
          </div>
        </div>
        <?php echo form_close(); ?>
      </div>

      <!-- ============ SMS ============ -->
      <div role="tabpanel" class="tab-pane" id="bulk-sms">
        <?php echo form_open(admin_url('school_ia_bridge/bulk_sms_send'), ['onsubmit' => "return confirm('Envoyer le SMS à tous les leads ciblés ?');"]); ?>
        <div class="row">
          <div class="col-md-4">
            <div class="panel_s"><div class="panel-body">
              <h5 class="bold" style="margin-top:0;">1. Cibler</h5>
              <div class="form-group">
                <label class="control-label">Étape</label>
                <select name="stage" class="form-control">
                  <option value="">Toutes</option>
                  <?php foreach ($model->stages() as $s => $conf) { ?>
                    <option value="<?php echo $s; ?>"><?php echo htmlspecialchars($conf[0], ENT_QUOTES); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="form-group">
                <label class="control-label">Programme</label>
                <select name="program" class="form-control">
                  <option value="">Tous</option>
                  <?php foreach ($programs as $p) { ?>
                    <option value="<?php echo htmlspecialchars($p, ENT_QUOTES); ?>"><?php echo htmlspecialchars($p, ENT_QUOTES); ?></option>
                  <?php } ?>
                </select>
              </div>
              <div class="form-group">
                <label class="control-label">Score minimum</label>
                <input type="number" name="min_score" class="form-control" placeholder="ex. 60">
              </div>
              <p class="text-muted" style="font-size:12px;">Seuls les leads avec un numéro sont contactés.</p>
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
              <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Envoyer les SMS</button>
            </div></div>
          </div>
        </div>
        <?php echo form_close(); ?>
      </div>
    </div>

  </div>
</div>
<script>
(function () {
  function wire(selId, subjId, bodyId) {
    var sel = document.getElementById(selId); if (!sel) return;
    sel.addEventListener('change', function () {
      var o = sel.options[sel.selectedIndex]; if (!o) return;
      if (subjId && o.getAttribute('data-subject') !== null) document.getElementById(subjId).value = o.getAttribute('data-subject') || '';
      if (o.getAttribute('data-body') !== null) document.getElementById(bodyId).value = o.getAttribute('data-body') || '';
    });
  }
  wire('bulk-email-tpl', 'bulk-email-subject', 'bulk-email-message');
  wire('bulk-sms-tpl', null, 'bulk-sms-text');
})();
</script>
<?php init_tail(); ?>
</body>
</html>
