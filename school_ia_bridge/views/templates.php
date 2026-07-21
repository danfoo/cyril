<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <h4 class="no-margin" style="margin-bottom:15px;"><i class="fa fa-file-text-o"></i> Modèles d'e-mail & SMS</h4>

    <div class="row">
      <!-- Formulaire -->
      <div class="col-md-5">
        <div class="panel_s"><div class="panel-body">
          <h5 class="bold" style="margin-top:0;"><?php echo $edit ? 'Modifier le modèle' : 'Nouveau modèle'; ?></h5>
          <?php echo form_open(admin_url('school_ia_bridge/template_save')); ?>
            <input type="hidden" name="id" value="<?php echo $edit ? (int) $edit->id : ''; ?>">
            <div class="form-group">
              <label class="control-label">Type</label>
              <select name="type" class="form-control" id="tpl-type">
                <option value="email" <?php echo ($edit && $edit->type === 'email') ? 'selected' : ''; ?>>E-mail</option>
                <option value="sms" <?php echo ($edit && $edit->type === 'sms') ? 'selected' : ''; ?>>SMS</option>
              </select>
            </div>
            <div class="form-group">
              <label class="control-label">Nom du modèle</label>
              <input type="text" name="name" class="form-control" required
                     value="<?php echo $edit ? htmlspecialchars((string) $edit->name, ENT_QUOTES) : ''; ?>">
            </div>
            <div class="form-group" id="tpl-subject-group">
              <label class="control-label">Objet (e-mail)</label>
              <input type="text" name="subject" class="form-control"
                     value="<?php echo $edit ? htmlspecialchars((string) $edit->subject, ENT_QUOTES) : ''; ?>">
            </div>
            <div class="form-group">
              <label class="control-label">Message</label>
              <textarea name="body" class="form-control" rows="7"><?php echo $edit ? htmlspecialchars((string) $edit->body, ENT_QUOTES) : ''; ?></textarea>
              <p class="text-muted" style="font-size:12px;margin-top:4px;">
                Variables : <code>{prenom}</code>, <code>{formation}</code>
              </p>
            </div>
            <button type="submit" class="btn btn-primary"><?php echo $edit ? 'Enregistrer' : 'Créer'; ?></button>
            <?php if ($edit) { ?>
              <a href="<?php echo admin_url('school_ia_bridge/templates'); ?>" class="btn btn-default">Annuler</a>
            <?php } ?>
          <?php echo form_close(); ?>
        </div></div>
      </div>

      <!-- Liste -->
      <div class="col-md-7">
        <div class="panel_s"><div class="panel-body">
          <table class="table">
            <thead><tr><th>Type</th><th>Nom</th><th>Aperçu</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($templates)) { ?>
              <tr><td colspan="4" class="text-center text-muted" style="padding:20px;">Aucun modèle. Créez-en un à gauche.</td></tr>
            <?php } else {
                foreach ($templates as $t) { ?>
              <tr>
                <td><span class="label <?php echo $t->type === 'sms' ? 'label-info' : 'label-primary'; ?>"><?php echo strtoupper($t->type); ?></span></td>
                <td><?php echo htmlspecialchars((string) $t->name, ENT_QUOTES); ?></td>
                <td class="text-muted"><?php echo htmlspecialchars(mb_substr((string) $t->body, 0, 60), ENT_QUOTES); ?>…</td>
                <td class="text-right">
                  <a href="<?php echo admin_url('school_ia_bridge/templates?edit=' . (int) $t->id); ?>" class="btn btn-xs btn-default"><i class="fa fa-pencil"></i></a>
                  <a href="<?php echo admin_url('school_ia_bridge/template_delete/' . (int) $t->id); ?>" class="btn btn-xs btn-default"
                     onclick="return confirm('Supprimer ce modèle ?');"><i class="fa fa-trash"></i></a>
                </td>
              </tr>
            <?php }
            } ?>
            </tbody>
          </table>
        </div></div>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  var sel = document.getElementById('tpl-type');
  var grp = document.getElementById('tpl-subject-group');
  function sync() { grp.style.display = (sel.value === 'sms') ? 'none' : 'block'; }
  if (sel && grp) { sel.addEventListener('change', sync); sync(); }
})();
</script>
<?php init_tail(); ?>
</body>
</html>
