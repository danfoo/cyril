<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <h4 class="no-margin" style="margin-bottom:15px;"><i class="fa fa-paper-plane"></i> Envoi groupé d'e-mails</h4>

    <?php echo form_open(admin_url('school_ia_bridge/bulk_send'), ['onsubmit' => "return confirm('Envoyer l\\'e-mail à tous les leads correspondant aux filtres ?');"]); ?>
    <div class="row">
      <!-- Ciblage -->
      <div class="col-md-4">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">1. Cibler les destinataires</h5>
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
            <label class="control-label">Programme (dans la formation)</label>
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
          <p class="text-muted" style="font-size:12px;">Seuls les leads ayant une adresse e-mail sont contactés.</p>
        </div></div>
      </div>

      <!-- Message -->
      <div class="col-md-8">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;">2. Rédiger le message</h5>
          <?php if (!empty($emailTpls)) { ?>
            <div class="form-group">
              <label class="control-label">Modèle</label>
              <select class="form-control" id="bulk-tpl">
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
            <input type="text" name="subject" id="bulk-subject" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="control-label">Message</label>
            <textarea name="message" id="bulk-message" class="form-control" rows="7" required></textarea>
            <p class="text-muted" style="font-size:12px;margin-top:4px;">
              Variables personnalisées par destinataire : <code>{prenom}</code>, <code>{formation}</code>
            </p>
          </div>

          <?php if (!empty($documents)) { ?>
            <div class="form-group">
              <label class="control-label"><i class="fa fa-paperclip"></i> Pièces jointes</label>
              <div style="max-height:120px; overflow-y:auto; border:1px solid #eee; border-radius:4px; padding:6px;">
                <?php foreach ($documents as $doc) { ?>
                  <label style="display:block; font-weight:normal; margin:2px 0;">
                    <input type="checkbox" name="attachments[]" value="<?php echo (int) $doc->id; ?>">
                    <?php echo htmlspecialchars(($doc->program ? $doc->program . ' · ' : '') . $doc->title, ENT_QUOTES); ?>
                  </label>
                <?php } ?>
              </div>
            </div>
          <?php } ?>

          <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Envoyer à tous les leads ciblés</button>
        </div></div>
      </div>
    </div>
    <?php echo form_close(); ?>

  </div>
</div>
<script>
(function () {
  var sel = document.getElementById('bulk-tpl');
  if (!sel) return;
  sel.addEventListener('change', function () {
    var o = sel.options[sel.selectedIndex]; if (!o) return;
    if (o.getAttribute('data-subject') !== null) document.getElementById('bulk-subject').value = o.getAttribute('data-subject') || '';
    if (o.getAttribute('data-body') !== null) document.getElementById('bulk-message').value = o.getAttribute('data-body') || '';
  });
})();
</script>
<?php init_tail(); ?>
</body>
</html>
